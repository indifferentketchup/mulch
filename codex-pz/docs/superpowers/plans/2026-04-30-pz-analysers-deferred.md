# ProjectZomboid Phase B.3 Deferred Analysers — As-Built Plan

> Retroactive: written 2026-05-01.

This document is a historical record of how Phase B.3 (the three deferred analysers from the original Step D candidate list) was implemented. The corresponding design spec is `docs/superpowers/specs/2026-04-30-pz-analysers-deferred-design.md`. The work is complete and merged to `master`; checkboxes are pre-checked.

**Goal:** Land three custom `Analyser` subclasses under `src/Analyser/ProjectZomboid/` (the first non-empty contents of that directory), three `Problem` subclasses under `src/Analysis/ProjectZomboid/`, threshold constants documented inline as `public const`, fixture extensions to exercise trigger and non-trigger paths, and e2e tests verifying the analysers' behaviour against the fixtures.

**Architecture:** Custom subclasses of the framework's abstract `Analyser`. Each overrides `analyse()` to walk `$this->log` once, aggregate cross-entry state, and emit coalesced `Problem` insights at the end. This is the first deviation from Phase B.1/B.2's vanilla-`PatternAnalyser` pattern; the reasoning is recorded in the design spec and in `CLAUDE.md`.

**Tech Stack:** PHP 8.4+, PHPUnit 12, Composer (root package: `indifferentketchup/codex`). PHP/Composer not installed on host — all command invocations wrap in `docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest …`.

---

## Tasks

### Task 0 — Pre-checkpoint

- [x] Empty checkpoint commit: `c444e85 pre-phase-B.3 checkpoint`

### Task 1 — `ConnectionFailureAnalyser` (UserLog)

Pairing logic: walk the log, count `attempting to join` and `allowed to join` events per Steam ID, emit a `ConnectionFailureProblem` for any Steam ID whose attempt count exceeds its allowed count.

- [x] Add `src/Analysis/ProjectZomboid/ConnectionFailureProblem.php` (Steam ID, player, unmatched count; `isEqual` coalesces by Steam ID)
- [x] Add `src/Analyser/ProjectZomboid/ConnectionFailureAnalyser.php` — first file in this directory; the `.gitkeep` placeholder is removed in this commit
- [x] Wire `ProjectZomboidUserLog::getDefaultAnalyser()` to return `new ConnectionFailureAnalyser()` and drop the now-unused `PatternAnalyser` import
- [x] Add `test/tests/Games/ProjectZomboid/Analyser/UserLogAnalysisTest.php` — asserts Player1 (`76561198000000001`) is flagged with `unmatchedAttempts == 1` and Player2 (`76561198000000002`) is not flagged
- [x] `composer test` green: 188 tests, 392 assertions
- [x] Commit: `73e9ca6 Add ConnectionFailureAnalyser`

Design note inside the analyser docblock: "attempting to join used queue" rows are surfaced as failures in v1 because a long queue wait is indistinguishable from a real failure without timing context. Tunable in v2 if false positives become noisy.

### Task 2 — `ItemDuplicationAnalyser` (ItemLog)

Sliding-window heuristic over `(steamid, item)` groups, restricted to positive-delta events. Negative-delta rows (drops/transfers) are filtered out.

- [x] Add `src/Analysis/ProjectZomboid/ItemDuplicationProblem.php` (Steam ID, player, item, event count; `isEqual` coalesces by `(steamid, item)`)
- [x] Add `src/Analyser/ProjectZomboid/ItemDuplicationAnalyser.php` with two threshold constants and rationale docblocks: `THRESHOLD_COUNT = 5`, `THRESHOLD_WINDOW_SECONDS = 10`
- [x] Wire `ProjectZomboidItemLog::getDefaultAnalyser()` to return `new ItemDuplicationAnalyser()`; drop unused `PatternAnalyser` import
- [x] Extend `test/src/Games/ProjectZomboid/fixtures/item-minimal.txt`: append 6 Bullets9mm events at sub-second timestamps `19:50:00.001`–`.006` for AdminUser (trigger), plus 4 Plank events scattered `20:00:00`–`20:03:00` for Player1 (sub-threshold)
- [x] Bump entry-count assertion in `ProjectZomboidItemLogTest::testParsesEachLineAsAnEntry`: 10 → 20
- [x] Add `test/tests/Games/ProjectZomboid/Analyser/ItemLogAnalysisTest.php` — asserts one `ItemDuplicationProblem` (AdminUser + Bullets9mm + 6 events), zero for the Plank group, and the threshold constants are positive
- [x] `composer test` green: 191 tests, 400 assertions
- [x] Commit: `ba3fae8 Add ItemDuplicationAnalyser`

Implementation note: the analyser uses a two-pointer sliding window per group, which is O(n) per group after the initial sort. `Entry::getTime()` returns integer Unix seconds (sub-second precision dropped); the burst events all collapse to the same Unix-second value so any positive window catches them.

### Task 3 — `SkillProgressionAnomalyAnalyser` (PerkLog)

Compare consecutive perks-snapshot rows per Steam ID; emit a problem for any single skill that gained more than `THRESHOLD_DELTA` levels between snapshots.

- [x] Add `src/Analysis/ProjectZomboid/SkillProgressionAnomalyProblem.php` (Steam ID, player, skill, fromLevel, toLevel, delta; `isEqual` coalesces by `(steamid, skill)`)
- [x] Add `src/Analyser/ProjectZomboid/SkillProgressionAnomalyAnalyser.php` with `THRESHOLD_DELTA = 3` and a rationale docblock about PZ's slow skill leveling
- [x] Wire `ProjectZomboidPerkLog::getDefaultAnalyser()` to return `new SkillProgressionAnomalyAnalyser()`; drop unused `PatternAnalyser` import
- [x] Extend `test/src/Games/ProjectZomboid/fixtures/perk-minimal.txt`: append PlayerSuspect (Steam ID `76561198000000004`) with two snapshots — Strength 2→10 (+8 trigger), Fitness 2→8 (+6 trigger), Maintenance 0→3 (+3 boundary, does not trigger because comparison is strict `>`)
- [x] Bump entry-count assertion in `ProjectZomboidPerkLogTest::testParsesEachLineAsAnEntry`: 6 → 10
- [x] Add `test/tests/Games/ProjectZomboid/Analyser/PerkLogAnalysisTest.php` — asserts exactly two problems for PlayerSuspect (Strength + Fitness, sorted), no problem for Maintenance, no problems for single-snapshot Player1/Player2, and the threshold constant is positive
- [x] `composer test` green: 195 tests, 412 assertions
- [x] Commit: `0c90e40 Add SkillProgressionAnomalyAnalyser`

Filtering note: the analyser skips event-token rows (`Login`, `Logout`, `LevelUp`) by checking that the bracketed event field contains a `Skill=N` pair via `PerkPattern::PERK_PAIR`. Only true perks-snapshot rows enter the comparison.

---

## Done condition (met)

After Task 3, `composer test` reports **195 tests, 412 assertions, all green** under PHPUnit 12.5.6 / PHP 8.5.5. All eight Step D candidate analysers (Phase B.1's three ServerLog + Phase B.2's seven PvP/Admin + Phase B.3's three deferred) are operational across their respective Log subclasses.

The directory `src/Analyser/ProjectZomboid/` now contains real code for the first time; its `.gitkeep` placeholder was removed in `73e9ca6`.

## Deviations from the original plan

None this phase. The 4-commit count and the per-analyser shape both match what was committed-to in chat before execution. No silent breakages, no missing closing braces. The only observation worth recording is that the planned commit count was inclusive of the pre-checkpoint, and the actual commit ordering matched the plan exactly.
