<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analyser;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\FunctionMissingProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\JavaExceptionProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\RequireFailedProblem;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidServerLog;
use PHPUnit\Framework\TestCase;

/**
 * Phase 7b regression: WarningPatternAnalyser structurally enforces the
 * one-producer seam (WARN-004/T2). A Java exception entry whose continuation
 * body contains warning-family text must not produce Warning Insights alongside
 * the StackTrace Insight for the same entry.
 */
class ExceptionSeamEnforcementTest extends TestCase
{
    private function makeLog(): ProjectZomboidServerLog
    {
        $content = implode("\n", [
            // Entry 1: "Exception thrown" entry whose tab-continuation body contains
            // BOTH a REQUIRE_FAILED match and a FUNCTION_MISSING match — the exact
            // text that triggers the double-count bug if the seam is not enforced.
            '[16-04-26 00:01:20.000] ERROR: General      f:0, t:1776297680000, st:48,648,195,000> ' .
                'LuaManager.call> Exception thrown',
            "\tjava.lang.RuntimeException: Lua runtime error: require(\"media/lua/Foo/Bar.lua\") failed" .
                ' at LuaManager.call(LuaManager.java:99).',
            "\t\tno such function \"SomeFunc\"",
            "\t\tat zombie.LuaManager.call(LuaManager.java:99)",
            // Entry 2: Genuine single-line require-failed WARN owned by WarningPatternAnalyser.
            '[16-04-26 00:01:21.000] WARN : Lua          f:0, t:1776297681000, st:48,648,196,000> ' .
                'require("media/lua/Baz/Qux.lua") failed.',
        ]);

        $log = (new ProjectZomboidServerLog())->setLogFile(new StringLogFile($content));
        $log->parse();
        return $log;
    }

    /**
     * The exception body contains REQUIRE_FAILED and FUNCTION_MISSING text.
     * WarningPatternAnalyser must skip the exception entry:
     *   - FunctionMissingProblem count = 0  (text only appears in exception body)
     *   - RequireFailedProblem count = 1    (separate genuine WARN entry only)
     *   - JavaExceptionProblem count = 1    (StackTrace child owns the exception entry)
     */
    public function testExceptionEntryBodyDoesNotProduceWarningInsights(): void
    {
        $analysis = $this->makeLog()->analyse();

        $this->assertCount(
            0,
            $analysis->getFilteredInsights(FunctionMissingProblem::class),
            'FunctionMissingProblem must not be emitted for text inside an Exception-thrown entry'
        );

        $this->assertCount(
            1,
            $analysis->getFilteredInsights(RequireFailedProblem::class),
            'RequireFailedProblem count must be 1 — separate genuine WARN entry only; exception body skipped'
        );

        $this->assertCount(
            1,
            $analysis->getFilteredInsights(JavaExceptionProblem::class),
            'Exception entry must yield exactly one JavaExceptionProblem from StackTraceClassificationAnalyser'
        );
    }

    /**
     * Prove the skip is scoped to exception-shaped entries only: the genuine
     * single-line require-failed WARN entry is still detected.
     */
    public function testGenuineWarningEntryIsStillDetected(): void
    {
        $analysis = $this->makeLog()->analyse();

        $this->assertNotEmpty(
            $analysis->getFilteredInsights(RequireFailedProblem::class),
            'Genuine require-failed WARN entry must still produce a RequireFailedProblem'
        );
    }
}
