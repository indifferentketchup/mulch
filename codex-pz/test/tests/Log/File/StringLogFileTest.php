<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Log\File;

use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use PHPUnit\Framework\TestCase;

class StringLogFileTest extends TestCase
{
    public function testGetContent(): void
    {
        $content = uniqid();
        $logFile = new StringLogFile($content);

        $this->assertEquals($content, $logFile->getContent());
    }

    public function testGetPathReturnsNull(): void
    {
        $logFile = new StringLogFile("anything");

        $this->assertNull($logFile->getPath());
    }
}
