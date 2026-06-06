<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidItemLog;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\ItemPattern;
use PHPUnit\Framework\TestCase;

class ProjectZomboidItemLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/item-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new ProjectZomboidItemLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(20, $log->getEntries());
    }

    public function testFieldsRegexExtractsItemAndDelta(): void
    {
        $line = '[16-04-26 19:42:25.223] 76561198000000002 "Player2" inventory +5 1011,2011,0 [Base.Bullets9mm].';
        $this->assertSame(1, preg_match(ItemPattern::FIELDS, $line, $m));
        $this->assertSame('Player2', $m['player']);
        $this->assertSame('inventory', $m['location']);
        $this->assertSame('+5', $m['delta']);
        $this->assertSame('Base.Bullets9mm', $m['item']);
    }

    public function testDetectiveDispatchesByContent(): void
    {
        $detective = (new Detective())
            ->setLogFile(new PathLogFile($this->fixturePath()))
            ->addPossibleLogClass(ProjectZomboidItemLog::class);

        $this->assertInstanceOf(ProjectZomboidItemLog::class, $detective->detect());
    }
}
