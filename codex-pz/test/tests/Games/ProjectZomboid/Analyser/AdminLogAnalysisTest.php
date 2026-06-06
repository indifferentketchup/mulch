<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analyser;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminAddedItemInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminAddedXpInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminChangedOptionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminGrantedAccessInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminReloadedOptionsInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminTeleportedInformation;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidAdminLog;
use PHPUnit\Framework\TestCase;

class AdminLogAnalysisTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/admin-minimal.txt';
    }

    public function testAnalyseProducesExpectedInsightCounts(): void
    {
        $log = (new ProjectZomboidAdminLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $this->assertCount(2, $analysis->getFilteredInsights(AdminAddedItemInformation::class));
        $this->assertCount(2, $analysis->getFilteredInsights(AdminAddedXpInformation::class));
        $this->assertCount(2, $analysis->getFilteredInsights(AdminGrantedAccessInformation::class));
        $this->assertCount(2, $analysis->getFilteredInsights(AdminChangedOptionInformation::class));
        $this->assertCount(1, $analysis->getFilteredInsights(AdminReloadedOptionsInformation::class));
        $this->assertCount(2, $analysis->getFilteredInsights(AdminTeleportedInformation::class));
    }

    public function testIdenticalAddedItemEventsAreCoalesced(): void
    {
        $log = (new ProjectZomboidAdminLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $shotgunInsight = null;
        foreach ($analysis->getFilteredInsights(AdminAddedItemInformation::class) as $insight) {
            if (str_contains($insight->getValue(), 'Base.ShotgunShells')) {
                $shotgunInsight = $insight;
                break;
            }
        }

        $this->assertNotNull($shotgunInsight);
        $this->assertSame(2, $shotgunInsight->getCounterValue());
    }
}
