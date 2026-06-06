<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analyser;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineNoiseExceptionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\JavaExceptionProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\LuaModRuntimeProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\RequireFailedProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ServerExceptionProblem;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidServerLog;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Phase 6 — CompositeAnalyser + ServerLog wiring.
 *
 * Three correctness points per the plan:
 *  1. setLog() propagation (covered by CompositeAnalyserTest — framework level).
 *  2. No-double-count: the one-producer seam holds end-to-end.
 *  3. End-to-end via getDefaultAnalyser(): StackTrace child is reached.
 */
class ServerLogCompositeTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/';

    // -------------------------------------------------------------------------
    // Correctness point 2: the seam — no double-count
    // -------------------------------------------------------------------------

    /**
     * A combined fixture containing BOTH a single-line warning (require failed,
     * owned by PatternAnalyser) AND a mod-attributed Exception thrown (owned by
     * StackTraceClassificationAnalyser). After the composite wiring:
     *
     *   - RequireFailedProblem comes from the PatternAnalyser child.
     *   - LuaModRuntimeProblem comes from the StackTrace child.
     *   - ServerExceptionProblem count MUST be zero (it was unregistered).
     *   - Each exception entry yields EXACTLY one problem row — no double-count.
     */
    public function testNoDoubleCountSeam(): void
    {
        $content = implode("\n", [
            '[16-04-26 00:00:42.407] LOG  : General      f:0, t:1776297642407, st:48,648,157,585> ' .
                'version=42.16.3 0000000000000000000000000000000000000000 2026-04-08 11:54:01 (ZB) demo=false.',
            '[16-04-26 00:01:19.131] WARN : Lua          f:0, t:1776297679131, st:48,648,194,309> ' .
                'require("/media/mods/SomeMod/media/lua/server/SomeModule.lua") failed.',
            '[16-04-26 00:01:20.000] ERROR: General      f:0, t:1776297680000, st:48,648,195,000> ' .
                'GameTime.update> Exception thrown',
            "\tjava.lang.RuntimeException: Lua runtime error at LuaManager.call(LuaManager.java:99).",
            "\t\tat zombie.GameTime.update(GameTime.java:99)",
            "\tLua((MOD:ExampleMod)).OnTick(ExampleScript.lua:36)",
        ]);

        $log = (new ProjectZomboidServerLog())->setLogFile(new StringLogFile($content));
        $log->parse();
        $analysis = $log->analyse();

        // PatternAnalyser child contributes the require-failed warning.
        $this->assertCount(1, $analysis->getFilteredInsights(RequireFailedProblem::class),
            'RequireFailedProblem must come from PatternAnalyser child');

        // StackTrace child contributes the mod-attributed exception.
        $modProblems = $analysis->getFilteredInsights(LuaModRuntimeProblem::class);
        $this->assertCount(1, $modProblems,
            'Exception entry must yield exactly one LuaModRuntimeProblem (no double-count)');

        // ServerExceptionProblem is unregistered — must not appear.
        $this->assertCount(0, $analysis->getFilteredInsights(ServerExceptionProblem::class),
            'ServerExceptionProblem must not appear after it was unregistered from PatternAnalyser');

        // Total problem count: 1 require-failed + 1 mod-runtime.
        $this->assertCount(2, $analysis->getProblems(),
            'Exactly 2 problem rows: one require-failed, one mod-runtime exception');
    }

    // -------------------------------------------------------------------------
    // Correctness point 3: end-to-end via getDefaultAnalyser()
    // -------------------------------------------------------------------------

    /**
     * B41 exception fixture run through getDefaultAnalyser() (the composite).
     * Proves the StackTrace child is wired and reached.
     */
    public function testGetDefaultAnalyserWiresStackTraceChildB41(): void
    {
        $log = (new ProjectZomboidServerLog())
            ->setLogFile(new PathLogFile(self::FIXTURE_DIR . 'debug-server-b41-exception-minimal.txt'));
        $log->parse();
        $analysis = $log->analyse();

        $modProblems = $analysis->getFilteredInsights(LuaModRuntimeProblem::class);
        $noise = $analysis->getFilteredInsights(EngineNoiseExceptionInformation::class);

        $this->assertNotEmpty(
            $modProblems,
            'B41 exception fixture must produce LuaModRuntimeProblem via getDefaultAnalyser()'
        );
        $this->assertNotEmpty(
            $noise,
            'B41 exception fixture must produce EngineNoiseExceptionInformation via getDefaultAnalyser()'
        );
    }

    /**
     * B4x exception fixture run through getDefaultAnalyser() (the composite).
     * Proves the StackTrace child handles the multi-entry forward-walk path.
     */
    public function testGetDefaultAnalyserWiresStackTraceChildB4x(): void
    {
        $log = (new ProjectZomboidServerLog())
            ->setLogFile(new PathLogFile(self::FIXTURE_DIR . 'debug-server-b4x-exception-minimal.txt'));
        $log->parse();
        $analysis = $log->analyse();

        $java = $analysis->getFilteredInsights(JavaExceptionProblem::class);
        $mod = $analysis->getFilteredInsights(LuaModRuntimeProblem::class);

        $this->assertGreaterThanOrEqual(
            1,
            count($java) + count($mod),
            'B4x exception fixture must yield ≥1 exception-type Problem via getDefaultAnalyser()'
        );
    }

    // -------------------------------------------------------------------------
    // Bench (informational — WARN-009, hardware-dependent, not a hard gate)
    // -------------------------------------------------------------------------

    /**
     * Generates a ~100k-line synthetic DebugLog-server mixing B41/B42/B4x at
     * ~5% error density, then runs parse() + analyse() through getDefaultAnalyser().
     *
     * Target is <2s but we do NOT fail the suite on the threshold — this is
     * informational only. The assertion is merely that the run completes and
     * emits at least one insight.
     *
     * @group bench
     */
    public function testBenchLargeLogCompletesInReasonableTime(): void
    {
        $lines = [];

        // Header
        $lines[] = '[16-04-26 00:00:42.407] LOG  : General      f:0, t:1776297642407, st:48,648,157,585> ' .
            'version=42.16.3 0000000000000000000000000000000000000000 2026-04-08 11:54:01 (ZB) demo=false.';

        $errorEvery = 20; // ~5% error density
        for ($i = 1; $i < 100_000; $i++) {
            $ts = sprintf('[16-04-26 00:%02d:%02d.000]', (int) ($i / 60) % 60, $i % 60);
            $st = 48_648_157_585 + $i;
            if ($i % $errorEvery === 0) {
                $lines[] = "$ts ERROR: General      f:0, t:1776297642407, st:$st> GameTime.update> Exception thrown";
                $lines[] = "\tjava.lang.RuntimeException: test error $i at SomeClass.method(SomeClass.java:$i).";
                $lines[] = "\t\tat zombie.GameTime.update(GameTime.java:$i)";
            } else {
                $lines[] = "$ts LOG  : General      f:0, t:1776297642407, st:$st> tick $i.";
            }
        }

        $content = implode("\n", $lines);

        $start = microtime(true);
        $log = (new ProjectZomboidServerLog())->setLogFile(new StringLogFile($content));
        $log->parse();
        $analysis = $log->analyse();
        $elapsed = microtime(true) - $start;

        // Informational: print timing but do not gate on it (WARN-009).
        fwrite(STDERR, sprintf("\n[bench] parse+analyse 100k lines: %.3fs\n", $elapsed));

        $this->assertNotEmpty($analysis->getInsights(), 'Bench run must produce at least one insight');

        if ($elapsed >= 2.0) {
            $this->markTestIncomplete(sprintf(
                '[bench] Completed in %.3fs (target <2s) — informational, not a hard gate',
                $elapsed
            ));
        }
    }
}
