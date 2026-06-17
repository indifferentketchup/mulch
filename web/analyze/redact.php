<?php

/**
 * PZ-aware PII redaction endpoint.
 *
 * The PHP codex library ships `ProjectZomboidRedactor`, which scrubs
 * Steam IDs, player display names, world coordinates and (its own) IP
 * regexes from raw Project Zomboid log content. It is NOT a portable
 * regex array, so the Next.js filter pipeline delegates the call to this
 * microservice (which already vendors codex-pz). The Node side calls
 * `POST /redact` with the raw content and gets back the redacted string.
 *
 * Mirrors `iblogs/src/Filter/ProjectZomboidRedactorFilter.php` semantics:
 * IP -> Steam ID -> player name -> coordinates, in that order. Toggles on
 * the redactor default to all-on; this endpoint currently runs with all
 * passes enabled because PZ PII is exactly what the redactor exists to
 * scrub.
 *
 * Idempotent: re-running the redactor on already-redacted content is a
 * no-op (the placeholder tokens do not match the redactor's own regexes,
 * except for the zeroed Steam ID `76561198000000000` which intentionally
 * IS a valid SteamID shape - the name pass anchors on it but the player
 * name placeholder is `<player>`, which the redactor's anchoring regexes
 * do not refire on).
 */

require_once __DIR__ . '/codex-pz/vendor/autoload.php';

use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;

header('Content-Type: application/json; charset=utf-8');

$content = file_get_contents('php://input');
if (empty($content)) {
    $content = file_get_contents('php://stdin');
}
if ($content === false || $content === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

// Cap the size of redaction requests at the same 50 MiB the Next side
// accepts on upload. The redactor is pure-PHP + regex on the full string,
// so it scales linearly with input size; this keeps the analyzer from
// chewing on accidentally-huge bodies.
$maxBytes = (int) (getenv('ANALYZER_REDACT_MAX_BYTES') ?: 52428800);
if (strlen($content) > $maxBytes) {
    http_response_code(413);
    echo json_encode([
        'error' => 'Content too large to redact',
        'limit_bytes' => $maxBytes,
    ]);
    exit;
}

$redactor = new ProjectZomboidRedactor();
// All four passes default to true; setting them explicitly here makes the
// contract clear to anyone reading the call site and lets a future toggle
// env-var flip individual passes without changing the wire contract.
$redactor
    ->redactIpAddresses(true)
    ->redactSteamIds(true)
    ->redactPlayerNames(true)
    ->redactCoordinates(true);

$redacted = $redactor->redact($content);

echo json_encode([
    'redacted' => $redacted,
    'input_bytes' => strlen($content),
    'output_bytes' => strlen($redacted),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
