<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analyser;

use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\ItemDuplicationAnalyser;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ItemDuplicationProblem;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidItemLog;
use PHPUnit\Framework\TestCase;

class ItemLogAnalysisTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/item-minimal.txt';
    }

    public function testFlagsBurstOfSameItemAboveThreshold(): void
    {
        $log = (new ProjectZomboidItemLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $problems = $analysis->getFilteredInsights(ItemDuplicationProblem::class);
        $this->assertCount(1, $problems);

        $problem = $problems[0];
        $this->assertSame('76561198000000003', $problem->getSteamId());
        $this->assertSame('AdminUser', $problem->getPlayer());
        $this->assertSame('Base.Bullets9mm', $problem->getItem());
        $this->assertSame(6, $problem->getEventCount());
    }

    public function testDoesNotFlagSubThresholdGroup(): void
    {
        $log = (new ProjectZomboidItemLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $problems = $analysis->getFilteredInsights(ItemDuplicationProblem::class);
        foreach ($problems as $problem) {
            $this->assertNotSame('Base.Plank', $problem->getItem());
        }
    }

    public function testThresholdConstantsAreDocumentedAndPositive(): void
    {
        $this->assertGreaterThan(0, ItemDuplicationAnalyser::THRESHOLD_COUNT);
        $this->assertGreaterThan(0, ItemDuplicationAnalyser::THRESHOLD_WINDOW_SECONDS);
    }
}
