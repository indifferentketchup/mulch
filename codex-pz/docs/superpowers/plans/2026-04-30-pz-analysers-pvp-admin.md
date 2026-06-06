# ProjectZomboid Phase B.2 Analysers — As-Built Plan

> Retroactive: written 2026-05-01.

This document is a historical record of how Phase B.2 (PvP combat detection + admin verb dispatch) was implemented. The corresponding design spec is `docs/superpowers/specs/2026-04-30-pz-analysers-pvp-admin-design.md`. The work is complete and merged to `master`; checkboxes are pre-checked.

**Goal:** Land seven new `Information` insight classes (one for PvP combat, six for admin verbs) under `src/Analysis/ProjectZomboid/`, plus seven new pattern constants on `PvpPattern` / `AdminPattern`, then wire `ProjectZomboidPvpLog` and `ProjectZomboidAdminLog` default analysers to register them.

**Architecture:** Vanilla `PatternAnalyser` configured with the new insight classes. No custom `Analyser` subclasses (deferred to Phase B.3). `Entry::__toString()` joins lines with `\n`, but B.2 logs are single-line per entry so multi-line behaviour doesn't apply here.

**Tech Stack:** PHP 8.4+, PHPUnit 12, Composer (root package: `indifferentketchup/codex`). PHP/Composer not installed on host — all command invocations wrap in `docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest …`.

---

## Tasks

### Task 0 — Pre-checkpoint

- [x] Empty checkpoint commit: `df62da1 pre-phase-B.2 checkpoint`

### Task 1 — `PvpDamageInformation` + `PvpPattern::COMBAT_REAL`

- [x] Add `PvpPattern::COMBAT_REAL` constant (combat regex with negative lookahead on weapon and positive-non-zero damage clause)
- [x] Add `src/Analysis/ProjectZomboid/PvpDamageInformation.php`
- [x] Add `test/tests/Games/ProjectZomboid/Analysis/PvpDamageInformationTest.php` covering pattern shape, match extraction, and three rejection cases (zombie weapon, zero damage, negative damage)
- [x] `composer test` green: 167 tests, 343 assertions
- [x] Commit: `55f769c Add PvpDamageInformation insight`

### Task 2 — `AdminAddedItemInformation` + `AdminPattern::ADDED_ITEM_ENTRY`

- [x] Add `AdminPattern::ADDED_ITEM_ENTRY` constant (entry-anchored variant; the body-only `ADDED_ITEM` from Phase A stays in place)
- [x] Add `src/Analysis/ProjectZomboid/AdminAddedItemInformation.php`
- [x] Add `test/tests/Games/ProjectZomboid/Analysis/AdminAddedItemInformationTest.php`
- [x] Commit: `90c85a0 Add AdminAddedItemInformation insight` — **see Deviations section below**
- [x] Forward-fix: `0d85a05 Fix missing closing brace in AdminPattern`
- [x] `composer test` green after forward-fix: 170 tests

### Task 3 — `AdminAddedXpInformation` + `ADDED_XP_ENTRY`

- [x] Add `AdminPattern::ADDED_XP_ENTRY` constant
- [x] Add `src/Analysis/ProjectZomboid/AdminAddedXpInformation.php`
- [x] Unit test
- [x] `composer test` green: 173 tests
- [x] Commit: `a2faa55 Add AdminAddedXpInformation insight`

### Task 4 — `AdminGrantedAccessInformation` + `GRANTED_ACCESS_ENTRY`

- [x] Add `AdminPattern::GRANTED_ACCESS_ENTRY` constant
- [x] Add `src/Analysis/ProjectZomboid/AdminGrantedAccessInformation.php`
- [x] Unit test
- [x] `composer test` green: 175 tests
- [x] Commit: `caed04d Add AdminGrantedAccessInformation insight`

### Task 5 — `AdminChangedOptionInformation` + `CHANGED_OPTION_ENTRY`

- [x] Add `AdminPattern::CHANGED_OPTION_ENTRY` constant
- [x] Add `src/Analysis/ProjectZomboid/AdminChangedOptionInformation.php`
- [x] Unit test
- [x] `composer test` green: 177 tests
- [x] Commit: `b7b89ef Add AdminChangedOptionInformation insight`

### Task 6 — `AdminReloadedOptionsInformation` + `RELOADED_OPTIONS_ENTRY`

- [x] Add `AdminPattern::RELOADED_OPTIONS_ENTRY` constant
- [x] Add `src/Analysis/ProjectZomboid/AdminReloadedOptionsInformation.php`
- [x] Unit test
- [x] `composer test` green: 179 tests
- [x] Commit: `64641fa Add AdminReloadedOptionsInformation insight`

### Task 7 — `AdminTeleportedInformation` + `TELEPORTED_ENTRY`

- [x] Add `AdminPattern::TELEPORTED_ENTRY` constant (handles negative Z for basement coordinates)
- [x] Add `src/Analysis/ProjectZomboid/AdminTeleportedInformation.php`
- [x] Unit test (positive and negative Z cases)
- [x] `composer test` green: 182 tests
- [x] Commit: `d15fc81 Add AdminTeleportedInformation insight`

### Task 8 — Wire `ProjectZomboidPvpLog::getDefaultAnalyser()`

- [x] Replace `return new PatternAnalyser();` with `(new PatternAnalyser())->addPossibleInsightClass(PvpDamageInformation::class)`
- [x] Add `test/tests/Games/ProjectZomboid/Analyser/PvpLogAnalysisTest.php` — asserts three real-PvP insights (Bare Hands, Tire Iron, Hunting Knife) and zero zombie/vehicle insights
- [x] `composer test` green: 184 tests
- [x] Commit: `51eb2de Wire ProjectZomboidPvpLog default analyser`

### Task 9 — Wire `ProjectZomboidAdminLog::getDefaultAnalyser()`

- [x] Register all six `Admin<Verb>Information` classes
- [x] Add `test/tests/Games/ProjectZomboid/Analyser/AdminLogAnalysisTest.php` — asserts the 2+2+2+2+1+2 distribution and confirms the duplicate ShotgunShells row coalesces with `counter == 2`
- [x] `composer test` green: 186 tests
- [x] Commit: `c57d646 Wire ProjectZomboidAdminLog default analyser`

---

## Deviations from the original plan

### The `90c85a0` brace-fix interlude

Task 2's commit (`90c85a0 Add AdminAddedItemInformation insight`) shipped broken. While adding the first `_ENTRY` constant to `AdminPattern.php`, the `Edit` tool's `old_string` was `<TELEPORTED line>\n}` and the `new_string` included a docblock plus the new constant but **dropped the closing brace** of the class body. The commit was made before the test result was inspected, so it landed with a `ParseError: Unclosed '{'` and 9 cascading test errors.

Forward-fix `0d85a05 Fix missing closing brace in AdminPattern` restored the brace as a separate commit (per the `CLAUDE.md` workflow rule: "Always create new commits rather than amending"). The broken intermediate commit remains in history; force-pushing master to clean it would have cost more than the cosmetic gain.

The remaining five admin commits (Tasks 3–7) used a deliberate practice change: every subsequent `Edit` to `AdminPattern.php` included the closing `}` in both `old_string` and `new_string` so it couldn't be dropped again. No further breakage.

### Total commit count

11 commits vs the 10 originally outlined in the spec's planning section. The extra commit is the brace-fix.

### Test-count divergence note (now resolved)

When Phase B.1's plan was written I projected a final count of 158 tests for B.1; the actual landed count was 161 (off by 3 — Task 5's contribution wasn't summed in the plan footer). For B.2 the planned and actual per-step counts match exactly. No projection error this phase.

---

## Done condition (met)

After Task 9, `composer test` reports **186 tests, 387 assertions, all green** under PHPUnit 12.5.6 / PHP 8.5.5 (verified via the `composer:latest` Docker image). All five originally-planned analysers from the Step D Phase B scope (B.1's three plus B.2's two) are now operational on their respective Log subclasses.
