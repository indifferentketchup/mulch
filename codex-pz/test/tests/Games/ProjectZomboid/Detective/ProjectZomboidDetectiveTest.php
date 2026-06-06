<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Detective;

use IndifferentKetchup\CodexPz\Detective\ProjectZomboid\ProjectZomboidDetective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidAdminLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidBurdJournalsLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidChatLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidClientActionLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidCmdLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidItemLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidMapLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidPerkLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidPvpLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidServerLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidUserLog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProjectZomboidDetectiveTest extends TestCase
{
    private function fixturesDir(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/';
    }

    /**
     * @return array<string, array{string, class-string}>
     */
    public static function fixtureDispatchProvider(): array
    {
        return [
            'server'        => ['debug-server-minimal.txt',  ProjectZomboidServerLog::class],
            'chat'          => ['chat-minimal.txt',          ProjectZomboidChatLog::class],
            'client-action' => ['client-action-minimal.txt', ProjectZomboidClientActionLog::class],
            'cmd'           => ['cmd-minimal.txt',           ProjectZomboidCmdLog::class],
            'item'          => ['item-minimal.txt',          ProjectZomboidItemLog::class],
            'map'           => ['map-minimal.txt',           ProjectZomboidMapLog::class],
            'perk'          => ['perk-minimal.txt',          ProjectZomboidPerkLog::class],
            'pvp'           => ['pvp-minimal.txt',           ProjectZomboidPvpLog::class],
            'admin'         => ['admin-minimal.txt',         ProjectZomboidAdminLog::class],
            'user'          => ['user-minimal.txt',          ProjectZomboidUserLog::class],
            'burd-journals' => ['burd-journals-minimal.txt', ProjectZomboidBurdJournalsLog::class],
        ];
    }

    #[DataProvider('fixtureDispatchProvider')]
    public function testDispatchesEachFixtureToCorrectLogClass(string $fixture, string $expectedClass): void
    {
        $detective = (new ProjectZomboidDetective())
            ->setLogFile(new PathLogFile($this->fixturesDir() . $fixture));

        $this->assertInstanceOf($expectedClass, $detective->detect());
    }

    public function testRegistersElevenLogClasses(): void
    {
        $detective = new ProjectZomboidDetective();
        $this->assertCount(11, $detective->getPossibleLogClasses());
    }
}
