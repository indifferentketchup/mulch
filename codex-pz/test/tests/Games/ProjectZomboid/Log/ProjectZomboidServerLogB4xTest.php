<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\Level;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidServerLog;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that ProjectZomboidServerLog correctly parses B4x-format log lines
 * (build 41.78.x: `, <unix_ms>> <tick>>` shape) and that continuation lines
 * (tab-indented stack frames that match no format) fold under their preceding entry.
 */
class ProjectZomboidServerLogB4xTest extends TestCase
{
    private static string $fixturePath = __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/debug-server-b4x-minimal.txt';

    private function parsedLog(): ProjectZomboidServerLog
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new PathLogFile(self::$fixturePath));
        $log->parse();
        return $log;
    }

    public function testParsesMultipleSeparateEntries(): void
    {
        $entries = $this->parsedLog()->getEntries();
        // Fixture has 5 matching lines + 1 continuation; expect 5 entries, not 6.
        $this->assertCount(5, $entries);
    }

    public function testFirstEntryHasCorrectLevelAndPrefix(): void
    {
        $entries = $this->parsedLog()->getEntries();
        $this->assertSame(Level::INFO, $entries[0]->getLevel());
        $this->assertSame('General', $entries[0]->getPrefix());
    }

    public function testWarnEntryMapsToWarningLevel(): void
    {
        $entries = $this->parsedLog()->getEntries();
        $warnEntries = array_values(array_filter($entries, fn($e) => $e->getLevel() === Level::WARNING));
        $this->assertNotEmpty($warnEntries);
        $this->assertSame('General', $warnEntries[0]->getPrefix());
    }

    public function testErrorEntryMapsToErrorLevel(): void
    {
        $entries = $this->parsedLog()->getEntries();
        $errorEntries = array_values(array_filter($entries, fn($e) => $e->getLevel() === Level::ERROR));
        $this->assertNotEmpty($errorEntries);
        $this->assertSame('General', $errorEntries[0]->getPrefix());
    }

    public function testContinuationLineFoldsUnderPrecedingEntry(): void
    {
        $entries = $this->parsedLog()->getEntries();
        $errorEntries = array_values(array_filter($entries, fn($e) => $e->getLevel() === Level::ERROR));
        $this->assertNotEmpty($errorEntries);
        // The tab-indented continuation must attach to the ERROR entry, not form its own.
        $this->assertGreaterThan(1, count($errorEntries[0]->getLines()));
    }

    public function testModEntryPrefixExtracted(): void
    {
        $entries = $this->parsedLog()->getEntries();
        $modEntries = array_values(array_filter($entries, fn($e) => $e->getPrefix() === 'Mod'));
        $this->assertNotEmpty($modEntries);
        $this->assertSame(Level::INFO, $modEntries[0]->getLevel());
    }
}
