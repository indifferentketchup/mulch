<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\Minecraft;

use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\Level;
use IndifferentKetchup\CodexPz\Log\Minecraft\MinecraftLog;
use IndifferentKetchup\CodexPz\Log\Minecraft\Vanilla\VanillaServerLog;
use PHPUnit\Framework\TestCase;

class VanillaServerLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../src/Games/Minecraft/fixtures/vanilla-server-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new VanillaServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(7, $log->getEntries());
    }

    public function testWarnTokenMapsToWarningLevel(): void
    {
        $log = (new VanillaServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $warn = $log->getEntries()[3];
        $this->assertSame(Level::WARNING, $warn->getLevel());
    }

    public function testErrorTokenMapsToErrorLevel(): void
    {
        $log = (new VanillaServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $error = $log->getEntries()[5];
        $this->assertSame(Level::ERROR, $error->getLevel());
    }

    public function testGetDetectorsThrowsWhenLinePatternUnoverridden(): void
    {
        $instance = new class extends MinecraftLog {
            protected function getVariantName(): string { return 'X'; }
            protected function getTypeName(): string { return 'Y'; }
        };

        $this->expectException(\LogicException::class);
        $instance::getDetectors();
    }
}
