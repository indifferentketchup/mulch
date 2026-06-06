<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Security;

use IndifferentKetchup\CodexPz\Detective\ProjectZomboid\ProjectZomboidDetective;
use IndifferentKetchup\CodexPz\Log\AnalysableLogInterface;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;
use PHPUnit\Framework\TestCase;

/**
 * Security gate for the entire PZ error-pipeline epic.
 *
 * A synthetic fixture containing Steam IDs from all three individual-account
 * SteamID64 universe prefixes (76561197/8/9), quoted player names, and a
 * Lua((MOD:...)) stack frame is driven through the full consumer pipeline:
 *
 *   redact → detect → parse → analyse → jsonSerialize → json_encode
 *
 * The resulting JSON must contain no recognisable Steam ID (no
 * 76561197/8/9 + 9 digits, other than the zero placeholder) and none of
 * the fixture's synthetic player names. This assertion must remain green
 * before any new Insight field that surfaces log-derived content is shipped.
 */
class PIIRoundTripTest extends TestCase
{
    private static string $fixturesDir = __DIR__ . '/../../src/Games/ProjectZomboid/fixtures';

    public function testFullPipelineEmitsNoSteamIdsOrPlayerNamesInJson(): void
    {
        $fixtureContent = file_get_contents(self::$fixturesDir . '/pii-roundtrip-multi-universe-minimal.txt');
        $this->assertNotFalse($fixtureContent, 'Fixture file must be readable.');

        // Step 1: Redact (consumer applies the redactor before persisting / parsing).
        $redactedContent = (new ProjectZomboidRedactor())->redact($fixtureContent);

        // Step 2: Detect log type from the redacted content.
        $detective = (new ProjectZomboidDetective())->setLogFile(new StringLogFile($redactedContent));
        $log = $detective->detect();
        $this->assertInstanceOf(AnalysableLogInterface::class, $log, 'Detected log must be analysable.');

        // Step 3: Parse.
        $log->parse();

        // Step 4: Analyse.
        $analysis = $log->analyse();

        // At least one problem must be emitted so the JSON surface is non-trivial.
        $this->assertNotEmpty($analysis->getProblems(), 'Analysis must produce at least one problem for the PII gate to be meaningful.');

        // Step 5+6: jsonSerialize → json_encode.
        $json = json_encode($analysis->jsonSerialize());
        $this->assertNotFalse($json, 'json_encode must succeed on the analysis output.');

        // Assert: no universe-197 Steam ID survived.
        $this->assertDoesNotMatchRegularExpression(
            '/76561197\d{9}/',
            $json,
            'Universe-197 Steam IDs must not appear in the JSON analysis output.'
        );

        // Assert: no universe-199 Steam ID survived.
        $this->assertDoesNotMatchRegularExpression(
            '/76561199\d{9}/',
            $json,
            'Universe-199 Steam IDs must not appear in the JSON analysis output.'
        );

        // Assert: no non-placeholder universe-198 Steam ID survived.
        $this->assertDoesNotMatchRegularExpression(
            '/76561198(?!000000000)\d{9}/',
            $json,
            'Non-placeholder universe-198 Steam IDs must not appear in the JSON analysis output.'
        );

        // Assert: no synthetic player name survived.
        foreach (['Player1', 'Player2', 'AdminUser'] as $name) {
            $this->assertStringNotContainsString(
                $name,
                $json,
                sprintf('Synthetic player name "%s" must not appear in the JSON analysis output.', $name)
            );
        }
    }
}
