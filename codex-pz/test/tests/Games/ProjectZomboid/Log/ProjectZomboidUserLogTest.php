<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidUserLog;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\UserPattern;
use PHPUnit\Framework\TestCase;

class ProjectZomboidUserLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/user-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new ProjectZomboidUserLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(9, $log->getEntries());
    }

    public function testConnectionRegexExtractsAddVariant(): void
    {
        $line = '[29-04-26 18:35:41.512] Connection add index=0 guid=144118788000000001 id=null.';
        $this->assertSame(1, preg_match(UserPattern::CONNECTION, $line, $m));
        $this->assertSame('add', $m['action']);
        $this->assertSame('null', $m['id']);
    }

    public function testConnectionRegexExtractsDisconnectVariant(): void
    {
        $line = '[29-04-26 18:39:23.923] Connection disconnect index=0 guid=144118788000000001 id=76561198000000001.';
        $this->assertSame(1, preg_match(UserPattern::CONNECTION, $line, $m));
        $this->assertSame('disconnect', $m['action']);
        $this->assertSame('76561198000000001', $m['id']);
    }

    public function testPlayerEventRegexExtracts(): void
    {
        $line = '[29-04-26 18:35:42.802] 76561198000000001 "Player1" attempting to join.';
        $this->assertSame(1, preg_match(UserPattern::PLAYER_EVENT, $line, $m));
        $this->assertSame('Player1', $m['player']);
        $this->assertSame('attempting to join', $m['event']);
    }

    public function testDetectiveDispatchesByContent(): void
    {
        $detective = (new Detective())
            ->setLogFile(new PathLogFile($this->fixturePath()))
            ->addPossibleLogClass(ProjectZomboidUserLog::class);

        $this->assertInstanceOf(ProjectZomboidUserLog::class, $detective->detect());
    }
}
