<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Analyser;

use IndifferentKetchup\CodexPz\Analysis\Analysis;
use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineVersionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ModLoadInformation;
use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\CompositeAnalyser;
use IndifferentKetchup\CodexPz\Analyser\PatternAnalyser;
use IndifferentKetchup\CodexPz\Log\AnalysableLogInterface;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidServerLog;
use PHPUnit\Framework\TestCase;

class CompositeAnalyserTest extends TestCase
{
    private const string VERSION_LINE =
        '[16-04-26 00:00:42.407] LOG  : General      f:0, t:1776297642407, st:48,648,157,585> ' .
        'version=42.16.3 0000000000000000000000000000000000000000 2026-04-08 11:54:01 (ZB) demo=false.';

    private const string MOD_LINE =
        '[16-04-26 00:01:19.131] LOG  : Mod          f:0, t:1776297679131, st:48,648,194,309> loading example_mod_alpha.';

    private function parsedLog(string ...$lines): \IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidServerLog
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new StringLogFile(implode("\n", $lines)));
        $log->parse();
        return $log;
    }

    /**
     * WARN-005 correctness point 1: setLog() MUST propagate to all children.
     *
     * We capture the received log on two spy children via a public property on
     * anonymous-class instances. Without propagation, each child's $this->log
     * is null, and calling analyse() would throw a TypeError when PatternAnalyser
     * or StackTraceClassificationAnalyser iterates "foreach ($this->log ...)".
     */
    public function testSetLogPropagatestoAllChildren(): void
    {
        $child1 = new class implements AnalyserInterface {
            public ?AnalysableLogInterface $receivedLog = null;

            public function setLog(AnalysableLogInterface $log): static
            {
                $this->receivedLog = $log;
                return $this;
            }

            public function analyse(): AnalysisInterface
            {
                return new Analysis();
            }
        };

        $child2 = new class implements AnalyserInterface {
            public ?AnalysableLogInterface $receivedLog = null;

            public function setLog(AnalysableLogInterface $log): static
            {
                $this->receivedLog = $log;
                return $this;
            }

            public function analyse(): AnalysisInterface
            {
                return new Analysis();
            }
        };

        $log = $this->parsedLog(self::VERSION_LINE);
        $composite = new CompositeAnalyser($child1, $child2);
        $composite->setLog($log);

        $this->assertSame($log, $child1->receivedLog, 'child1 must receive the log via setLog()');
        $this->assertSame($log, $child2->receivedLog, 'child2 must receive the log via setLog()');
    }

    /** Insights from all children are merged into a single Analysis. */
    public function testAnalyseMergesInsightsFromAllChildren(): void
    {
        $child1 = (new PatternAnalyser())->addPossibleInsightClass(EngineVersionInformation::class);
        $child2 = (new PatternAnalyser())->addPossibleInsightClass(ModLoadInformation::class);

        $log = $this->parsedLog(self::VERSION_LINE, self::MOD_LINE);

        $composite = new CompositeAnalyser($child1, $child2);
        $composite->setLog($log);
        $analysis = $composite->analyse();

        $this->assertCount(1, $analysis->getFilteredInsights(EngineVersionInformation::class));
        $this->assertCount(1, $analysis->getFilteredInsights(ModLoadInformation::class));
    }

    /** Equal insights from different children are coalesced (not duplicated). */
    public function testAnalysisCoalescesEqualInsightsAcrossChildren(): void
    {
        // Both children see the same ModLoadInformation entry.
        $child1 = (new PatternAnalyser())->addPossibleInsightClass(ModLoadInformation::class);
        $child2 = (new PatternAnalyser())->addPossibleInsightClass(ModLoadInformation::class);

        $log = $this->parsedLog(self::MOD_LINE);

        $composite = new CompositeAnalyser($child1, $child2);
        $composite->setLog($log);
        $analysis = $composite->analyse();

        $this->assertCount(1, $analysis->getFilteredInsights(ModLoadInformation::class),
            'Equal insights from two children should coalesce, not duplicate');
    }

    /** An empty CompositeAnalyser returns an empty Analysis without error. */
    public function testEmptyCompositeReturnsEmptyAnalysis(): void
    {
        $log = $this->parsedLog(self::VERSION_LINE);
        $composite = new CompositeAnalyser();
        $composite->setLog($log);
        $analysis = $composite->analyse();

        $this->assertCount(0, $analysis->getInsights());
    }
}
