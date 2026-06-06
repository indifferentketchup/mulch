<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Log\File;

use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use PHPUnit\Framework\TestCase;

class PathLogFileTest extends TestCase
{
    public function testGetContent(): void
    {
        $path = __DIR__ . "/../../../data/simple.log";
        $logFile = new PathLogFile($path);

        $this->assertStringEqualsFile($path, $logFile->getContent());
    }

    public function testGetPathReturnsConstructorArgument(): void
    {
        $path = __DIR__ . "/../../../data/simple.log";
        $logFile = new PathLogFile($path);

        $this->assertSame($path, $logFile->getPath());
    }
}
