<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidClientActionLog;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\ClientActionPattern;
use PHPUnit\Framework\TestCase;

class ProjectZomboidClientActionLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/client-action-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new ProjectZomboidClientActionLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(10, $log->getEntries());
    }

    public function testFieldsRegexExtractsStructuredData(): void
    {
        $line = "[29-04-26 21:41:25.084] [76561198000000001][ISEnterVehicle][Player1][1000,2000,0][Van_LectroMax].";
        $this->assertSame(1, preg_match(ClientActionPattern::FIELDS, $line, $m));
        $this->assertSame('76561198000000001', $m['steamid']);
        $this->assertSame('ISEnterVehicle', $m['action']);
        $this->assertSame('Player1', $m['player']);
        $this->assertSame('Van_LectroMax', $m['param']);
    }

    public function testDetectiveDispatchesByContent(): void
    {
        $detective = (new Detective())
            ->setLogFile(new PathLogFile($this->fixturePath()))
            ->addPossibleLogClass(ProjectZomboidClientActionLog::class);

        $this->assertInstanceOf(ProjectZomboidClientActionLog::class, $detective->detect());
    }
}
