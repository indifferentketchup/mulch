# ProjectZomboid analyser design (Phase B.3 — deferred analysers)

> Retroactive: written 2026-05-01.

## Summary

Add the three remaining Project Zomboid analysers from the original Step D candidate list — connection failure pairing, item duplication heuristic, and skill progression anomaly detection — by introducing custom `Analyser` subclasses under `src/Analyser/ProjectZomboid/`. These are the first analysers in the tree that cannot be expressed as configured `PatternAnalyser` instances; they require cross-entry state (event pairing, sliding windows, snapshot deltas) that `PatternAnalyser` does not provide.

This document covers Phase B.3. Phase B.1 / B.2 docs are at `2026-04-30-pz-analysers-design.md` / `2026-04-30-pz-analysers-pvp-admin-design.md`. With Phase B.3, the original eight-analyser candidate list from Step D is fully implemented.

## Scope

- **In scope:** `ConnectionFailureAnalyser` + `ConnectionFailureProblem` (UserLog, event pairing); `ItemDuplicationAnalyser` + `ItemDuplicationProblem` (ItemLog, sliding-window heuristic); `SkillProgressionAnomalyAnalyser` + `SkillProgressionAnomalyProblem` (PerkLog, consecutive-snapshot delta); wiring three Log subclasses' `getDefaultAnalyser()`; extending two synthetic fixtures to exercise trigger and non-trigger cases; end-to-end tests.
- **Out of scope (B.3):** the five other PZ logs whose `getDefaultAnalyser()` continues returning an empty `PatternAnalyser` stub (Chat, ClientAction, Cmd, Map, BurdJournals); the codex-side `Redactor` utility; Hytale / Minecraft / Seven Days To Die analysers; v0.1.0 release plumbing.

## Architectural shift: custom `Analyser` subclasses

Phases B.1 and B.2 established the convention that vanilla `PatternAnalyser` plus `Insight::isEqual()` coalescing is sufficient for per-entry pattern matching, and a custom Analyser subclass is **not** needed even for multi-line records (PatternParser's continuation-line behaviour combined with `Entry::__toString()` joins solves multi-line capture without subclassing).

Phase B.3's three analysers genuinely require cross-entry state:

- **ConnectionFailureAnalyser** must count `attempting to join` and `allowed to join` events per Steam ID and report unmatched attempts. PatternAnalyser dispatches each entry independently and has no mechanism to compare counts across entries.
- **ItemDuplicationAnalyser** must group positive-delta item events by `(steamid, item)` tuple and slide a fixed-second window across each group. Sliding-window logic spans multiple entries by definition.
- **SkillProgressionAnomalyAnalyser** must collect all perks-row snapshots per Steam ID, sort them by time, then compute pairwise deltas between consecutive snapshots. Pairwise comparison spans entries.

Each subclass extends the framework's abstract `Analyser`, overrides `analyse(): AnalysisInterface`, walks `$this->log` once to aggregate state, and emits `Problem` insights at the end. The CLAUDE.md "Framework architecture" section was updated alongside Phase B.3 to document this pattern.

## Components

Three `Analyser` subclasses under `src/Analyser/ProjectZomboid/` (the directory's `.gitkeep` placeholder is removed in this phase):

| Analyser | Target Log | Logic shape | Threshold constants |
|---|---|---|---|
| `ConnectionFailureAnalyser` | `ProjectZomboidUserLog` | Two-pass count of attempt vs allowed events per Steam ID; emits one Problem per Steam ID where attempts > allowed | None — strict pairing |
| `ItemDuplicationAnalyser` | `ProjectZomboidItemLog` | Sliding-window heuristic over `(steamid, item)` groups | `THRESHOLD_COUNT = 5`, `THRESHOLD_WINDOW_SECONDS = 10` |
| `SkillProgressionAnomalyAnalyser` | `ProjectZomboidPerkLog` | Consecutive-snapshot delta per `(steamid, skill)`; only positive-delta perks-row entries (Login/Logout/LevelUp event tokens are filtered out) | `THRESHOLD_DELTA = 3` |

Three `Problem` subclasses under `src/Analysis/ProjectZomboid/`:

| Problem | Coalescing |
|---|---|
| `ConnectionFailureProblem` | By Steam ID — one problem per player regardless of how many unmatched attempts |
| `ItemDuplicationProblem` | By `(steamid, item)` tuple — one problem per suspicious group |
| `SkillProgressionAnomalyProblem` | By `(steamid, skill)` — one problem per skill exceeding the delta threshold |

## Threshold rationale (recorded as docblocks)

The constants are first-pass heuristics expected to be tuned once production logs flow through codex. Each is documented inline in its analyser class:

- **`ItemDuplicationAnalyser::THRESHOLD_COUNT = 5`**: Five identical item gains in a fixed window. Legitimate gameplay rarely produces five identical items quickly — crafting has animation delays, looting is one-at-a-time, zombie drops are similarly serial. A burst of five suggests admin-spawn or exploit. Tune downward if false negatives appear.
- **`ItemDuplicationAnalyser::THRESHOLD_WINDOW_SECONDS = 10`**: Ten seconds covers a realistic burst-loot scenario (e.g. a crate full of identical items) without collapsing onto unrelated events. Combined with `THRESHOLD_COUNT` this means an effective rate of 0.5 same-item events per second.
- **`SkillProgressionAnomalyAnalyser::THRESHOLD_DELTA = 3`**: PZ skills require thousands of XP per level; even active grinding rarely produces four-or-more level jumps in a single session bridge. Set to 3 as baseline; modded XP servers may need to raise this via subclass override.

## Patterns

No new pattern constants. Existing constants from Phase A are reused inside the per-entry walks:

- `UserPattern::PLAYER_EVENT` — decode `[time] <steamid> "<player>" <event>` lines
- `ItemPattern::FIELDS` — decode `[time] <steamid> "<player>" <location> <delta> <coords> [<item>]` lines
- `PerkPattern::FIELDS` — decode the bracket-heavy perks log line
- `PerkPattern::PERK_PAIR` — extract individual `Skill=N` pairs from the perks-row event field

`Entry::getTime()` returns integer Unix seconds (sub-second precision is dropped by `DateTime::getTimestamp()`). For `ItemDuplicationAnalyser` this means events within the same second collapse to time-diff zero, which is acceptable for v1.

## Wiring

Three `getDefaultAnalyser()` overrides (each was previously `return new PatternAnalyser();`):

```php
// ProjectZomboidUserLog
return new ConnectionFailureAnalyser();

// ProjectZomboidItemLog
return new ItemDuplicationAnalyser();

// ProjectZomboidPerkLog
return new SkillProgressionAnomalyAnalyser();
```

The unused `PatternAnalyser` import is removed from each Log subclass.

## Test plan

End-to-end tests under `test/tests/Games/ProjectZomboid/Analyser/`, one per Log:

- **`UserLogAnalysisTest`** — drives `user-minimal.txt`. Asserts exactly one `ConnectionFailureProblem` for Player1 (Steam ID `76561198000000001`) with `unmatchedAttempts == 1` (Player1 has two `attempting to join` events, one of which is `attempting to join used queue`, and one `allowed to join`). Asserts that Player2 (matched 1+1) is not flagged.
- **`ItemLogAnalysisTest`** — drives the extended `item-minimal.txt`. Asserts one `ItemDuplicationProblem` for AdminUser + Base.Bullets9mm with `eventCount == 6`, and verifies the four-event Base.Plank group does not trigger. Also asserts the threshold constants are positive and documented.
- **`PerkLogAnalysisTest`** — drives the extended `perk-minimal.txt`. Asserts exactly two `SkillProgressionAnomalyProblem` insights for PlayerSuspect (Steam ID `76561198000000004`), one for Strength (delta +8) and one for Fitness (delta +6). Verifies that Maintenance (delta exactly +3) does not trigger because the comparison is strict `>`. Verifies that single-snapshot players (Player1, Player2) are not flagged. Asserts the threshold constant is positive and documented.

## Fixture changes

Two synthetic fixtures extended (no new files, no real-log content):

- **`item-minimal.txt`** — appended 10 lines: a 6-event Bullets9mm burst by AdminUser at sub-second timestamps `19:50:00.001`–`.006` (triggers the dupe heuristic), and a 4-event Plank group by Player1 scattered across 4 minutes (`20:00:00`–`20:03:00`, sub-threshold). The Phase A entry-count assertion in `ProjectZomboidItemLogTest` was bumped from 10 → 20.
- **`perk-minimal.txt`** — appended 4 lines: PlayerSuspect (Steam ID `76561198000000004`) with two perks snapshots — a low-stat baseline at `18:30:00.000` and an inflated set at `22:00:00.000` showing Strength 2→10, Fitness 2→8, and Maintenance 0→3 (boundary case). The Phase A entry-count assertion in `ProjectZomboidPerkLogTest` was bumped from 6 → 10.

All identifiers are placeholder per the Privacy / Fixture Rules in CLAUDE.md (`76561198000000001`–`76561198000000004` for Steam IDs, `Player1`/`Player2`/`AdminUser`/`PlayerSuspect` for names, coords in the `1000-1100, 2000-2200, 0` range).

## Commits (as-built, in order)

1. `c444e85` — `pre-phase-B.3 checkpoint` (`--allow-empty`)
2. `73e9ca6` — `Add ConnectionFailureAnalyser`
3. `ba3fae8` — `Add ItemDuplicationAnalyser`
4. `0c90e40` — `Add SkillProgressionAnomalyAnalyser`

4 commits total. Each non-checkpoint commit ships an Analyser + Problem + (optional) fixture extension + updated count assertion + e2e test in one logical unit, per the per-analyser commit shape requested up front.

## Open issues

None blocking. All three threshold constants are heuristic guesses pending production data calibration; tuning is expected once iblogs starts feeding real logs through codex. The values are tunable via subclass override and the rationale is in the source docblocks.

## Pointers

- Phase B.1 (foundation, ServerLog analysers): `2026-04-30-pz-analysers-design.md` and `2026-04-30-pz-analysers.md`.
- Phase B.2 (vanilla PatternAnalyser PvP/Admin coverage): `2026-04-30-pz-analysers-pvp-admin-design.md` and `2026-04-30-pz-analysers-pvp-admin.md`.
- Workflow conventions and architecture overview: `CLAUDE.md`.
- The Phase B.3 commit set begins at `c444e85` (pre-checkpoint) and ends at `0c90e40` (the third analyser).
