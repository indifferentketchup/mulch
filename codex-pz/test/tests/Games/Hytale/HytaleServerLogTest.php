<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\Hytale;

use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\Hytale\HytaleServerLog;
use IndifferentKetchup\CodexPz\Log\Level;
use PHPUnit\Framework\TestCase;

class HytaleServerLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../src/Games/Hytale/fixtures/server-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new HytaleServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(6, $log->getEntries());
    }

    public function testFirstEntryHasInfoLevelAndTimestamp(): void
    {
        $log = (new HytaleServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $first = $log->getEntries()[0];
        $this->assertSame(Level::INFO, $first->getLevel());
        $this->assertNotNull($first->getTime());
    }

    public function testWarnTokenMapsToWarningLevel(): void
    {
        $log = (new HytaleServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $warn = $log->getEntries()[3];
        $this->assertSame(Level::WARNING, $warn->getLevel());
    }
}
