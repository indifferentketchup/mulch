<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\Level;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidChatLog;
use PHPUnit\Framework\TestCase;

class ProjectZomboidChatLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/chat-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new ProjectZomboidChatLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(13, $log->getEntries());
    }

    public function testInfoBracketIsParsedAsLevel(): void
    {
        $log = (new ProjectZomboidChatLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $first = $log->getEntries()[0];
        $this->assertSame(Level::INFO, $first->getLevel());
        $this->assertNotNull($first->getTime());
    }

    public function testServerAlertLinesParseWithoutLevel(): void
    {
        $log = (new ProjectZomboidChatLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $alert = $log->getEntries()[10];
        $this->assertStringContainsString('Server alert message', (string) $alert);
    }

    public function testDetectiveDispatchesByContent(): void
    {
        $detective = (new Detective())
            ->setLogFile(new PathLogFile($this->fixturePath()))
            ->addPossibleLogClass(ProjectZomboidChatLog::class);

        $this->assertInstanceOf(ProjectZomboidChatLog::class, $detective->detect());
    }
}
