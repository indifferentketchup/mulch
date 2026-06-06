<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidPerkLog;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\PerkPattern;
use PHPUnit\Framework\TestCase;

class ProjectZomboidPerkLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/perk-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new ProjectZomboidPerkLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(10, $log->getEntries());
    }

    public function testFieldsRegexHandlesEventRow(): void
    {
        $line = '[16-04-26 18:29:08.171] [76561198000000001][Player1][1000,2000,1][Login][Hours Survived: 100].';
        $this->assertSame(1, preg_match(PerkPattern::FIELDS, $line, $m));
        $this->assertSame('Player1', $m['player']);
        $this->assertSame('Login', $m['event']);
        $this->assertSame('100', $m['hours']);
    }

    public function testFieldsRegexHandlesPerksRow(): void
    {
        $line = '[16-04-26 18:30:02.500] [76561198000000003][AdminUser][1020,2020,0][Logout][Hours Survived: 75].';
        $this->assertSame(1, preg_match(PerkPattern::FIELDS, $line, $m));
        $this->assertSame('Logout', $m['event']);
    }

    public function testPerkPairRegexExtractsSkillsFromBracketedList(): void
    {
        $bracket = 'Cooking=5, Fitness=6, Strength=7';
        $count = preg_match_all(PerkPattern::PERK_PAIR, $bracket, $matches, PREG_SET_ORDER);
        $this->assertSame(3, $count);
        $this->assertSame('Cooking', $matches[0]['skill']);
        $this->assertSame('5', $matches[0]['level']);
    }

    public function testDetectiveDispatchesByContent(): void
    {
        $detective = (new Detective())
            ->setLogFile(new PathLogFile($this->fixturePath()))
            ->addPossibleLogClass(ProjectZomboidPerkLog::class);

        $this->assertInstanceOf(ProjectZomboidPerkLog::class, $detective->detect());
    }
}
