<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidPvpLog;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\PvpPattern;
use PHPUnit\Framework\TestCase;

class ProjectZomboidPvpLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/pvp-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new ProjectZomboidPvpLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(10, $log->getEntries());
    }

    public function testSubsystemIsCapturedAsPrefix(): void
    {
        $log = (new ProjectZomboidPvpLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $entries = $log->getEntries();
        $this->assertSame('Safety', $entries[0]->getPrefix());
        $this->assertSame('Combat', $entries[5]->getPrefix());
    }

    public function testCombatRegexExtractsRealPvpDamage(): void
    {
        $line = 'Combat: "Player1" (1005,2005,0) hit "Player2" (1006,2005,0) weapon="Tire Iron (Worn)" damage=0.112317.';
        $this->assertSame(1, preg_match(PvpPattern::COMBAT, $line, $m));
        $this->assertSame('Player1', $m['attacker']);
        $this->assertSame('Player2', $m['victim']);
        $this->assertSame('Tire Iron (Worn)', $m['weapon']);
        $this->assertSame('0.112317', $m['damage']);
    }

    public function testCombatRegexHandlesNegativeZ(): void
    {
        $line = 'Combat: "AdminUser" (1020,2020,-1) hit "Player1" (1020,2020,-1) weapon="Hunting Knife" damage=0.350000.';
        $this->assertSame(1, preg_match(PvpPattern::COMBAT, $line, $m));
        $this->assertSame('-1', $m['az']);
    }

    public function testSafetyRegexExtracts(): void
    {
        $line = 'Safety: "Player1" (1000,2000,0) restore true.';
        $this->assertSame(1, preg_match(PvpPattern::SAFETY, $line, $m));
        $this->assertSame('Player1', $m['player']);
        $this->assertSame('restore', $m['verb']);
    }

    public function testDetectiveDispatchesByContent(): void
    {
        $detective = (new Detective())
            ->setLogFile(new PathLogFile($this->fixturePath()))
            ->addPossibleLogClass(ProjectZomboidPvpLog::class);

        $this->assertInstanceOf(ProjectZomboidPvpLog::class, $detective->detect());
    }
}
