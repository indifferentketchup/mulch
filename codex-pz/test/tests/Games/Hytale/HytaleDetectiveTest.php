<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\Hytale;

use IndifferentKetchup\CodexPz\Detective\Hytale\HytaleDetective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\Hytale\HytaleClientLog;
use IndifferentKetchup\CodexPz\Log\Hytale\HytaleServerLog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HytaleDetectiveTest extends TestCase
{
    private function fixturesDir(): string
    {
        return __DIR__ . '/../../../src/Games/Hytale/fixtures/';
    }

    /**
     * @return array<string, array{string, class-string}>
     */
    public static function fixtureDispatchProvider(): array
    {
        return [
            'server' => ['server-minimal.txt', HytaleServerLog::class],
            'client' => ['client-minimal.txt', HytaleClientLog::class],
        ];
    }

    #[DataProvider('fixtureDispatchProvider')]
    public function testDispatchesEachFixtureToCorrectLogClass(string $fixture, string $expectedClass): void
    {
        $detective = (new HytaleDetective())
            ->setLogFile(new PathLogFile($this->fixturesDir() . $fixture));

        $this->assertInstanceOf($expectedClass, $detective->detect());
    }

    public function testRegistersTwoLogClasses(): void
    {
        $detective = new HytaleDetective();
        $this->assertCount(2, $detective->getPossibleLogClasses());
    }
}
