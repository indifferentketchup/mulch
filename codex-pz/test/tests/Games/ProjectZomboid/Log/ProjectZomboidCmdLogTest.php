<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidCmdLog;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\CmdPattern;
use PHPUnit\Framework\TestCase;

class ProjectZomboidCmdLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/cmd-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new ProjectZomboidCmdLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(10, $log->getEntries());
    }

    public function testFieldsRegexExtractsCommand(): void
    {
        $line = '[16-04-26 16:19:30.812] 76561198000000003 "AdminUser" admin.kickPlayer @ 1020,2020,0.';
        $this->assertSame(1, preg_match(CmdPattern::FIELDS, $line, $m));
        $this->assertSame('AdminUser', $m['player']);
        $this->assertSame('admin.kickPlayer', $m['command']);
    }

    public function testDetectiveDispatchesByContent(): void
    {
        $detective = (new Detective())
            ->setLogFile(new PathLogFile($this->fixturePath()))
            ->addPossibleLogClass(ProjectZomboidCmdLog::class);

        $this->assertInstanceOf(ProjectZomboidCmdLog::class, $detective->detect());
    }
}
