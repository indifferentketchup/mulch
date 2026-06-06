<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\Hytale;

use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\Hytale\HytaleClientLog;
use IndifferentKetchup\CodexPz\Log\Level;
use PHPUnit\Framework\TestCase;

class HytaleClientLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../src/Games/Hytale/fixtures/client-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new HytaleClientLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(5, $log->getEntries());
    }

    public function testFirstEntryHasInfoLevelAndTimestamp(): void
    {
        $log = (new HytaleClientLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $first = $log->getEntries()[0];
        $this->assertSame(Level::INFO, $first->getLevel());
        $this->assertNotNull($first->getTime());
    }

    public function testErrorTokenMapsToErrorLevel(): void
    {
        $log = (new HytaleClientLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $err = $log->getEntries()[3];
        $this->assertSame(Level::ERROR, $err->getLevel());
    }
}
