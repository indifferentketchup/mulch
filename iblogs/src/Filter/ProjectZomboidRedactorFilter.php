<?php

namespace IndifferentKetchup\Iblogs\Filter;

use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;

/**
 * Save-time wrapper that delegates to codex's ProjectZomboidRedactor.
 *
 * Codex owns the canonical Project Zomboid PII patterns (Steam IDs, player
 * names, world coordinates, plus IPv4 / IPv6 addresses with the v0.3.0
 * release). This filter is the single point at which PZ-shaped PII is
 * scrubbed on save; it replaces the previous IPv4Filter + IPv6Filter
 * stage (whose IP-only matches left port suffixes intact) and adds the
 * PZ-specific Steam ID, player-name, and coordinate redaction the generic
 * filters never touched.
 *
 * Codex's IPv4 / IPv6 regexes are generic and apply to non-PZ pastes too;
 * the PZ-specific regexes (Steam ID, player name, coords) mostly no-op on
 * non-PZ content because they rely on PZ-specific anchors (`76561198`,
 * the Steam-ID placeholder, `Combat:` / `Safety:` prefixes, `at` / `[`
 * coord wrappers + trailing PvP verbs).
 *
 * Patterns are encapsulated inside the codex redactor and are not exposed
 * to the client-side preview JS (`getData()` returns an empty array).
 * Server-side redaction on save is the privacy guarantee; the preview is
 * only a UX hint for users about what gets scrubbed.
 */
class ProjectZomboidRedactorFilter extends Filter
{
    public function getType(): FilterType
    {
        return FilterType::REGEX;
    }

    public function getData(): array
    {
        return [];
    }

    public function filter(string $data): string
    {
        return new ProjectZomboidRedactor()->redact($data);
    }
}
