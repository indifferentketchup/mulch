<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Util\Redactor;

use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;
use PHPUnit\Framework\TestCase;

class ProjectZomboidRedactorSteamIdTest extends TestCase
{
    public function testCollapsesDistinctSteamIdsToZeroPlaceholder(): void
    {
        $input = 'Players: 76561198111111111, 76561198222222222, 76561198333333333 connected.';
        $expected = 'Players: 76561198000000000, 76561198000000000, 76561198000000000 connected.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output, 'All three distinct Steam IDs should be replaced with the zero placeholder.');
    }

    public function testNonSteamIdLongDigitsAreNotTouched(): void
    {
        // 13-digit Unix-millisecond timestamp (PZ log t: shape) and a 17-digit number
        // that does not begin with 76561198 — neither should be altered.
        $input = 't:1776297642406 score=12345678901234567';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($input, $output, 'Non-SteamID digit sequences must not be modified.');
    }

    public function testEmbeddedSteamIdInsideLongerAlphanumericTokenIsNotTouched(): void
    {
        // The SteamID64 pattern is embedded inside a longer alphanumeric token;
        // the negative lookaround boundaries should prevent a match.
        $input = 'token=abc76561198000000001def other=data';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($input, $output, 'A Steam ID embedded inside an alphanumeric token must not be redacted.');
    }

    public function testToggleOffLeavesSteamIdsIntact(): void
    {
        $input = 'Connected: 76561198111111111 and 76561198222222222.';

        $output = (new ProjectZomboidRedactor())
            ->redactSteamIds(false)
            ->redact($input);

        $this->assertSame($input, $output, 'With the Steam ID toggle disabled the original input must be returned unchanged.');
    }

    public function testRedactsUniverse197SteamId(): void
    {
        $input = 'Player joined: 76561197000000001 connected.';
        $expected = 'Player joined: 76561198000000000 connected.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output, 'Universe-197 Steam IDs (76561197xxxxxxxxx) must be redacted.');
    }

    public function testRedactsUniverse199SteamId(): void
    {
        $input = 'Player joined: 76561199000000003 connected.';
        $expected = 'Player joined: 76561198000000000 connected.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output, 'Universe-199 Steam IDs (76561199xxxxxxxxx) must be redacted.');
    }

    public function testAllThreeUniversesRedactedToSamePlaceholder(): void
    {
        $input = 'ids: 76561197000000001 76561198000000002 76561199000000003.';
        $expected = 'ids: 76561198000000000 76561198000000000 76561198000000000.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output, 'All three universe prefixes (197/198/199) must reduce to the same zero placeholder.');
    }
}
