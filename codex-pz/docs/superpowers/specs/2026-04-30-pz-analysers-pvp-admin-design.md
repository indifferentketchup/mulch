# ProjectZomboid analyser design (Phase B.2)

> Retroactive: written 2026-05-01.

## Summary

Add Project Zomboid PvP combat detection (filtering zombie hits and zero-damage events) and admin verb-dispatch coverage of six action types, by registering seven new `Information` insight classes onto the existing `PatternAnalyser`. No custom `Analyser` subclasses are introduced in this phase — all dispatch fits within `PatternAnalyser`'s per-entry pattern matching.

This document covers Phase B.2. Phase B.1 is in `2026-04-30-pz-analysers-design.md`. Phase B.3 (cross-entry / threshold analysers requiring custom `Analyser` subclasses) is in `2026-04-30-pz-analysers-deferred-design.md`.

## Scope

- **In scope:** `PvpDamageInformation` + `PvpPattern::COMBAT_REAL` regex; six `Admin<Verb>Information` classes + six `AdminPattern::<VERB>_ENTRY` regex constants; wiring `ProjectZomboidPvpLog::getDefaultAnalyser()` and `ProjectZomboidAdminLog::getDefaultAnalyser()`; end-to-end tests for both logs.
- **Out of scope (B.2):** any cross-entry / threshold / pairing logic (deferred to B.3); the eight other PZ logs whose `getDefaultAnalyser()` continues returning an empty `PatternAnalyser` stub; the codex-side `Redactor` utility (deferred — see `2026-04-30-redactor-design.md`).

## Architectural decision: vanilla PatternAnalyser

Phase B.1 established that `PatternAnalyser` plus `Insight::isEqual()` coalescing covers single-entry pattern matching cleanly. Phase B.2's analysers (PvP damage rows, admin verb lines) all fit that mould — each interesting line is independent of the others, dispatch is per-entry, and counter-coalescing handles repeats. No `Analyser` subclassing required. (Phase B.3 will deviate from this when cross-entry logic enters the picture.)

## Components

All under `src/Analysis/ProjectZomboid/`:

| Class | Type | Pattern | Coalescing |
|---|---|---|---|
| `PvpDamageInformation` | Information | `PvpPattern::COMBAT_REAL` | Default `Information::isEqual` (label + value) — same attacker/victim/weapon coalesces |
| `AdminAddedItemInformation` | Information | `AdminPattern::ADDED_ITEM_ENTRY` | Default — same admin/item/target coalesces |
| `AdminAddedXpInformation` | Information | `AdminPattern::ADDED_XP_ENTRY` | Default — same admin/amount/skill/target coalesces |
| `AdminGrantedAccessInformation` | Information | `AdminPattern::GRANTED_ACCESS_ENTRY` | Default — same admin/level/target coalesces |
| `AdminChangedOptionInformation` | Information | `AdminPattern::CHANGED_OPTION_ENTRY` | Default — same admin/option/value coalesces |
| `AdminReloadedOptionsInformation` | Information | `AdminPattern::RELOADED_OPTIONS_ENTRY` | Default — same admin coalesces |
| `AdminTeleportedInformation` | Information | `AdminPattern::TELEPORTED_ENTRY` | Default — same admin/target/coords coalesces |

## Patterns

Seven new constants total.

**`PvpPattern::COMBAT_REAL`** — combat regex with the noise filter baked in. The negative lookahead `(?!zombie")` rejects zombie weapon rows; the damage clause uses alternation to match only positive non-zero floats:

```
'/Combat: "(?<attacker>[^"]+)" \([^)]+\) hit "(?<victim>[^"]+)" \([^)]+\) weapon="(?<weapon>(?!zombie")[^"]+)" damage=(?<damage>0\.0*[1-9][0-9]*|[1-9][0-9]*\.[0-9]+)/'
```

The damage alternation explicitly rejects `0.000000` and any leading-minus value because both branches require either `0.<non-zero>` or `<non-zero>.<digits>`.

**`AdminPattern::<VERB>_ENTRY`** — six entry-anchored variants of the existing body-only verb constants. Necessary because `PatternAnalyser` calls `preg_match_all` against the full Entry text (including the `[time]` prefix), so the Phase A verb constants anchored at `^<admin>` would never match. The Phase A constants stay intact for direct-message use; new ones live alongside them on the same `AdminPattern` class.

## Wiring

Two `getDefaultAnalyser()` overrides (was `return new PatternAnalyser();` for both):

```php
// ProjectZomboidPvpLog
return (new PatternAnalyser())
    ->addPossibleInsightClass(PvpDamageInformation::class);
```

```php
// ProjectZomboidAdminLog
return (new PatternAnalyser())
    ->addPossibleInsightClass(AdminAddedItemInformation::class)
    ->addPossibleInsightClass(AdminAddedXpInformation::class)
    ->addPossibleInsightClass(AdminGrantedAccessInformation::class)
    ->addPossibleInsightClass(AdminChangedOptionInformation::class)
    ->addPossibleInsightClass(AdminReloadedOptionsInformation::class)
    ->addPossibleInsightClass(AdminTeleportedInformation::class);
```

## Test plan

Unit tests under `test/tests/Games/ProjectZomboid/Analysis/`, one per Insight class — exercises `getPatterns()` shape, `setMatches()` extraction, and at least one filter-rejection case for `PvpDamageInformation` (zombie weapon and zero-damage rejection).

End-to-end tests under `test/tests/Games/ProjectZomboid/Analyser/`:

- `PvpLogAnalysisTest` against `pvp-minimal.txt`: asserts exactly three `PvpDamageInformation` insights (Bare Hands, Tire Iron (Worn), Hunting Knife). Zombie and vehicle rows must be filtered out by the regex.
- `AdminLogAnalysisTest` against `admin-minimal.txt`: asserts 2 + 2 + 2 + 2 + 1 + 2 = 11 insights across the six admin classes, with the duplicate ShotgunShells row coalescing into a single insight at `counter == 2`.

## Fixture changes

None. The Phase A synthetic fixtures `pvp-minimal.txt` and `admin-minimal.txt` already cover every code path Phase B.2 exercises.

## Commits (as-built, in order)

1. `df62da1` — `pre-phase-B.2 checkpoint` (`--allow-empty`)
2. `55f769c` — `Add PvpDamageInformation insight`
3. `90c85a0` — `Add AdminAddedItemInformation insight` ⚠️ broken — see `2026-04-30-pz-analysers-pvp-admin.md` §Deviations
4. `0d85a05` — `Fix missing closing brace in AdminPattern` (forward-fix for #3)
5. `a2faa55` — `Add AdminAddedXpInformation insight`
6. `caed04d` — `Add AdminGrantedAccessInformation insight`
7. `b7b89ef` — `Add AdminChangedOptionInformation insight`
8. `64641fa` — `Add AdminReloadedOptionsInformation insight`
9. `d15fc81` — `Add AdminTeleportedInformation insight`
10. `51eb2de` — `Wire ProjectZomboidPvpLog default analyser`
11. `c57d646` — `Wire ProjectZomboidAdminLog default analyser`

11 commits total, vs 10 originally planned. The brace-fix commit accounts for the discrepancy.

## Open issues

None blocking. Phase A Q4 (admin verb scope) was settled before B.2 began. Phase B Q2 confirmed PvP fixtures contain real combat events worth analysing.

## Pointers

- Phase B.1 (foundation): `2026-04-30-pz-analysers-design.md` and `2026-04-30-pz-analysers.md`.
- Phase B.3 (deferred analysers requiring custom `Analyser` subclasses): `2026-04-30-pz-analysers-deferred-design.md`.
- Workflow conventions: `CLAUDE.md` § Workflow conventions and § Pitfalls.
