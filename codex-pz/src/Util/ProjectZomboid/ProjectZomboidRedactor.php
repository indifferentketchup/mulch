<?php

namespace IndifferentKetchup\CodexPz\Util\ProjectZomboid;

use IndifferentKetchup\CodexPz\Util\RedactorInterface;

/**
 * Render-time PII filter for Project Zomboid log content.
 *
 * Applies up to four sequential regex passes over the raw log string,
 * each controlled by a boolean toggle (all enabled by default):
 *
 *   1. IP address pass — replaces IPv4 addresses (with optional :port
 *      suffix) and IPv6 addresses (full, abbreviated, bracketed, and
 *      IPv4-mapped forms; all with optional :port when bracketed) with
 *      a placeholder token. Pattern-disjoint from the other passes.
 *   2. Steam ID pass    — replaces 17-digit Steam IDs with a placeholder
 *      token.
 *   3. Player name pass — replaces player display names with a placeholder
 *      token. This pass anchors on the already-redacted Steam ID token, so
 *      the ordering Steam ID -> name -> coordinates is mandatory.
 *   4. Coordinates pass — replaces world coordinate triplets with a
 *      placeholder token.
 *
 * Pass 1 runs first by convention, not dependency: it shares no anchors
 * with passes 2-4 and could run anywhere in the chain without affecting
 * their output.
 *
 * All regex passes use the /u flag for Unicode safety.
 *
 * Replacements are not reversible; do not apply to content that must later be
 * restored to its original form.
 */
class ProjectZomboidRedactor implements RedactorInterface
{
    /** Generic placeholder substituted for every matched IPv4 or IPv6 address (with port suffix consumed when present). */
    public const string IP_REPLACEMENT = '[REDACTED_IP]';

    /** Strict IPv4 with valid 0-255 octets and optional :port suffix. Lookarounds reject matches embedded in longer alphanumeric or dotted-decimal tokens; the (?<!\d\.) / (?!\.\d) pair specifically prevents matching inside an N-octet (N>4) sequence like 1.2.3.4.5 while still allowing a trailing sentence period after the IP/port. */
    public const string IPV4_REGEX = '/'
        . '(?<![A-Za-z0-9_:])(?<!\d\.)'
        . '(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)'
        . '(?:\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)){3}'
        . '(?::\d{1,5})?'
        . '(?![A-Za-z0-9_:])(?!\.\d)'
        . '/u';

    /** Coarse IPv6 candidate matcher (bracketed-with-port, or bare 2-7-colon hex form covering full / abbreviated / IPv4-mapped). Each match is validated with filter_var() in the redact() callback so PHP/Java scope ops like Foo::Bar and PZ timestamps like 12:00:00.000 are rejected. Boundary lookarounds mirror the IPv4 regex so trailing sentence periods don't block the match. */
    public const string IPV6_REGEX = '/'
        . '(?<![A-Za-z0-9_:])(?<!\d\.)'
        . '(?:'
        . '\[(?<bracketed>[0-9a-fA-F:.]+)\](?::\d{1,5})?'
        . '|'
        . '(?<bare>(?:[0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F.]*)'
        . ')'
        . '(?![A-Za-z0-9_:])(?!\.\d)'
        . '/u';

    /** Regex matching a 17-digit SteamID64 in any of the three individual-account universe prefixes (76561197, 76561198, 76561199), with lookaround boundaries that reject embedded occurrences. All three prefixes appear in production logs; the old pattern only covered 76561198 and missed ~46% of real IDs. */
    public const string STEAM_ID_REGEX = '/(?<![A-Za-z0-9])7656119[789]\d{9}(?![A-Za-z0-9])/u';

    /** Zeroed-out SteamID64 placeholder; syntactically valid but refers to no real account. */
    public const string STEAM_ID_REPLACEMENT = '76561198000000000';

    /** Generic placeholder substituted for every matched player display name. */
    public const string PLAYER_NAME_REPLACEMENT = '<player>';

    /** Matches a double-quoted player name that immediately follows either the redacted Steam ID placeholder or a raw individual-account SteamID64 (any of the three covered universe prefixes: 76561197/8/9) in the cmd.txt / admin.txt shape. The first capture group preserves the preceding Steam ID / placeholder so it survives the replacement. This pattern is intentionally independent of the Steam ID pass having fired on that exact token: it redacts the player name whether or not the adjacent ID was already replaced. */
    public const string PLAYER_AFTER_STEAMID_REGEX = '/(76561198000000000|7656119[789]\d{9}) "([^"]+)"/u';

    /** Matches the author value inside a ChatMessage{...} envelope, using a fixed-length lookbehind on ", author='" and a lookahead on the closing "'" so only the bare name is replaced. */
    public const string PLAYER_IN_CHATMESSAGE_REGEX = '/(?<=, author=\')(?<name>[^\']+)(?=\')/u';

    /** Matches the first double-quoted player name following a Combat: or Safety: subsystem token (pvp.txt shape); does NOT redact the second name after "hit" — deferred to v2. */
    public const string PLAYER_IN_PVP_SUBSYSTEM_REGEX = '/(?<=(?:Combat|Safety): )"(?<name>[^"]+)"/u';

    /** Zeroed-out coordinate triple used as the inner replacement; bracket/paren/`at` wrapper is preserved by the regex lookaround anchors. */
    public const string COORDS_REPLACEMENT = '0,0,0';

    /** Matches integer or float coordinate triplets that immediately follow the literal ` at ` token (map.txt / item.txt shape); the trailing dot is preserved via lookahead. */
    public const string COORDS_AT_CLAUSE_REGEX = '/(?<= at )(?<x>[\d.]+),(?<y>[\d.]+),(?<z>-?[\d.]+)(?=\.)/u';

    /** Matches integer coordinate triplets enclosed in square brackets (ClientActionLog.txt / PerkLog.txt / cmd.txt @-context shape); the surrounding brackets are preserved via lookaround. */
    public const string COORDS_BRACKETED_REGEX = '/(?<=\[)(?<x>\d+),(?<y>\d+),(?<z>-?\d+)(?=\])/u';

    /** Matches integer coordinate triplets enclosed in round parentheses, anchored on a trailing PvP verb to disambiguate from server-metadata triples (pvp.txt Combat:/Safety: shape); only the attacker/first-coord set is redacted per line — the victim coords lack the trailing keyword and are deferred to v2. */
    public const string COORDS_PARENTHESISED_REGEX = '/(?<=\()(?<x>\d+),(?<y>\d+),(?<z>-?\d+)(?=\) (?:hit|restore|store|true|false))/u';

    private bool $redactIpAddresses = true;
    private bool $redactSteamIds = true;
    private bool $redactPlayerNames = true;
    private bool $redactCoordinates = true;

    /**
     * Enable or disable the IP address redaction pass (covers IPv4 and IPv6).
     *
     * @param bool $on Pass true to enable, false to disable.
     * @return static
     */
    public function redactIpAddresses(bool $on): static
    {
        $this->redactIpAddresses = $on;
        return $this;
    }

    /**
     * Enable or disable the Steam ID redaction pass.
     *
     * @param bool $on Pass true to enable, false to disable.
     * @return static
     */
    public function redactSteamIds(bool $on): static
    {
        $this->redactSteamIds = $on;
        return $this;
    }

    /**
     * Enable or disable the player-name redaction pass.
     *
     * @param bool $on Pass true to enable, false to disable.
     * @return static
     */
    public function redactPlayerNames(bool $on): static
    {
        $this->redactPlayerNames = $on;
        return $this;
    }

    /**
     * Enable or disable the coordinates redaction pass.
     *
     * @param bool $on Pass true to enable, false to disable.
     * @return static
     */
    public function redactCoordinates(bool $on): static
    {
        $this->redactCoordinates = $on;
        return $this;
    }

    /**
     * Redact PII from the given Project Zomboid log content.
     *
     * Passes are applied in the order: IP address -> Steam ID -> player
     * name -> coordinates. The Steam ID -> name -> coordinates ordering
     * is mandatory (see class docblock); the IP pass is pattern-disjoint
     * and runs first by convention.
     *
     * @param string $content Raw log content that may contain PII.
     * @return string Content with enabled PII categories replaced by tokens.
     */
    public function redact(string $content): string
    {
        if ($this->redactIpAddresses) {
            $content = preg_replace_callback(
                self::IPV6_REGEX,
                static function (array $matches): string {
                    $candidate = ($matches['bracketed'] ?? '') !== ''
                        ? $matches['bracketed']
                        : ($matches['bare'] ?? '');
                    return filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
                        ? self::IP_REPLACEMENT
                        : $matches[0];
                },
                $content
            );
            $content = preg_replace(self::IPV4_REGEX, self::IP_REPLACEMENT, $content);
        }
        if ($this->redactSteamIds) {
            $content = preg_replace(self::STEAM_ID_REGEX, self::STEAM_ID_REPLACEMENT, $content);
        }
        if ($this->redactPlayerNames) {
            $content = preg_replace(self::PLAYER_AFTER_STEAMID_REGEX, '$1 "' . self::PLAYER_NAME_REPLACEMENT . '"', $content);
            $content = preg_replace(self::PLAYER_IN_CHATMESSAGE_REGEX, self::PLAYER_NAME_REPLACEMENT, $content);
            $content = preg_replace(self::PLAYER_IN_PVP_SUBSYSTEM_REGEX, '"' . self::PLAYER_NAME_REPLACEMENT . '"', $content);
        }
        if ($this->redactCoordinates) {
            $content = preg_replace(self::COORDS_AT_CLAUSE_REGEX, self::COORDS_REPLACEMENT, $content);
            $content = preg_replace(self::COORDS_BRACKETED_REGEX, self::COORDS_REPLACEMENT, $content);
            $content = preg_replace(self::COORDS_PARENTHESISED_REGEX, self::COORDS_REPLACEMENT, $content);
        }
        return $content;
    }
}
