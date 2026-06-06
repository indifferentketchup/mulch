<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Util\Redactor;

use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;
use PHPUnit\Framework\TestCase;

class ProjectZomboidRedactorCoordinatesTest extends TestCase
{
    public function testRedactsAtClauseCoords(): void
    {
        // map.txt / item.txt shape: integer coords following " at " with trailing dot.
        $input = '[16-04-26 12:00:00.000] 76561198000000001 "Player1" added Base.Aerosolbomb at 1000,2000,0.';
        $expected = '[16-04-26 12:00:00.000] 76561198000000001 "Player1" added Base.Aerosolbomb at 0,0,0.';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redactPlayerNames(false)
            ->redact($input);

        $this->assertSame($expected, $output, 'Integer coords following " at " must be replaced; leading "at " and trailing "." must be preserved.');
    }

    public function testRedactsAtClauseFloatCoords(): void
    {
        // map.txt shape: IsoObject form with float coords (x.x,y.y,z.z).
        $input = '[16-04-26 12:00:01.000] 76561198000000001 "Player1" added IsoObject (fencing_damaged_01_124) at 1010.0,2010.0,0.0.';
        $expected = '[16-04-26 12:00:01.000] 76561198000000001 "Player1" added IsoObject (fencing_damaged_01_124) at 0,0,0.';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redactPlayerNames(false)
            ->redact($input);

        $this->assertSame($expected, $output, 'Float coords following " at " must be replaced; the IsoObject parenthesised form must be unaffected.');
    }

    public function testRedactsBracketedCoords(): void
    {
        // ClientActionLog.txt shape: strict 5-field bracketed structure.
        // The Steam ID bracket and action/player/param brackets must survive.
        $input = '[16-04-26 12:00:02.000] [76561198000000001][ISEnterVehicle][Player1][1000,2000,0][Van_LectroMax].';
        $expected = '[16-04-26 12:00:02.000] [76561198000000001][ISEnterVehicle][Player1][0,0,0][Van_LectroMax].';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redactPlayerNames(false)
            ->redact($input);

        $this->assertSame($expected, $output, 'Coord bracket must become [0,0,0]; Steam ID, action, player name, and param brackets must be unaffected.');
    }

    public function testRedactsBracketedNegativeZ(): void
    {
        // Basement Z coordinates are negative; the regex must handle the leading minus.
        $input = '[16-04-26 12:00:03.000] [76561198000000001][ISEnterVehicle][Player1][1020,2020,-1][Van_LectroMax].';
        $expected = '[16-04-26 12:00:03.000] [76561198000000001][ISEnterVehicle][Player1][0,0,0][Van_LectroMax].';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redactPlayerNames(false)
            ->redact($input);

        $this->assertSame($expected, $output, 'Negative Z (basement level) inside square brackets must be replaced.');
    }

    public function testRedactsParenthesisedCoordsBeforeHit(): void
    {
        // pvp.txt Combat: shape. The attacker coords are followed by ") hit" and ARE
        // redacted. The victim coords are followed by ") weapon=" and are NOT redacted
        // in v1 — the trailing-keyword anchor is intentionally absent for that position.
        $input = '[16-04-26 17:14:35.128][INFO] Combat: "Player1" (1005,2005,0) hit "Player2" (1006,2005,0) weapon="Tire Iron (Worn)" damage=0.112317.';
        $expected = '[16-04-26 17:14:35.128][INFO] Combat: "Player1" (0,0,0) hit "Player2" (1006,2005,0) weapon="Tire Iron (Worn)" damage=0.112317.';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redactPlayerNames(false)
            ->redact($input);

        // Attacker coords (before "hit") are redacted; victim coords (before "weapon=") are NOT — deferred to v2.
        $this->assertSame($expected, $output, 'Attacker coords before "hit" must be replaced; victim coords without a trailing keyword must survive.');
    }

    public function testRedactsParenthesisedCoordsBeforeSafetyVerb(): void
    {
        // pvp.txt Safety: shape; coords followed by ") restore true".
        $input = '[16-04-26 16:17:49.731][LOG] Safety: "Player1" (1000,2000,0) restore true.';
        $expected = '[16-04-26 16:17:49.731][LOG] Safety: "Player1" (0,0,0) restore true.';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redactPlayerNames(false)
            ->redact($input);

        $this->assertSame($expected, $output, 'Coords followed by ") restore" must be replaced.');
    }

    public function testServerMetadataTriplesAreNotRedacted(): void
    {
        // DebugLog-server.txt entries contain server-state metadata that superficially
        // resembles coordinates but is not: "st:48,648,157,584" is a 4-component token,
        // "t:1776297642406" is a millisecond timestamp. Neither pattern lives inside
        // brackets, parentheses followed by a PvP verb, or after " at " — so none of
        // the three coordinate regexes should fire.
        $input = '[16-04-26 00:01:19.080] ERROR: General      f:0, t:1776297642406, st:48,648,157,584> Server starting up.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($input, $output, 'Server metadata triples (st:) and millisecond timestamps (t:) must pass through unchanged.');
    }

    public function testToggleOffLeavesCoordsIntact(): void
    {
        $input = '[16-04-26 12:00:04.000] 76561198000000001 "Player1" added Base.Aerosolbomb at 1000,2000,0.';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redactPlayerNames(false)
            ->redactCoordinates(false)
            ->redact($input);

        $this->assertSame($input, $output, 'With the coordinates toggle disabled the original input must be returned unchanged.');
    }
}
