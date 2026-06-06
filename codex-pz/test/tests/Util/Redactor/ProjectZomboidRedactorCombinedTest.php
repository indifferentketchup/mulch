<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Util\Redactor;

use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;
use PHPUnit\Framework\TestCase;

class ProjectZomboidRedactorCombinedTest extends TestCase
{
    public function testFullScrubAllTogglesOn(): void
    {
        // Realistic multi-line input touching all three PII categories:
        // Steam IDs, player names in multiple contexts (after Steam ID, in ChatMessage,
        // after Combat:/Safety:), and coordinates in multiple shapes (at clause,
        // bracketed, parenthesised before PvP verb).
        $input = implode("\n", [
            // cmd.txt / admin.txt: Steam ID + quoted name + at-clause coords (keyword " at ")
            '[16-04-26 12:00:00.000] 76561198111111111 "Player1" added Base.Aerosolbomb at 1000,2000,0.',
            // map.txt: Steam ID + quoted name + at-clause float coords
            '[16-04-26 12:00:01.000] 76561198222222222 "Player2" added IsoObject (fence_01) at 1050.0,2050.0,0.0.',
            // chat.txt: ChatMessage author
            "[16-04-26 17:05:03.280][info] Got message:ChatMessage{chat=Local, author='AdminUser', text='hello'}.",
            // pvp.txt Combat: name + attacker parenthesised coords before "hit"
            '[16-04-26 17:14:35.128][INFO] Combat: "Player1" (1005,2005,0) hit "Player2" (1006,2005,0) weapon="Tire Iron (Worn)" damage=0.112317.',
            // pvp.txt Safety: name + parenthesised coords before "restore"
            '[16-04-26 16:17:49.731][LOG] Safety: "Player1" (1000,2000,0) restore true.',
            // ClientActionLog: bracketed Steam ID + action + name + coords bracket
            '[16-04-26 12:00:02.000] [76561198333333333][ISEnterVehicle][Player2][1020,2020,0][Van_LectroMax].',
        ]);

        $expected = implode("\n", [
            '[16-04-26 12:00:00.000] 76561198000000000 "<player>" added Base.Aerosolbomb at 0,0,0.',
            '[16-04-26 12:00:01.000] 76561198000000000 "<player>" added IsoObject (fence_01) at 0,0,0.',
            "[16-04-26 17:05:03.280][info] Got message:ChatMessage{chat=Local, author='<player>', text='hello'}.",
            '[16-04-26 17:14:35.128][INFO] Combat: "<player>" (0,0,0) hit "Player2" (1006,2005,0) weapon="Tire Iron (Worn)" damage=0.112317.',
            '[16-04-26 16:17:49.731][LOG] Safety: "<player>" (0,0,0) restore true.',
            '[16-04-26 12:00:02.000] [76561198000000000][ISEnterVehicle][Player2][0,0,0][Van_LectroMax].',
        ]);

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output, 'With all three toggles on, every Steam ID, player name context, and coord shape must be replaced.');
    }

    public function testSteamIdToggleOffLeavesSteamIdsIntact(): void
    {
        // All three PII categories present; Steam ID toggle is disabled.
        //
        // PLAYER_AFTER_STEAMID_REGEX now also matches raw SteamID64 tokens (not just the
        // placeholder), so player names in the "after-Steam-ID" shape ARE redacted even
        // when the Steam ID pass is off. Names in other contexts (ChatMessage, Combat:/Safety:)
        // are also redacted. Only the raw Steam IDs survive untouched.
        $input = implode("\n", [
            '[16-04-26 12:00:00.000] 76561198111111111 "Player1" added Base.Aerosolbomb at 1000,2000,0.',
            "[16-04-26 17:05:03.280][info] Got message:ChatMessage{chat=Local, author='AdminUser', text='hello'}.",
            '[16-04-26 17:14:35.128][INFO] Combat: "Player2" (1005,2005,0) hit "Player1" (1006,2005,0) weapon="Pipe Bomb" damage=1.0.',
        ]);

        $expected = implode("\n", [
            // Steam ID intact; "Player1" IS redacted (PLAYER_AFTER_STEAMID_REGEX now matches raw IDs)
            '[16-04-26 12:00:00.000] 76561198111111111 "<player>" added Base.Aerosolbomb at 0,0,0.',
            "[16-04-26 17:05:03.280][info] Got message:ChatMessage{chat=Local, author='<player>', text='hello'}.",
            '[16-04-26 17:14:35.128][INFO] Combat: "<player>" (0,0,0) hit "Player1" (1006,2005,0) weapon="Pipe Bomb" damage=1.0.',
        ]);

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redact($input);

        $this->assertSame(
            $expected,
            $output,
            'With Steam ID toggle off: raw Steam IDs survive; player names in the after-Steam-ID shape are still redacted (PLAYER_AFTER_STEAMID_REGEX is independent); ChatMessage and Combat:/Safety: names and coords are also redacted.',
        );
    }

    public function testPlayerNameToggleOffLeavesNamesIntact(): void
    {
        // Steam IDs and coords redact; player names survive verbatim.
        $input = implode("\n", [
            '[16-04-26 12:00:00.000] 76561198111111111 "Player1" added Base.Aerosolbomb at 1000,2000,0.',
            "[16-04-26 17:05:03.280][info] Got message:ChatMessage{chat=Local, author='Player2', text='bye'}.",
            '[16-04-26 16:17:49.731][LOG] Safety: "AdminUser" (1050,2050,0) restore true.',
        ]);

        $expected = implode("\n", [
            '[16-04-26 12:00:00.000] 76561198000000000 "Player1" added Base.Aerosolbomb at 0,0,0.',
            "[16-04-26 17:05:03.280][info] Got message:ChatMessage{chat=Local, author='Player2', text='bye'}.",
            '[16-04-26 16:17:49.731][LOG] Safety: "AdminUser" (0,0,0) restore true.',
        ]);

        $output = (new ProjectZomboidRedactor())
            ->redactPlayerNames(false)
            ->redact($input);

        $this->assertSame($expected, $output, 'With player-name toggle off, all player names must survive; Steam IDs and coords must still be redacted.');
    }

    public function testCoordinatesToggleOffLeavesCoordsIntact(): void
    {
        // Steam IDs and player names redact; coordinates survive verbatim.
        $input = implode("\n", [
            '[16-04-26 12:00:00.000] 76561198111111111 "Player1" added Base.Aerosolbomb at 1000,2000,0.',
            '[16-04-26 12:00:01.000] [76561198222222222][ISEnterVehicle][Player2][1020,2020,0][Van_LectroMax].',
            '[16-04-26 17:14:35.128][INFO] Combat: "AdminUser" (1005,2005,0) hit "Player1" (1006,2005,0) weapon="Baseball Bat" damage=0.5.',
        ]);

        $expected = implode("\n", [
            '[16-04-26 12:00:00.000] 76561198000000000 "<player>" added Base.Aerosolbomb at 1000,2000,0.',
            '[16-04-26 12:00:01.000] [76561198000000000][ISEnterVehicle][Player2][1020,2020,0][Van_LectroMax].',
            '[16-04-26 17:14:35.128][INFO] Combat: "<player>" (1005,2005,0) hit "Player1" (1006,2005,0) weapon="Baseball Bat" damage=0.5.',
        ]);

        $output = (new ProjectZomboidRedactor())
            ->redactCoordinates(false)
            ->redact($input);

        $this->assertSame($expected, $output, 'With coordinates toggle off, all coord triplets must survive; Steam IDs and player names must still be redacted.');
    }

    public function testAllTogglesOffReturnsInputByteForByte(): void
    {
        // Disabling every toggle must produce an output identical to the input —
        // the "passthrough" contract: opt-out means truly nothing happens.
        $input = implode("\n", [
            '[16-04-26 12:00:00.000] 76561198111111111 "Player1" added Base.Aerosolbomb at 1000,2000,0.',
            "[16-04-26 17:05:03.280][info] Got message:ChatMessage{chat=Local, author='Player2', text='hello'}.",
            '[16-04-26 17:14:35.128][INFO] Combat: "AdminUser" (1005,2005,0) hit "Player1" (1006,2005,0) weapon="Tire Iron (Worn)" damage=0.112317.',
            '[16-04-26 12:00:01.000] [76561198333333333][ISEnterVehicle][Player2][1020,2020,0][Van_LectroMax].',
        ]);

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redactPlayerNames(false)
            ->redactCoordinates(false)
            ->redact($input);

        $this->assertSame($input, $output, 'With all three toggles disabled, the output must be byte-for-byte identical to the input.');
    }
}
