<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Util\Redactor;

use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
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
use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests: drive all 11 existing PZ fixtures through ProjectZomboidRedactor
 * and verify that the output is well-formed.
 *
 * Three properties are checked across all fixtures:
 *
 *   1. Steam ID normalisation — no non-zero-placeholder Steam IDs survive.
 *   2. Structural preservation — parsing the redacted content yields the same
 *      entry count as parsing the original.
 *   3. Idempotence — applying redact() a second time produces no further changes.
 *
 * Known v1 limitations documented inline:
 *
 *   - pvp.txt: victim names after `hit "..."` are NOT redacted (Task 3 limitation).
 *     Player2 can therefore still appear after `hit` in the redacted pvp output.
 *   - pvp.txt: victim coords after `hit "(x,y,z)"` are NOT redacted (Task 4
 *     limitation). COORDS_PARENTHESISED_REGEX anchors on the trailing PvP verb
 *     which is present only for the attacker bracket.
 *   - admin.txt: `teleported X to <x,y,z>` coords survive because COORDS_AT_CLAUSE_REGEX
 *     anchors on ` at `, not ` to `.
 */
class ProjectZomboidRedactorIntegrationTest extends TestCase
{
    private static string $fixturesDir = __DIR__ . '/../../../src/Games/ProjectZomboid/fixtures';

    // ---------------------------------------------------------------------------
    // Data providers
    // ---------------------------------------------------------------------------

    /**
     * Yields [fixturePath] for every PZ fixture file.
     */
    public static function fixturePathProvider(): array
    {
        $dir = self::$fixturesDir;
        return [
            'admin'             => [$dir . '/admin-minimal.txt'],
            'burd-journals'     => [$dir . '/burd-journals-minimal.txt'],
            'chat'              => [$dir . '/chat-minimal.txt'],
            'client-action'     => [$dir . '/client-action-minimal.txt'],
            'cmd'               => [$dir . '/cmd-minimal.txt'],
            'debug-server'      => [$dir . '/debug-server-minimal.txt'],
            'item'              => [$dir . '/item-minimal.txt'],
            'map'               => [$dir . '/map-minimal.txt'],
            'perk'              => [$dir . '/perk-minimal.txt'],
            'pvp'               => [$dir . '/pvp-minimal.txt'],
            'user'              => [$dir . '/user-minimal.txt'],
        ];
    }

    /**
     * Yields [fixturePath] for the subset of fixtures where every synthetic
     * player name (Player1 / Player2 / AdminUser / PlayerSuspect) appears
     * exclusively in a context the redactor recognises:
     *
     *   - chat:  ChatMessage{author='...'} envelope
     *   - cmd, item, map, user:  77-char-Steam-ID followed by "..." quoted name
     *
     * Fixtures intentionally excluded:
     *
     *   - admin:          names appear in free-text positions (no Steam-ID anchor,
     *                     no quotes, no Combat:/Safety: prefix). Names survive in v1.
     *   - client-action,
     *     perk:           names appear inside [...] brackets, not "..." quotes.
     *                     PLAYER_AFTER_STEAMID_REGEX requires double-quotes.
     *   - pvp:            attacker name redacts but victim name after `hit "..."`
     *                     survives in v1 (Task 3 limitation).
     *   - burd-journals,
     *     debug-server:   no synthetic player names present.
     */
    public static function fixturesWhereAllNamesAreInCoveredContextsProvider(): array
    {
        $dir = self::$fixturesDir;
        return [
            'chat' => [$dir . '/chat-minimal.txt'],
            'cmd'  => [$dir . '/cmd-minimal.txt'],
            'item' => [$dir . '/item-minimal.txt'],
            'map'  => [$dir . '/map-minimal.txt'],
            'user' => [$dir . '/user-minimal.txt'],
        ];
    }

    /**
     * Yields [fixturePath, logClass] for the fixtures whose log class parses
     * them. All 11 fixtures are represented.
     */
    public static function fixtureWithLogClassProvider(): array
    {
        $dir = self::$fixturesDir;
        return [
            'admin'             => [$dir . '/admin-minimal.txt',         ProjectZomboidAdminLog::class],
            'burd-journals'     => [$dir . '/burd-journals-minimal.txt', ProjectZomboidBurdJournalsLog::class],
            'chat'              => [$dir . '/chat-minimal.txt',           ProjectZomboidChatLog::class],
            'client-action'     => [$dir . '/client-action-minimal.txt', ProjectZomboidClientActionLog::class],
            'cmd'               => [$dir . '/cmd-minimal.txt',            ProjectZomboidCmdLog::class],
            'debug-server'      => [$dir . '/debug-server-minimal.txt',  ProjectZomboidServerLog::class],
            'item'              => [$dir . '/item-minimal.txt',           ProjectZomboidItemLog::class],
            'map'               => [$dir . '/map-minimal.txt',            ProjectZomboidMapLog::class],
            'perk'              => [$dir . '/perk-minimal.txt',           ProjectZomboidPerkLog::class],
            'pvp'               => [$dir . '/pvp-minimal.txt',            ProjectZomboidPvpLog::class],
            'user'              => [$dir . '/user-minimal.txt',           ProjectZomboidUserLog::class],
        ];
    }

    // ---------------------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------------------

    private function redact(string $content): string
    {
        return (new ProjectZomboidRedactor())->redact($content);
    }

    // ---------------------------------------------------------------------------
    // Test 1 — Steam ID normalisation
    // ---------------------------------------------------------------------------

    /**
     * After redaction every 17-digit Steam ID that is NOT the zero-placeholder
     * must be gone. The zero-placeholder itself (76561198000000000) is the only
     * Steam ID that may remain.
     */
    #[DataProvider('fixturePathProvider')]
    public function testFixtureContainsNoSteamIdsAfterRedaction(string $fixturePath): void
    {
        $content = (new PathLogFile($fixturePath))->getContent();
        $redacted = $this->redact($content);

        $matches = preg_match_all('/(?<![A-Za-z0-9])76561198(?!000000000)\d{9}(?![A-Za-z0-9])/u', $redacted);

        $this->assertSame(
            0,
            $matches,
            sprintf(
                'After redaction, fixture "%s" must contain no non-zero-placeholder Steam IDs, but %d were found.',
                basename($fixturePath),
                $matches,
            ),
        );
    }

    // ---------------------------------------------------------------------------
    // Test 2 — Structural preservation (re-parse after redaction)
    // ---------------------------------------------------------------------------

    /**
     * The redacted content, fed back through the corresponding parser, must
     * produce exactly the same number of log entries as the original content.
     *
     * This asserts that the redactor does not corrupt timestamps, delimiters,
     * or structural tokens that the parser relies on.
     *
     * @param string $fixturePath Path to the fixture file.
     * @param class-string<\IndifferentKetchup\CodexPz\Log\Log> $logClass
     *   Fully-qualified name of the Log subclass that corresponds to this fixture.
     */
    #[DataProvider('fixtureWithLogClassProvider')]
    public function testFixtureRedactedOutputParsesToSameEntryCount(string $fixturePath, string $logClass): void
    {
        $content = (new PathLogFile($fixturePath))->getContent();

        /** @var \IndifferentKetchup\CodexPz\Log\Log $originalLog */
        $originalLog = (new $logClass())->setLogFile(new PathLogFile($fixturePath));
        $originalLog->parse();
        $originalCount = count($originalLog->getEntries());

        $redacted = $this->redact($content);

        /** @var \IndifferentKetchup\CodexPz\Log\Log $redactedLog */
        $redactedLog = (new $logClass())->setLogFile(new StringLogFile($redacted));
        $redactedLog->parse();
        $redactedCount = count($redactedLog->getEntries());

        $this->assertSame(
            $originalCount,
            $redactedCount,
            sprintf(
                'Parsing the redacted "%s" fixture with %s must yield the same entry count (%d) as parsing the original, but got %d.',
                basename($fixturePath),
                $logClass,
                $originalCount,
                $redactedCount,
            ),
        );
    }

    // ---------------------------------------------------------------------------
    // Test 3 — Idempotence
    // ---------------------------------------------------------------------------

    /**
     * Applying redact() a second time must produce no further changes:
     * redact(redact(content)) === redact(content).
     *
     * This guards against poorly-anchored regexes that would re-match the
     * redaction placeholders themselves on a second pass.
     */
    #[DataProvider('fixturePathProvider')]
    public function testFixtureIsIdempotent(string $fixturePath): void
    {
        $content = (new PathLogFile($fixturePath))->getContent();

        $redactor = new ProjectZomboidRedactor();
        $once = $redactor->redact($content);
        $twice = $redactor->redact($once);

        $this->assertSame(
            $once,
            $twice,
            sprintf(
                'redact(redact(content)) must equal redact(content) for fixture "%s"; a second pass must be a no-op.',
                basename($fixturePath),
            ),
        );
    }

    // ---------------------------------------------------------------------------
    // Test 4 — Player-name collapse in fully-covered fixtures
    // ---------------------------------------------------------------------------

    /**
     * For fixtures where every synthetic player name appears exclusively in a
     * context the redactor recognises, no synthetic name should remain after
     * redaction.
     *
     * This addresses observation #3 from the final code review (the integration
     * tests previously asserted Steam-ID elimination + structural preservation
     * + idempotence, but did not directly verify name collapse). The unit tests
     * in ProjectZomboidRedactorPlayerNameTest cover this property exhaustively
     * per-context; this integration test re-verifies it end-to-end against the
     * fixtures that ride into iblogs.
     */
    #[DataProvider('fixturesWhereAllNamesAreInCoveredContextsProvider')]
    public function testFixturePlayerNamesCollapseInCoveredContexts(string $fixturePath): void
    {
        $content = (new PathLogFile($fixturePath))->getContent();
        $redacted = $this->redact($content);

        foreach (['Player1', 'Player2', 'AdminUser', 'PlayerSuspect'] as $name) {
            $this->assertStringNotContainsString(
                $name,
                $redacted,
                sprintf(
                    'Fixture "%s": synthetic name %s survived redaction. Every name in this fixture should appear only in a covered lexical context.',
                    basename($fixturePath),
                    $name,
                ),
            );
        }
    }
}
