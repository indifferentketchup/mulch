<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Analyser;

use IndifferentKetchup\CodexPz\Analyser\CompositeAnalyser;
use IndifferentKetchup\CodexPz\Analyser\NoiseGate;
use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\ErrorContextAnalyser;
use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\StackTraceClassificationAnalyser;
use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\WarningPatternAnalyser;
use IndifferentKetchup\CodexPz\Analysis\Analysis;
use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;
use IndifferentKetchup\CodexPz\Analysis\Attribution;
use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Kind;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Log\EntryInterface;
use IndifferentKetchup\CodexPz\Log\Level;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidServerLog;
use PHPUnit\Framework\TestCase;

class NoiseGateTest extends TestCase
{
    private function mockInsight(Kind $kind, Attribution $attribution, int $count, ?Level $level = null, ?Severity $severity = null): InsightInterface
    {
        return new class ($kind, $attribution, $count, $level, $severity) implements InsightInterface, SeverityAwareInsightInterface {
            private Kind $kind;
            private Attribution $attribution;
            private int $count;
            private ?Level $level;
            private ?Severity $severity;
            private ?bool $gated = null;

            public function __construct(Kind $kind, Attribution $attribution, int $count, ?Level $level, ?Severity $severity)
            {
                $this->kind = $kind;
                $this->attribution = $attribution;
                $this->count = $count;
                $this->level = $level;
                $this->severity = $severity;
            }

            public function getKind(): Kind { return $this->kind; }
            public function setKind(Kind $kind): static { return $this; }
            public function getAttribution(): Attribution { return $this->attribution; }
            public function setAttribution(Attribution $attribution): static { return $this; }
            public function getRankScore(): int { return 0; }
            public function isGated(): bool { return $this->gated ?? false; }
            public function setGated(?bool $gated): static { $this->gated = $gated; return $this; }
            public function getSeverity(): Severity { return $this->severity ?? Severity::Low; }
            public function getMessage(): string { return 'test-' . $this->kind->value; }
            public function __toString(): string { return $this->getMessage(); }
            public function setEntry(EntryInterface $entry): static { return $this; }
            public function getEntry(): ?EntryInterface { return null; }
            public function isEqual(InsightInterface $insight): bool { return false; }
            public function increaseCounter(): static { return $this; }
            public function getCounterValue(): int { return $this->count; }
            public function getFingerprint(): string { return 'fp-' . $this->kind->value; }
            public function setAnalysis(AnalysisInterface $analysis): static { return $this; }
            public function getAnalysis(): ?AnalysisInterface { return null; }
            public function jsonSerialize(): array { return []; }
        };
    }

    public function testRuntimeUnattributedGated(): void
    {
        $insight = $this->mockInsight(Kind::Runtime, Attribution::Unattributed, 1);
        $stack = new StackTraceClassificationAnalyser();
        $analysis = new Analysis();
        $analysis->addInsight($insight);
        $this->assertTrue($stack->isInsightGated($insight, $analysis),
            'Unattributed + Runtime must be gated');
    }

    public function testEngineNoiseGated(): void
    {
        $insight = $this->mockInsight(Kind::EngineNoise, Attribution::Unattributed, 1);
        $stack = new StackTraceClassificationAnalyser();
        $analysis = new Analysis();
        $analysis->addInsight($insight);
        $this->assertTrue($stack->isInsightGated($insight, $analysis),
            'EngineNoise kind must be gated');
    }

    public function testJavaExceptionOver50Gated(): void
    {
        $insight = $this->mockInsight(Kind::JavaException, Attribution::Unattributed, 51);
        $stack = new StackTraceClassificationAnalyser();
        $analysis = new Analysis();
        $analysis->addInsight($insight);
        $this->assertTrue($stack->isInsightGated($insight, $analysis),
            'Unattributed JavaException with >50 occurrences must be gated');
    }

    public function testJavaExceptionUnder50NotGated(): void
    {
        $insight = $this->mockInsight(Kind::JavaException, Attribution::Unattributed, 10);
        $stack = new StackTraceClassificationAnalyser();
        $analysis = new Analysis();
        $analysis->addInsight($insight);
        $this->assertFalse($stack->isInsightGated($insight, $analysis),
            'Unattributed JavaException with <=50 occurrences must not be gated');
    }

    public function testDirectAttributedNeverGated(): void
    {
        $insight = $this->mockInsight(Kind::EngineNoise, Attribution::Attributed, 200);
        $stack = new StackTraceClassificationAnalyser();
        $analysis = new Analysis();
        $analysis->addInsight($insight);
        $this->assertFalse($stack->isInsightGated($insight, $analysis),
            'Attributed insights must never be gated regardless of kind or count');
    }

    public function testCompositeAnalyserPostProcessRunsHook(): void
    {
        $spy = false;
        $hookAnalyser = new class extends \IndifferentKetchup\CodexPz\Analyser\Analyser {
            public bool $hookRan = false;

            public function analyse(): AnalysisInterface
            {
                return new Analysis();
            }

            public function postProcessAnalysis(AnalysisInterface $analysis): AnalysisInterface
            {
                $this->hookRan = true;
                return $analysis;
            }
        };

        $composite = new CompositeAnalyser($hookAnalyser);
        $log = (new ProjectZomboidServerLog())
            ->setLogFile(new \IndifferentKetchup\CodexPz\Log\File\StringLogFile(
                '[16-04-26 00:00:42.407] LOG  : General      f:0, t:1776297642407, st:48,648,157,585> version=42.16.3 hash 2026-04-08 11:54:01 (ZB) demo=false.'
            ));
        $log->parse();
        $composite->setLog($log);
        $composite->analyse();

        $this->assertTrue($hookAnalyser->hookRan,
            'CompositeAnalyser must call postProcessAnalysis on each child');
    }

    public function testDefaultServerLogIncludesErrorContextAnalyser(): void
    {
        $analyser = ProjectZomboidServerLog::getDefaultAnalyser();
        $this->assertInstanceOf(CompositeAnalyser::class, $analyser,
            'Default analyser must be a CompositeAnalyser');

        $ref = new \ReflectionClass($analyser);
        $prop = $ref->getProperty('children');
        $children = $prop->getValue($analyser);

        $hasErrorContext = false;
        foreach ($children as $child) {
            if ($child instanceof ErrorContextAnalyser) {
                $hasErrorContext = true;
                break;
            }
        }
        $this->assertTrue($hasErrorContext,
            'Default server-log composite must include ErrorContextAnalyser');
    }
}
