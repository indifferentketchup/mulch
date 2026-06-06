<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analyser;

use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\StackTraceClassificationAnalyser;
use IndifferentKetchup\CodexPz\Analysis\AttributionConfidence;
use IndifferentKetchup\CodexPz\Analysis\EngineNoiseInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineNoiseExceptionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\JavaExceptionProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\LuaModRuntimeProblem;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidServerLog;
use PHPUnit\Framework\TestCase;

class StackTraceClassificationAnalyserTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/';

    private function analyseFixture(string $fixture): \IndifferentKetchup\CodexPz\Analysis\AnalysisInterface
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new PathLogFile(self::FIXTURE_DIR . $fixture));
        $log->parse();
        return (new StackTraceClassificationAnalyser())->setLog($log)->analyse();
    }

    private function analyseString(string $content): \IndifferentKetchup\CodexPz\Analysis\AnalysisInterface
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new StringLogFile($content));
        $log->parse();
        return (new StackTraceClassificationAnalyser())->setLog($log)->analyse();
    }

    /**
     * CRIT-002 acceptance: the multi-entry B4x forward-walk actually classifies
     * exceptions. Non-zero EXCEPTION-type output is the bar — not merely a
     * non-empty Analysis.
     */
    public function testB4xExceptionFixtureProducesExceptionProblems(): void
    {
        $analysis = $this->analyseFixture('debug-server-b4x-exception-minimal.txt');

        $java = $analysis->getFilteredInsights(JavaExceptionProblem::class);
        $mod = $analysis->getFilteredInsights(LuaModRuntimeProblem::class);

        $this->assertGreaterThanOrEqual(
            1,
            count($java) + count($mod),
            'B4x fixture must yield at least one exception-type Problem (CRIT-002)'
        );
        $this->assertCount(1, $java, 'BufferUnderflow header => exactly one JavaExceptionProblem');
        $this->assertCount(1, $mod, 'Lua frame in the adjacent Stack-trace entry => one LuaModRuntimeProblem');

        $this->assertSame('java.nio.BufferUnderflowException', $java[0]->getExceptionClass());
        $this->assertSame('java.lang.RuntimeException', $mod[0]->getExceptionClass());

        // The mod frame lives on a SEPARATE entry from the exception header —
        // only the forward-walk could have surfaced it.
        $this->assertNotNull($mod[0]->getDeepestModFrame());
        $this->assertStringContainsString('ExampleFramework', (string) $mod[0]->getDeepestModFrame());
    }

    /** B4x must not double-count: the "Stack trace:" marker entries produce no insight of their own. */
    public function testB4xDoesNotDoubleCount(): void
    {
        $analysis = $this->analyseFixture('debug-server-b4x-exception-minimal.txt');
        $this->assertCount(2, $analysis->getProblems(), 'Exactly one row per underlying B4x exception');
    }

    /** B41 tab-continuation: a Lua((MOD:...)) frame yields a directly-attributed mod crash. */
    public function testB41ModRuntimeDirectAttribution(): void
    {
        $analysis = $this->analyseFixture('debug-server-b41-exception-minimal.txt');

        $mod = $analysis->getFilteredInsights(LuaModRuntimeProblem::class);
        $this->assertCount(1, $mod);

        $attribution = $mod[0]->getModAttribution();
        $this->assertNotNull($attribution);
        $this->assertSame(AttributionConfidence::Direct, $attribution->confidence);
        $this->assertSame('ExampleFramework', $attribution->modName);
        $this->assertStringContainsString('ExampleFramework', (string) $mod[0]->getDeepestModFrame());
        $this->assertSame(Severity::High, $mod[0]->getSeverity());
        $this->assertSame('java.lang.RuntimeException', $mod[0]->getExceptionClass());
    }

    /** DebugFileWatcher NoSuchFile + Kahlua dumps classify as Severity::Noise engine noise. */
    public function testEngineNoiseClassification(): void
    {
        $analysis = $this->analyseFixture('debug-server-b41-exception-minimal.txt');

        $noise = $analysis->getFilteredInsights(EngineNoiseExceptionInformation::class);
        $this->assertNotEmpty($noise);

        foreach ($noise as $insight) {
            $this->assertInstanceOf(EngineNoiseInsightInterface::class, $insight);
            $this->assertSame(Severity::Noise, $insight->getSeverity());
        }

        $classes = array_map(fn($n) => $n->getExceptionClass(), $noise);
        $this->assertContains(
            'java.nio.file.NoSuchFileException',
            $classes,
            'DebugFileWatcher NoSuchFile must be tagged engine noise'
        );

        // Neither engine-noise entry leaks into the Problem (crash) channel.
        $modAttributed = $analysis->getFilteredInsights(LuaModRuntimeProblem::class);
        $noiseClasses = array_map(fn($n) => $n->getExceptionClass(), $noise);
        $this->assertNotContains('java.nio.file.NoSuchFileException', array_map(fn($m) => $m->getExceptionClass(), $modAttributed));
        $this->assertContains('java.nio.file.NoSuchFileException', $noiseClasses);
    }

    /** SEC-002: ANSI/control bytes embedded in a cause line are stripped from causeChain. */
    public function testCauseChainStripsAnsiControlBytes(): void
    {
        $content = implode("\n", [
            '[16-04-26 00:01:20.000] ERROR: General      f:0, t:1776297680000, st:48,648,195,000> GameTime.update> Exception thrown',
            "\tsome.pkg.CustomException: boom\x1b[2Jmore at Foo.bar(Foo.java:1).",
            "\tCaused by: other.pkg.RootException: root\x1b[2Jcause at Baz.qux(Baz.java:2).",
        ]);

        $analysis = $this->analyseString($content);
        $java = $analysis->getFilteredInsights(JavaExceptionProblem::class);
        $this->assertCount(1, $java);

        $causeChain = $java[0]->getCauseChain();
        $this->assertNotNull($causeChain);
        $this->assertStringNotContainsString("\x1b", $causeChain, 'ESC control byte must be stripped (SEC-002)');
        $this->assertStringContainsString('CustomException', $causeChain);
        $this->assertStringContainsString('RootException', $causeChain);
    }

    /** Fingerprint is non-empty, well-formed, and stable across runs. */
    public function testFingerprintStableAndNonEmpty(): void
    {
        $first = $this->analyseFixture('debug-server-b4x-exception-minimal.txt')->getProblems();
        $second = $this->analyseFixture('debug-server-b4x-exception-minimal.txt')->getProblems();

        $this->assertNotEmpty($first);
        $this->assertSameSize($first, $second);

        $firstFingerprints = array_map(fn($p) => $p->getFingerprint(), $first);
        $secondFingerprints = array_map(fn($p) => $p->getFingerprint(), $second);
        sort($firstFingerprints);
        sort($secondFingerprints);

        $this->assertSame($firstFingerprints, $secondFingerprints, 'Fingerprints must be deterministic');
        foreach ($firstFingerprints as $fingerprint) {
            $this->assertMatchesRegularExpression('/^sha256:[0-9a-f]{16}$/', $fingerprint);
        }
    }
}
