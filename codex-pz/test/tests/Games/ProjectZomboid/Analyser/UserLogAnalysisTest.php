<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analyser;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ConnectionFailureProblem;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidUserLog;
use PHPUnit\Framework\TestCase;

class UserLogAnalysisTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/user-minimal.txt';
    }

    public function testFlagsPlayerWithUnmatchedAttempts(): void
    {
        $log = (new ProjectZomboidUserLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $problems = $analysis->getFilteredInsights(ConnectionFailureProblem::class);
        $this->assertCount(1, $problems);

        $problem = $problems[0];
        $this->assertSame('76561198000000001', $problem->getSteamId());
        $this->assertSame('Player1', $problem->getPlayer());
        $this->assertSame(1, $problem->getUnmatchedAttempts());
    }

    public function testDoesNotFlagPlayerWithMatchedAttempts(): void
    {
        $log = (new ProjectZomboidUserLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $problems = $analysis->getFilteredInsights(ConnectionFailureProblem::class);
        foreach ($problems as $problem) {
            $this->assertNotSame('76561198000000002', $problem->getSteamId());
        }
    }
}
