<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\Level;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidBurdJournalsLog;
use PHPUnit\Framework\TestCase;

class ProjectZomboidBurdJournalsLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/burd-journals-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new ProjectZomboidBurdJournalsLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(5, $log->getEntries());
    }

    public function testLevelAndPrefixAreParsed(): void
    {
        $log = (new ProjectZomboidBurdJournalsLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $first = $log->getEntries()[0];
        $this->assertSame(Level::WARNING, $first->getLevel());
        $this->assertSame('BurdJournals', $first->getPrefix());
    }

    public function testDetectiveDispatchesByContent(): void
    {
        $detective = (new Detective())
            ->setLogFile(new PathLogFile($this->fixturePath()))
            ->addPossibleLogClass(ProjectZomboidBurdJournalsLog::class);

        $this->assertInstanceOf(ProjectZomboidBurdJournalsLog::class, $detective->detect());
    }
}
