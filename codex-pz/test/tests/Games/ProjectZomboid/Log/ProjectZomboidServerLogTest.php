<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\Level;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidServerLog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProjectZomboidServerLogTest extends TestCase
{
    /**
     * Both PZ B41 and B42 line shapes must parse identically. B41 (and the
     * fixture used by every analyser test) emits `f:N, t:N, st:N,N,N,N>`;
     * B42 (release branch from 2026-04 onward, e.g. build 42.17) drops the
     * `t:` microsecond field entirely and tightens whitespace to
     * `f:N st:N,N,N,N>`.
     */
    public static function fixtureProvider(): array
    {
        $base = __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures';
        return [
            'pz41-format' => [$base . '/debug-server-minimal.txt'],
            'pz42-format' => [$base . '/debug-server-42x-minimal.txt'],
        ];
    }

    #[DataProvider('fixtureProvider')]
    public function testParsesEntriesWithLevelAndPrefix(string $fixturePath): void
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new PathLogFile($fixturePath));
        $log->parse();

        $entries = $log->getEntries();
        $this->assertNotEmpty($entries);

        $first = $entries[0];
        $this->assertSame('General', $first->getPrefix());
        $this->assertSame(Level::INFO, $first->getLevel());
        $this->assertNotNull($first->getTime());
    }

    #[DataProvider('fixtureProvider')]
    public function testStackTraceLinesAttachToTriggeringErrorEntry(string $fixturePath): void
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new PathLogFile($fixturePath));
        $log->parse();

        $errorEntry = null;
        foreach ($log->getEntries() as $entry) {
            if ($entry->getLevel() === Level::ERROR && $entry->getPrefix() === 'General') {
                $errorEntry = $entry;
                break;
            }
        }

        $this->assertNotNull($errorEntry);
        $this->assertGreaterThan(1, count($errorEntry->getLines()));
    }

    #[DataProvider('fixtureProvider')]
    public function testWarnLevelMapsCorrectly(string $fixturePath): void
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new PathLogFile($fixturePath));
        $log->parse();

        $warnEntries = array_filter($log->getEntries(), fn($e) => $e->getLevel() === Level::WARNING);
        $this->assertNotEmpty($warnEntries);
    }

    #[DataProvider('fixtureProvider')]
    public function testDetectiveDispatchesByContent(string $fixturePath): void
    {
        $detective = (new Detective())
            ->setLogFile(new PathLogFile($fixturePath))
            ->addPossibleLogClass(ProjectZomboidServerLog::class);

        $log = $detective->detect();
        $this->assertInstanceOf(ProjectZomboidServerLog::class, $log);
    }
}
