<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Detective;

use IndifferentKetchup\CodexPz\Detective\FilenameDetector;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use PHPUnit\Framework\TestCase;

class FilenameDetectorTest extends TestCase
{
    public function testReturnsWeightWhenPatternMatchesPath(): void
    {
        $detector = (new FilenameDetector())
            ->setPattern('/simple\.log$/')
            ->setWeight(0.9);
        $detector->setLogFile(new PathLogFile(__DIR__ . "/../../data/simple.log"));

        $this->assertSame(0.9, $detector->detect());
    }

    public function testReturnsFalseWhenPatternDoesNotMatch(): void
    {
        $detector = (new FilenameDetector())
            ->setPattern('/notthere\.txt$/');
        $detector->setLogFile(new PathLogFile(__DIR__ . "/../../data/simple.log"));

        $this->assertFalse($detector->detect());
    }

    public function testReturnsFalseWhenLogFileHasNoPath(): void
    {
        $detector = (new FilenameDetector())
            ->setPattern('/anything/');
        $detector->setLogFile(new StringLogFile("content"));

        $this->assertFalse($detector->detect());
    }

    public function testReturnsFalseWhenPatternUnset(): void
    {
        $detector = new FilenameDetector();
        $detector->setLogFile(new PathLogFile(__DIR__ . "/../../data/simple.log"));

        $this->assertFalse($detector->detect());
    }
}
