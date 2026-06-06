<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Util\Redactor;

use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the idempotence property of ProjectZomboidRedactor::redact().
 *
 * Idempotence: redact(redact(x)) === redact(x) for all valid inputs.
 *
 * A downstream consumer might accidentally double-pipe content through the
 * Redactor. The result must be stable — a second pass must make no further
 * changes. If a regex were poorly anchored such that the post-redact placeholder
 * itself matched and was re-redacted to something different, idempotence would
 * fail. Specifically, the player-name regex PLAYER_AFTER_STEAMID_REGEX anchors
 * on 76561198000000000 — the same value the Steam ID pass writes. This test
 * suite verifies that applying redact() twice is safe: on the second pass, names
 * already written as <player> do not accidentally re-match and produce a doubly-
 * nested result like "<player>" → something else.
 */
class ProjectZomboidRedactorIdempotenceTest extends TestCase
{
    public function testIdempotenceSteamIdOnly(): void
    {
        $input = implode("\n", [
            'Players: 76561198111111111, 76561198222222222, 76561198333333333 connected.',
            '[16-04-26 12:00:00.000] [76561198111111111][ISEnterVehicle][Player1][1000,2000,0][Van_LectroMax].',
        ]);

        $redactor = new ProjectZomboidRedactor();
        $redacted = $redactor->redact($input);
        $redactedAgain = $redactor->redact($redacted);

        $this->assertSame($redacted, $redactedAgain, 'Applying redact() twice to Steam-ID-only input must produce the same result as applying it once.');
    }

    public function testIdempotencePlayerNamesOnly(): void
    {
        // Input already has the Steam ID placeholder in place (as the Steam ID pass
        // would have written it), so PLAYER_AFTER_STEAMID_REGEX can fire. After the
        // first pass the name becomes "<player>"; the second pass must leave "<player>"
        // untouched — it is not a valid display name inside double quotes preceded
        // by the Steam ID placeholder anchor in a way that would re-match, because
        // the replacement written is: 76561198000000000 "<player>", and the regex
        // would need an unquoted player name inside quotes after the placeholder.
        // "<player>" (with the angle brackets) does satisfy [^"]+ but the second
        // pass must still produce an identical result.
        $input = implode("\n", [
            '76561198000000000 "Player1" ISLogSystem.writeLog @ 1000,2000,0.',
            "[16-04-26 17:05:03.280][info] Got message:ChatMessage{chat=Local, author='AdminUser', text='hi'}.",
            '[16-04-26 16:17:49.731][LOG] Safety: "Player2" (1000,2000,0) restore true.',
        ]);

        $redactor = (new ProjectZomboidRedactor())->redactSteamIds(false)->redactCoordinates(false);
        $redacted = $redactor->redact($input);
        $redactedAgain = $redactor->redact($redacted);

        $this->assertSame($redacted, $redactedAgain, 'Applying redact() twice to player-name-only input must produce the same result as applying it once.');
    }

    public function testIdempotenceCoordsOnly(): void
    {
        $input = implode("\n", [
            '[16-04-26 12:00:00.000] 76561198000000001 "Player1" added Base.Aerosolbomb at 1000,2000,0.',
            '[16-04-26 12:00:01.000] [76561198000000001][ISEnterVehicle][Player1][1020,2020,-1][Van_LectroMax].',
            '[16-04-26 17:14:35.128][INFO] Combat: "Player1" (1005,2005,0) hit "Player2" (1006,2005,0) weapon="Tire Iron (Worn)" damage=0.112317.',
            '[16-04-26 16:17:49.731][LOG] Safety: "Player1" (1000,2000,0) restore true.',
        ]);

        $redactor = (new ProjectZomboidRedactor())->redactSteamIds(false)->redactPlayerNames(false);
        $redacted = $redactor->redact($input);
        $redactedAgain = $redactor->redact($redacted);

        $this->assertSame($redacted, $redactedAgain, 'Applying redact() twice to coords-only input must produce the same result as applying it once; the placeholder 0,0,0 must not be re-matched.');
    }

    public function testIdempotenceAllCategories(): void
    {
        // Full input: all three PII categories in multiple lexical contexts.
        // After the first redact(), every placeholder is in place. The second
        // redact() must make no further changes.
        $input = implode("\n", [
            '[16-04-26 12:00:00.000] 76561198111111111 "Player1" added Base.Aerosolbomb at 1000,2000,0.',
            '[16-04-26 12:00:01.000] 76561198222222222 "Player2" teleported to 1050,2050,0.',
            "[16-04-26 17:05:03.280][info] Got message:ChatMessage{chat=Local, author='AdminUser', text='hello'}.",
            '[16-04-26 17:14:35.128][INFO] Combat: "Player1" (1005,2005,0) hit "Player2" (1006,2005,0) weapon="Tire Iron (Worn)" damage=0.112317.',
            '[16-04-26 16:17:49.731][LOG] Safety: "Player1" (1000,2000,0) restore true.',
            '[16-04-26 12:00:02.000] [76561198333333333][ISEnterVehicle][Player2][1020,2020,0][Van_LectroMax].',
        ]);

        $redactor = new ProjectZomboidRedactor();
        $redacted = $redactor->redact($input);
        $redactedAgain = $redactor->redact($redacted);

        $this->assertSame($redacted, $redactedAgain, 'Applying redact() twice to input with all PII categories must produce the same result as applying it once; no placeholder must re-match on the second pass.');
    }
}
