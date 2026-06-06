<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\Minecraft;

use IndifferentKetchup\CodexPz\Detective\Minecraft\MinecraftDetective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\Minecraft\Vanilla\VanillaServerLog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MinecraftDetectiveTest extends TestCase
{
    private function fixturesDir(): string
    {
        return __DIR__ . '/../../../src/Games/Minecraft/fixtures/';
    }

    /**
     * @return array<string, array{string, class-string}>
     */
    public static function fixtureDispatchProvider(): array
    {
        return [
            'vanilla-server' => ['vanilla-server-minimal.txt', VanillaServerLog::class],
        ];
    }

    #[DataProvider('fixtureDispatchProvider')]
    public function testDispatchesEachFixtureToCorrectLogClass(string $fixture, string $expectedClass): void
    {
        $detective = (new MinecraftDetective())
            ->setLogFile(new PathLogFile($this->fixturesDir() . $fixture));

        $this->assertInstanceOf($expectedClass, $detective->detect());
    }

    public function testRegistersOneLogClassInPhase1(): void
    {
        $detective = new MinecraftDetective();
        $this->assertCount(1, $detective->getPossibleLogClasses());
    }
}
