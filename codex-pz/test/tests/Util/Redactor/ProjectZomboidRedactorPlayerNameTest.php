<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Util\Redactor;

use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;
use PHPUnit\Framework\TestCase;

class ProjectZomboidRedactorPlayerNameTest extends TestCase
{
    public function testRedactsPlayerNameAfterRedactedSteamId(): void
    {
        // The Steam ID pass has already run; the literal placeholder 76561198000000000
        // precedes the quoted name. The player-name pass must redact the name.
        $input = '76561198000000000 "AdminUser" admin.broadcastMessage @ 1020,2020,0.';
        $expected = '76561198000000000 "<player>" admin.broadcastMessage @ 1020,2020,0.';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redact($input);

        $this->assertSame($expected, $output, 'Player name following the redacted Steam ID placeholder must be replaced.');
    }

    public function testRedactsChatMessageAuthor(): void
    {
        // The author field inside ChatMessage{...} must be replaced; the text
        // payload ('hello') is not in scope for player-name redaction and must
        // survive unchanged.
        $input = "[16-04-26 17:05:03.280][info] Got message:ChatMessage{chat=Local, author='Player1', text='hello'}.";
        $expected = "[16-04-26 17:05:03.280][info] Got message:ChatMessage{chat=Local, author='<player>', text='hello'}.";

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redact($input);

        $this->assertSame($expected, $output, 'ChatMessage author must be replaced while the text payload remains unchanged.');
    }

    public function testRedactsCombatNameInPvpLog(): void
    {
        // Only the FIRST quoted name (after "Combat: ") is redacted in v1.
        // The second name (after "hit") is NOT yet redacted — deferred to v2.
        // The weapon name ("Tire Iron (Worn)") must also survive unchanged.
        $input = '[16-04-26 17:14:35.128][INFO] Combat: "Player1" (1005,2005,0) hit "Player2" (1006,2005,0) weapon="Tire Iron (Worn)" damage=0.112317.';
        // Attacker coords (before "hit") are also replaced by the coordinates pass.
        // Victim coords (before "weapon=") lack the trailing keyword and are NOT replaced — deferred to v2.
        $expected = '[16-04-26 17:14:35.128][INFO] Combat: "<player>" (0,0,0) hit "Player2" (1006,2005,0) weapon="Tire Iron (Worn)" damage=0.112317.';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redact($input);

        // Player1 (after "Combat: ") is replaced; attacker coords (before "hit") are also replaced.
        // Player2 (after "hit") and victim coords (before "weapon=") are NOT replaced in v1 — deferred.
        $this->assertSame($expected, $output, 'First Combat: player name and attacker coords must be replaced; second name, victim coords, and weapon must survive.');
    }

    public function testRedactsSafetyNameInPvpLog(): void
    {
        $input = '[16-04-26 16:17:49.731][LOG] Safety: "Player1" (1000,2000,0) restore true.';
        // Coords (before ") restore") are also replaced by the coordinates pass.
        $expected = '[16-04-26 16:17:49.731][LOG] Safety: "<player>" (0,0,0) restore true.';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redact($input);

        $this->assertSame($expected, $output, 'Player name and coords following the Safety: token must both be replaced.');
    }

    public function testBareQuotedStringWithoutAnchorIsNotTouched(): void
    {
        // "foo" is not preceded by a redacted Steam ID, not inside ChatMessage{...},
        // and not after Combat:/Safety: — it must pass through unchanged.
        $input = 'option changed to "foo" successfully.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($input, $output, 'A quoted string with no matching anchor must not be redacted.');
    }

    public function testToggleOffLeavesNamesIntact(): void
    {
        $input = '76561198000000000 "Player1" ISLogSystem.writeLog @ 1000,2000,0.';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redactPlayerNames(false)
            ->redact($input);

        $this->assertSame($input, $output, 'With the player-name toggle disabled the original input must be returned unchanged.');
    }

    public function testRedactsPlayerNameAfterUniverse197SteamId(): void
    {
        // Both passes enabled: the Steam ID pass should replace 76561197xxx with the
        // placeholder, then the player name pass should replace the quoted name.
        $input = '76561197000000001 "Player1" ISLogSystem.writeLog @ 1000,2000,0.';
        $expected = '76561198000000000 "<player>" ISLogSystem.writeLog @ 1000,2000,0.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output, 'Player name following a universe-197 Steam ID must be redacted.');
    }

    public function testRedactsPlayerNameAfterUniverse199SteamId(): void
    {
        $input = '76561199000000003 "AdminUser" admin.broadcastMessage @ 1020,2020,0.';
        $expected = '76561198000000000 "<player>" admin.broadcastMessage @ 1020,2020,0.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output, 'Player name following a universe-199 Steam ID must be redacted.');
    }

    public function testPlayerNameRedactionIndependentOfSteamIdPass(): void
    {
        // Even when the Steam ID pass is disabled, the player name adjacent to a raw
        // SteamID64 in the cmd.txt shape must still be redacted by the player name pass.
        // This verifies the player-name regex is not solely anchored on the placeholder.
        $input = '76561197000000001 "Player1" ISLogSystem.writeLog @ 1000,2000,0.';
        $expected = '76561197000000001 "<player>" ISLogSystem.writeLog @ 1000,2000,0.';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redact($input);

        $this->assertSame($expected, $output, 'Player name after a raw universe-197 Steam ID must be redacted even when the Steam ID pass is disabled.');
    }
}
