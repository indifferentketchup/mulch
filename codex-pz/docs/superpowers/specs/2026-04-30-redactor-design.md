# Codex Redactor utility — design spec

> Retroactive: written 2026-05-01.
> **Status: implemented on the `redactor` branch (2026-05-01).** Plan: `docs/superpowers/plans/2026-05-01-redactor.md`. Arrival commit set documented in `CHANGELOG.md` `[Unreleased]`. The "Status: deferred" framing below is preserved for historical context; treat this file as the as-built design contract.

## Summary

Codex grows a small utility surface for redacting personally-identifying data from log content before it is stored, displayed, or analysed in environments where preservation of PII is unwanted. The shape is a thin generic interface plus per-game implementations that know each game's log format. iblogs is the primary line of defence (upload-time filter); codex's redactor is the optional helper consumers can call when they want codex itself to scrub data.

## Why deferred

The Phase A Step E open-questions table (Q5) marked the codex-side redactor as "defer to its own session" because the iblogs upload-time filter is the actual privacy boundary — anything codex does in this layer is a convenience, not a guarantee. Phase B (the analyser arc) shipped without the redactor and remains useful: synthetic fixtures use placeholder identifiers throughout, real Logs.zip never reaches the index, and the privacy story for codex's tests does not depend on this utility. Building it remains worthwhile when iblogs starts consuming codex output and wants a one-line option for "scrub before analyse."

## Scope

- **In scope (when this spec is implemented):** a `RedactorInterface` under `src/Util/`, a `ProjectZomboidRedactor` implementation that handles the three PII categories observed in PZ logs (Steam IDs, player names, world coordinates), per-category toggles with a defaults-on stance, replacement-string conventions matching the synthetic fixture placeholders.
- **Out of scope:** non-PZ game redactors (those land alongside their respective game implementations); UI / CLI wrappers; redaction of mod-specific identifiers (e.g. BurdJournals scientific-notation Steam IDs) — handled by an extension of the PZ implementation if/when needed; storage / persistence of redaction maps.

## Architecture

```
                            +-------------------------+
                            |   RedactorInterface     |
                            |   (src/Util/)           |
                            |   redact(string): string|
                            +-----------+-------------+
                                        |
                +-----------------------+-----------------------+
                |                                               |
   +------------v-----------------+              +--------------v-------------+
   | ProjectZomboidRedactor       |              | (Future) MinecraftRedactor |
   | (src/Util/ProjectZomboid/)   |              | (src/Util/Minecraft/)      |
   +------------------------------+              +----------------------------+
```

A thin interface in the framework's `Util` namespace. One concrete implementation per supported game, mirroring the existing components-outer-with-game-suffix layout used everywhere else in the tree (Analyser, Analysis, Detective, Log, Parser, Pattern). Future games' redactors land alongside their analyser surface.

## Why per-game implementations rather than a single regex utility

PII detection in log text is **context-sensitive**, not just regex matching:

- **Steam IDs** are 17-digit decimal numbers. Almost regexable, but care is needed not to chew through unrelated long numbers (timestamps, build numbers, GUIDs that happen to be 17 digits).
- **Player names** are arbitrary strings. They cannot be detected from text alone — a redactor needs to know the lexical contexts where names appear (`<steamid> "Name"`, `ChatMessage{author='Name'}`, `Combat: "Name"`). Without that knowledge a naive `\w+`-style match would shred the entire log.
- **Coordinates** are number triples in specific shapes (`x,y,z` after `at`, `[x,y,z]` between brackets, `(x,y,z)` in PvP combat lines). Stripping every "two commas in a row" regex match would over-redact (e.g. `f:0, t:1776297642406, st:48,648,157,584` is server metadata, not coordinates).

Per-game implementations encode the lexical contexts. PZ's redactor uses the same regex shapes Phase A's Pattern classes encode for parsing, applied in a different direction (replacement instead of extraction).

## Components

### `src/Util/RedactorInterface.php`

```php
namespace IndifferentKetchup\Codex\Util;

interface RedactorInterface
{
    /**
     * Return a copy of $content with PII replaced by placeholder tokens
     * according to the redactor's enabled toggles.
     */
    public function redact(string $content): string;
}
```

A single method. Stateless from the caller's perspective; toggles are configured on the concrete implementation before `redact()` is called.

### `src/Util/ProjectZomboid/ProjectZomboidRedactor.php`

Implements `RedactorInterface`. Three independent toggles (defaults all on) and three regex-driven replacement passes:

```php
namespace IndifferentKetchup\Codex\Util\ProjectZomboid;

use IndifferentKetchup\Codex\Util\RedactorInterface;

class ProjectZomboidRedactor implements RedactorInterface
{
    private bool $redactSteamIds = true;
    private bool $redactPlayerNames = true;
    private bool $redactCoordinates = true;

    public function redactSteamIds(bool $on): static { /* ... */ }
    public function redactPlayerNames(bool $on): static { /* ... */ }
    public function redactCoordinates(bool $on): static { /* ... */ }

    public function redact(string $content): string
    {
        if ($this->redactSteamIds)    { /* preg_replace */ }
        if ($this->redactPlayerNames) { /* preg_replace */ }
        if ($this->redactCoordinates) { /* preg_replace */ }
        return $content;
    }
}
```

### Replacement conventions

To match the synthetic fixture placeholders already used throughout the test suite (per the Privacy / fixture rules in CLAUDE.md):

| PII category | Replacement |
|---|---|
| Steam ID (17 decimal digits in a Steam ID context) | `76561198000000000` |
| Player name (between `"..."` after a 17-digit Steam ID, between `'...'` in `ChatMessage{author='...'}`, between `"..."` after subsystem keywords like `Combat:` / `Safety:`) | `<player>` |
| World coordinates (the `x,y,z` or `(x,y,z)` triples in PZ log lines, distinguished by leading-context anchors so server metadata triples are not stripped) | `0,0,0` |

The replacements are deliberately not reversible — codex makes no attempt to maintain a map between original and redacted values. Reversibility is a different feature scope (encryption / tokenization) and is not what this utility provides.

### Lexical anchors for the regex passes

Steam ID: `(?<![\w])(?P<sid>76561198\d{9})(?![\w])` — the `76561198` prefix matches the SteamID64 universe prefix for Steam (region "Individual"); avoids matching unrelated 17-digit numbers. Boundary classes prevent matching inside a longer alphanumeric token.

Player name (PZ-specific contexts):
- After Steam ID quoted: `(?<sid>76561198000000000) "(?P<name>[^"]+)"` → preserve the redacted Steam ID, replace the quoted name. (Redaction order matters: SIDs first, names second.)
- ChatMessage author: `ChatMessage\{chat=\w+, author='(?P<name>[^']+)',` → replace the captured author.
- PvP / Safety subsystem: `(?P<sub>Combat|Safety): "(?P<name>[^"]+)"` → replace the captured name.

Coordinates:
- ItemLog / MapLog / CmdLog `at` clauses: `at (?P<coords>[\d.]+,[\d.]+,-?[\d.]+)\.` → replace with `0,0,0.`
- ClientActionLog / PerkLog bracketed coords: `\[(?P<coords>\d+,\d+,-?\d+)\]` → replace with `[0,0,0]`
- PvP combat parenthesised coords: `\((?P<coords>\d+,\d+,-?\d+)\) (?:hit|restore|store|true|false)` — the trailing context disambiguates from server metadata triples.

These regex shapes are not yet committed to the spec implementation; tuning is expected during the actual implementation pass against the real `Logs.zip` content under `.scratch/pz/Logs/`.

## Where this fits relative to iblogs

The Phase A Step D Section e split holds: **iblogs is the primary line of defence**. iblogs filters PII at upload time, before storage, mirroring the mclogs IP/token redaction approach. Stored logs in iblogs are pre-sanitised. The codex `Redactor` is the *option* iblogs (or any other consumer) reaches for if they want codex itself to do the scrubbing — for example in a preview pipeline that wants to render redacted output without writing the raw paste to disk first, or in a dev environment where the same code path runs without iblogs's upload filter.

This means the codex Redactor is **non-load-bearing** for the privacy story. iblogs implementing redaction independently is the actual safety guarantee; codex's helper is a convenience.

## Test plan (when implemented)

Synthetic-only fixtures, no real-log content:

1. Three pairs of fixture-input / expected-output strings exercising each category in isolation.
2. One combined-input fixture demonstrating that all three categories applied to the same content produce a fully-scrubbed output.
3. Toggle tests: each of the three booleans turned off in isolation produces partial scrubbing; all three off produces an unchanged copy of input (the redactor returns input verbatim).
4. Idempotence test: `redact(redact($x)) == redact($x)`.
5. A small "negative" test: server metadata triples (`f:0, t:1776297642406, st:48,648,157,584`) are not mistaken for coordinates.

## Open questions

1. **Should the redactor optionally preserve some structure for analysers downstream?** For example, after redaction the analysers can no longer correlate by Steam ID across events because every Steam ID is the same placeholder. Two paths: (a) accept the loss — redaction is done before storage and you don't analyse redacted content, or (b) provide a "tokenizing redactor" that maps each unique input value to a unique placeholder (`76561198000000001`, `76561198000000002`, ...) preserving cardinality. Recommend (a) for v1; (b) is its own design pass.
2. **What about `BurdJournals.txt`'s scientific-notation Steam IDs?** Phase A Step C noted these as `7.656119799341651E16` form. The PZ redactor's Steam ID regex doesn't match this shape. v1 leaves them intact (tag `[BurdJournals]` already disambiguates them as mod-internal). v2 could add a separate regex for the sci-notation form.
3. **Should `coords` redaction try to preserve relative location** (e.g. round to the nearest 1000-tile chunk so the *region* is visible without giving precise base coords)? Out of scope for v1.

## Pointers

- Phase A original Q5 deferral: `2026-04-30-pz-analysers-design.md` referenced this; the explicit deferral lived in chat (Phase A Step E open-questions table).
- iblogs upload-time filtering decisions: see the iblogs bootstrap spec at `2026-05-01-iblogs-bootstrap-design.md`.
- Existing Pattern classes that the regex shapes will mirror in reverse: `src/Pattern/ProjectZomboid/{CmdPattern,ItemPattern,MapPattern,PerkPattern,ClientActionPattern,ChatPattern,PvpPattern,UserPattern}.php`.
