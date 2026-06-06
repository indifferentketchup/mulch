<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analyser;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\PvpDamageInformation;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidPvpLog;
use PHPUnit\Framework\TestCase;

class PvpLogAnalysisTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/pvp-minimal.txt';
    }

    public function testAnalyseProducesOnlyRealPvpInsights(): void
    {
        $log = (new ProjectZomboidPvpLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $insights = $analysis->getFilteredInsights(PvpDamageInformation::class);

        $values = array_map(fn($i) => $i->getValue(), $insights);
        sort($values);

        $this->assertSame(
            [
                'AdminUser hit Player1 with Hunting Knife',
                'Player1 hit Player2 with Bare Hands',
                'Player1 hit Player2 with Tire Iron (Worn)',
            ],
            $values
        );
    }

    public function testZombieAndZeroDamageAreFilteredOut(): void
    {
        $log = (new ProjectZomboidPvpLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $insights = $analysis->getFilteredInsights(PvpDamageInformation::class);

        foreach ($insights as $insight) {
            $this->assertStringNotContainsString('zombie', $insight->getValue());
            $this->assertStringNotContainsString('vehicle', $insight->getValue());
        }
    }
}
