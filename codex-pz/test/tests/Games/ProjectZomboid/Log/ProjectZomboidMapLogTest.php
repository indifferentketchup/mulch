<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidMapLog;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\MapPattern;
use PHPUnit\Framework\TestCase;

class ProjectZomboidMapLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/map-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new ProjectZomboidMapLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(10, $log->getEntries());
    }

    public function testFieldsRegexHandlesIntegerCoordinates(): void
    {
        $line = '[16-04-26 18:39:37.028] 76561198000000001 "Player1" added Base.Aerosolbomb at 1000,2000,0.';
        $this->assertSame(1, preg_match(MapPattern::FIELDS, $line, $m));
        $this->assertSame('added', $m['verb']);
        $this->assertSame('Base.Aerosolbomb', $m['object']);
        $this->assertSame('1000', $m['x']);
    }

    public function testFieldsRegexHandlesFloatCoordinatesAndIsoObject(): void
    {
        $line = '[16-04-26 21:20:06.768] 76561198000000002 "Player2" added IsoObject (fencing_damaged_01_124) at 1010.0,2010.0,0.0.';
        $this->assertSame(1, preg_match(MapPattern::FIELDS, $line, $m));
        $this->assertSame('IsoObject (fencing_damaged_01_124)', $m['object']);
        $this->assertSame('1010.0', $m['x']);
    }

    public function testDetectiveDispatchesByContent(): void
    {
        $detective = (new Detective())
            ->setLogFile(new PathLogFile($this->fixturePath()))
            ->addPossibleLogClass(ProjectZomboidMapLog::class);

        $this->assertInstanceOf(ProjectZomboidMapLog::class, $detective->detect());
    }
}
