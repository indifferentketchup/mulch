<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Log\File;

use IndifferentKetchup\CodexPz\Log\File\StreamLogFile;
use PHPUnit\Framework\TestCase;

class StreamLogFileTest extends TestCase
{
    public function testGetContent(): void
    {
        $path = __DIR__ . "/../../../data/simple.log";
        $streamResource = fopen($path, 'r');

        $logFile = new StreamLogFile($streamResource);
        $this->assertStringEqualsFile($path, $logFile->getContent());
    }
}
