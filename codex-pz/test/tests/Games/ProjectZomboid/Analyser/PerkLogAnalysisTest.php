<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analyser;

use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\SkillProgressionAnomalyAnalyser;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\SkillProgressionAnomalyProblem;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidPerkLog;
use PHPUnit\Framework\TestCase;

class PerkLogAnalysisTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/perk-minimal.txt';
    }

    public function testFlagsSkillsThatExceedDeltaThreshold(): void
    {
        $log = (new ProjectZomboidPerkLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $problems = $analysis->getFilteredInsights(SkillProgressionAnomalyProblem::class);

        $skills = array_map(fn($p) => $p->getSkill(), $problems);
        sort($skills);

        $this->assertSame(['Fitness', 'Strength'], $skills);

        foreach ($problems as $problem) {
            $this->assertSame('76561198000000004', $problem->getSteamId());
            $this->assertSame('PlayerSuspect', $problem->getPlayer());
        }
    }

    public function testDeltaAtThresholdDoesNotTrigger(): void
    {
        $log = (new ProjectZomboidPerkLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $problems = $analysis->getFilteredInsights(SkillProgressionAnomalyProblem::class);
        foreach ($problems as $problem) {
            $this->assertNotSame('Maintenance', $problem->getSkill());
        }
    }

    public function testSinglePlayerWithOneSnapshotProducesNoProblem(): void
    {
        $log = (new ProjectZomboidPerkLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $problems = $analysis->getFilteredInsights(SkillProgressionAnomalyProblem::class);
        foreach ($problems as $problem) {
            $this->assertNotSame('76561198000000001', $problem->getSteamId());
            $this->assertNotSame('76561198000000002', $problem->getSteamId());
        }
    }

    public function testThresholdConstantIsDocumentedAndPositive(): void
    {
        $this->assertGreaterThan(0, SkillProgressionAnomalyAnalyser::THRESHOLD_DELTA);
    }
}
