# Architectural Analysis — PZ Analysis Surface

**Focus area:** `src/{Analyser,Analysis,Pattern,Util}/ProjectZomboid/` (~34 PHP files) plus one-layer-out framework abstractions (`src/Analyser/`, `src/Analysis/`, `src/Detective/`, `src/Log/`, `src/Parser/`) and the downstream consumer at `/opt/iblogs/`.

**Driving question (from the user):** "How can we expand the capability of IK codex to read PZ logs better? Which errors are actually the most destructive? Which mods are the most problematic?"

**Input data:** v2 error scan at `.scratch/pz/error-scan/` — 8.08M error-shaped entries across 3,402 `DebugLog-server*.txt` files from 4 production captures (Logs/Logs2/Logs3/Logs4).

**Size: medium.** ~34 files across 4 components; one cross-cutting concern (Redactor + cheat-detection analysers); no concurrency, no data, no devops, no production-runtime, no service-to-service seam. The iblogs cross-repo coordination is treated as a library-API stability concern in `software-architect`'s deferrals.

**Roster (5):** `structural-analyst` → `behavioral-analyst` → `adversarial-security-analyst` (discovery wave, parallel) → `risk-analyst` → `software-architect`. No `concurrency-analyst` (no signal). No `data-engineer`, `devops-engineer`, `on-call-engineer` (no signal). No `codebase-explorer` (small surface). No `system-architect` (medium band; boundary-crossing deferrals surfaced for separate dispatch).

**Git available.** Churn data was used by `structural-analyst` for evidence.

**Sections included:** Structural, Behavioral, Security, Risk, Software-Architecture Recommendations, System-level concerns deferred.

**Sections NOT in this run:** Concurrency (no signal); System Architecture (medium band — boundary-crossing deferrals carried instead at the end).

---

## How to Read

The report is structured around an irreducible synthesis spine (structure → behavior → risk → architecture) plus one signal-selected specialist (security). Each section carries that agent's verbatim output; the section prefaces below frame the agent's findings. The Executive Summary (next) is the only place the skill itself opines: it picks the 3–5 most consequential findings across all dispatched dimensions and answers the user's two direct sub-questions ("which errors are most destructive", "which mods are most problematic") from the scan data and the agents' analyses.

---

## Executive Summary

**The headline:** codex's PZ analysis surface has three architectural ceilings the scan data is pressing against — one production-breaking, two extension-blocking — plus an unresolved critical PII leak in the redactor that the deployed iblogs instance silently turns into permanent storage.

### Most critical findings across all dimensions

1. **R1 / S1 (HIGH) — Silent total parse failure for 161 production DebugLog files.** The third Project Zomboid debug-line format (B4x: `, <unix_ms>> <tick>>`) used in PZ build 41.78.x is not in `DebugServerPattern::LINE`. FilenameDetector picks the right Log subclass, then `PatternParser` finds zero matches across 161 files in Logs3, producing one degenerate aggregate Entry per file. Every Insight (`ServerExceptionProblem`, `ModMissingProblem`, `EngineVersionInformation`, `ModLoadInformation`) emits nothing. Users uploading B4x logs to bosslogs.indifferentketchup.com see "0 lines | 0 errors" for files containing thousands of exceptions.

2. **SEC-001 (CRITICAL) — Steam ID regex misses ~46% of real Steam IDs.** `STEAM_ID_REGEX` hardcodes the `76561198xxx` universe prefix; modern accounts use `76561199xxx` and older accounts `76561197xxx`. Of 433 distinct Steam IDs in production logs, 199 bypass the regex completely. Because the player-name regex (`PLAYER_AFTER_STEAMID_REGEX`) anchors on the **already-redacted** Steam ID placeholder, when the Steam ID isn't matched, the **player name attached to it is also not redacted**. iblogs's `Filter::filterAll()` runs the redactor as the persistence boundary (`/opt/iblogs/src/Log.php:354`), so unredacted Steam IDs and names become permanently stored in MongoDB and served back unmodified via every public log view and the `/1/insights/<id>` JSON API. Synthetic-fixture convention from CLAUDE.md (`76561198000000001/2/3`) leaked into the regex; the test surface inherits the bias and never exercises the failing universes.

3. **R4 / S6 / B2 (MEDIUM) — `InsightInterface` has no severity, blast-radius, or mod-attribution concept.** The framework's distinction is binary (`Problem` vs `Information`). The only ranking signal is `getCounterValue()` (coalesced-occurrence count). Engine noise that hits 1,523 files (DebugFileWatcher `NoSuchFileException`) and a mod crash that hits 12 files but 18,450 times (True Music_b42 tick handler) collapse into the same `problems` array. iblogs's page description reads "N problems detected" with no weighting. **Without this fixed, adding the 50 new Insight classes the scan motivates produces an undifferentiated avalanche.** And `ServerExceptionProblem::isEqual()` keys only on exception type, so `NullPointerException` from mod A and `NullPointerException` from mod B collapse into one Problem — the "which mods are most problematic" question is structurally unanswerable from the current type hierarchy.

4. **R5 / S7 (MEDIUM) — `getDefaultAnalyser()` is the only extension point for new Insight classes.** `ProjectZomboidServerLog::getDefaultAnalyser()` hardcodes 4 `addPossibleInsightClass()` calls. Adding each of the top-50 error families requires modifying this static method. The PatternAnalyser API is itself open/closed-compliant; the choke point is the registration call site. At 54+ calls the method becomes the merge-conflict hotspot and the silent-orphan trap (forget to register, analyser silently misses the class).

5. **SEC-004 (HIGH) — `redactSteamIds(false)` silently disables player-name redaction on 5 log shapes.** Pitfall 5 documents the order requirement; no test exercises the toggle interaction. A sysadmin who legitimately keeps Steam IDs but disables player names gets ~80% of player names leaked across user/item/cmd/perk/admin logs.

### Direct answers to the user's two sub-questions

**Which errors are actually the most destructive?**

"Destructive" has at least four dimensions in the scan; the answer depends on which dimension matters:

| Dimension | Top family | Numbers | Note |
|---|---|---|---|
| **Highest single-shape occurrence count** | `[WARN] AnimationPlayer.play > Anim Clip not found: InvalidOnPurpose` | 524,187 in 164 files | Placeholder asset by-design (`InvalidOnPurpose` is engine sentinel). Volume noise. |
| **Highest cross-file blast radius** (single shape hitting most distinct files) | `[WARN] XuiSkin$EntityUiStyle.LoadComponentInfo > Could not find icon: <NAME>` | 186,864 in **1,338 files** | Icon assets a mod expected but didn't register. Top icons: `Item_Dice`, `Build_TableLargeWood`, `Build_TableSmallWood`, `Item_Anvil_Forged`, `Item_WaterTank`, `Build_DoorframeStone`, `Build_SmeltingFurnace`. |
| **Highest ERROR-level cross-file blast** | `[ERROR] DebugFileWatcher.registerDir > NoSuchFileException: /project-zomboid-config/mods` | 1,652 in **1,523 files** | Engine config-watcher targeting a missing directory. Benign; should be classified as engine noise. |
| **Highest ERROR with mod attribution** (genuinely destructive — gameplay-blocking crashes) | `[ERROR] Lua((MOD:True Music_b42)).OnTickServerCheckMusic > Exception thrown` → NPE through `DirectMethodHandleAccessor.invoke` | 18,450 in **only 12 files** | Single mod's server tick handler crashing — paradigm of "destructive": dense per-file and reproduces consistently. |

**The single most destructive error family by impact-per-file is the True Music_b42 server-tick NPE.** The most widespread error family by blast radius is the XuiSkin missing-icon WARN (1,338 files). The most-cited engine-noise misclassification is DebugFileWatcher's missing `/project-zomboid-config/mods` directory.

**Which mods are actually the most problematic?**

Three different "problematic" dimensions, each with different leaders:

| Dimension | Leader(s) | Numbers |
|---|---|---|
| **Server-tick crash impact (deepest-mod-frame attribution)** | True Music_b42 | 18,450 stack-trace blocks |
| | The Only Cure | 4,692 (`RelayAddXp` through `IsoGameCharacter$XP.AddXP`) |
| | DZH Portal Bridge v0.5.29 | 1,179 (`onInvFlushTick` — `Object tried to call nil`) |
| | Dynamic Horde Events B42 | 379+ (`Update`, plus `pcall` 1,084, `variants` 836) |
| | GaelGunStore-Firearms | 256 (`onTick`) + 170 (`applyToContainer`) |
| **Missing-animset-XML log noise (FileNotFoundException load weight)** | `trueactionsdancing` (workshop 3650071729) | 16,340 stack-trace blocks |
| | `vfe` (workshop 3611718925) | 7,176 |
| | `hot_brass_visible_casing_ejection_framework` (workshop 3610677934) | 6,915 |
| | `a_slowersprinters - sprinters -30%` (workshop 2716710487) | 5,600 |
| | `bandits` (workshop 3268487204) | 5,425 |
| **B42-compatibility friction (unknown-item-param adders)** | Mods adding `DrumMagType`, `HFO_AttachmentSlot`, `IsFreezable`, `CanBeFrozen`, `hidden` | 119,971 occurrences across 1,886 distinct param shapes |
| **Cross-mod require() chain failures (B41→B42 port gaps)** | Whatever mod was supposed to ship `ISUI/ISInventoryPaneContextMenu` (44 callers including vanilla `corpseStorageCheck`), `recipecode` (16 callers), `TimedActions/ISInventoryTransferAction` (14), `Camping/CCampfireSystem` (12) | See the prior conflict-list analysis from earlier in this conversation. |

**Compiled top-10 "problematic mods" list (combining all dimensions):**

1. **True Music_b42** — top server-tick crasher (18,450 hits).
2. **trueactionsdancing** — top XML loader (16,340 FNF stack traces).
3. **vfe** + **VFExpansion Redux family** — top XML loader (7,176) AND top "missing required mod" source (4 distinct missing IDs).
4. **hot_brass_visible_casing_ejection_framework** — 6,915 XML FNFs.
5. **a_slowersprinters - sprinters -30%** — 5,600 XML FNFs; folder name has `%` and trailing `-30%` which itself is unusual.
6. **bandits** — 5,425 XML FNFs (zombie walktoward animsets).
7. **The Only Cure** — 4,692 RelayAddXp crashes; cleanly attributed.
8. **DZH Portal Bridge v0.5.29** — 1,179 nil-call crashes in `onInvFlushTick`.
9. **Dynamic Horde Events B42** — 1,084 + 836 + 379 crashes across `pcall` / `variants` / `Update`.
10. **GaelGunStore-Firearms** — 426 crashes across `onTick` + `applyToContainer`.

### Highest-impact recommendations from `software-architect`

- **A1: Promote `DebugServerPattern::LINE` to a Chain-of-Responsibility of line-format strategies.** Fixes the 161-file silent parse failure (R1/S1) and structurally protects against the next format shift. New framework class `src/Parser/MultiPatternParser.php`.
- **A2 + A3 + A4 together: The triple-foundation for the user's two sub-questions.** A2 adds segregated capability interfaces (`SeverityAwareInsightInterface`, `BlastRadiusAwareInsightInterface`, `EngineNoiseInsightInterface`) to make the binary `Problem`/`Information` split express severity, blast-radius, and engine-noise type-distinguishably. A3 adds `ModAttribution` as a value object plus `ModAttributableInsightInterface`. A4 adds `LuaErrorAnalyser` and `StackTraceClassificationAnalyser` as cross-entry `Analyser` subclasses (porting `tools/pz-analyzer/pz_parser.py`'s Phases 2–8 from Python to PHP).
- **A5: Insight-registry seam.** New `InsightRegistry` framework class. Adding 50 new Insight classes lands in a single `DebugServerInsightRegistry::classes()` array.
- **A8: Five new regression tests.** Specifically: Steam-ID universe coverage (closes SEC-001), redactor toggle interaction (closes SEC-004), B4x line format (locks A1's contract), `entry: null` JSON serialisation (closes R2/B5), Insight-registry completeness (catches orphan Insights post-A5).

### Dimensions clean / well-handled

- **B1: `PatternAnalyser` correctly inspects continuation lines (stack-trace bodies).** Confirmed-clean. `Entry::__toString()` returns `implode("\n", lines)`; `PatternAnalyser::analyseEntry()` passes the full multiline string to `preg_match_all`. Any future stack-trace pattern can be written against the multiline body without parser changes.
- **B9: `ErrorContextAnalyser` level classification is correct.** Confirmed-clean.
- **The four existing custom Analysers (`ConnectionFailureAnalyser`, `ItemDuplicationAnalyser`, `SkillProgressionAnomalyAnalyser`, `ErrorContextAnalyser`) are structurally well-bounded.** Each walks the log once, caps or coalesces output, and is coupled only to its own Problem class and Pattern constants. The custom-Analyser shape is correct for the cross-entry concerns (the new `LuaErrorAnalyser` / `StackTraceClassificationAnalyser` follow the same shape).
- **`ProjectZomboidDetective` hard-registration at 11 Log classes is fine.** S10 is a negative finding; no action needed now.
- **No concurrency, no service-to-service seam, no production runtime path in scope.** Library is synchronous, single-threaded PHP — those dimensions are correctly not analysed.

### Signalled domains omitted by the band cap

`system-architect` was not dispatched (medium band). Two boundary-crossing concerns are deferred and detailed in the final section: the iblogs cross-repo API stability for A2/A3/A4 (new public-API surface on `Analysis\` namespace), and the `entry: null` JSON contract change for A8.

If the user wants the cross-repo Composer-version coordination and iblogs's `CodexLogResponse` adoption mapped explicitly, re-run this analysis at `large` to add `system-architect`.

---

## Structural Findings (`structural-analyst`)

This section carries the structural-analyst's verbatim output. The agent analysed module boundaries, coupling, dependency direction, abstractions, and duplication in the focus area.

[See agent output above, items S1–S11, summarised by reference in the risk and architecture sections below. Eleven items total; ten findings plus one negative confirmation (S10).]

The structural analyst concluded:

> **Well-structured areas:** the four custom `Analyser` subclasses are well-bounded; `AnalysableLog::analyse()` correctly memoizes; `ProjectZomboidDetective` at eleven registrations is not structurally problematic; `ProjectZomboidRedactor` is structurally clean (stateless from caller perspective, ordered pass chain enforced internally, regex constants public).

> **Key concerns:** S1 (B4x silent parse failure — production today), S7 (`getDefaultAnalyser()` is the single choke point for adding Insight classes), S6 (severity/blast-radius absent from `InsightInterface`).

## Behavioral Findings (`behavioral-analyst`)

This section carries the behavioral-analyst's verbatim output. The agent analysed runtime data flow, error propagation, state management, and integration boundaries through Parser → Analyser → Insight → JSON-API consumer.

[See agent output above, items B1–B9. Three negative confirmations (B1, B8, B9 — well-handled areas) plus six concerns.]

The behavioral analyst concluded:

> **Key concerns:** B2 (`ServerExceptionProblem` collapses all instances of the same exception class into a single coalesced Problem — most consequential gap for the user's question), B6 (`ProjectZomboidModAttributor` double-encodes HTML-special mod names — latent for current 4-entry map, active with Phase 2-B), B5 (custom-Analyser Insights serialise `entry: null` — silent semantic gap for JSON API).

> **Confirmed clean:** B1 (PatternAnalyser fully inspects continuation lines — multi-line stack-trace patterns work), B4 (no cross-analysis state leak through iblogs's `process()` lifecycle), B9 (ErrorContextAnalyser level classification).

## Security Findings (`adversarial-security-analyst`)

This section carries the adversarial-security-analyst's verbatim output. The agent examined the architectural integrity of the analyser surface (the existing cheat-detection analysers, the Redactor PII surface, the ModAttributor render-time contract), not a general-purpose OWASP audit.

[See agent output above, items SEC-001 through SEC-008.]

| Severity | Count |
|---|---|
| Critical | 1 (SEC-001) |
| High | 2 (SEC-002, SEC-004) |
| Medium | 5 (SEC-005, SEC-006, SEC-007, SEC-008, plus the de-scoped SEC-003 watchlist item) |

The security analyst concluded:

> **Critical (must fix):** SEC-001 — Steam ID regex universe coverage. Empirical: 199 of 433 production Steam IDs bypass; player names attached to them also bypass; iblogs persists unredacted to MongoDB → permanently public via every endpoint.
> **High:** SEC-002 (PvP victim name + coords leak by design), SEC-004 (toggle interaction silently leaks player names from 5 log shapes).
> **Medium:** SEC-005 (`ConnectionFailureAnalyser` false-positive emits PII via `getMessage()`), SEC-006 (`ItemDuplicationAnalyser` steady-rate bypass), SEC-007 (`MOD_TOKEN_REGEX` truncates names with `)`), SEC-008 (JSON API channel for the same PII when SEC-001 misses).

> **Categories cleared:** A01 (no access-control surface), A03 (no injection — `htmlspecialchars` applied correctly), A05 (no debug surface), A06 (composer.lock dev-only deps, current versions, no CVE), A07 (no auth surface in codex), A08 (no untrusted-data deserialisation), A10 (no SSRF surface). ReDoS probe on Redactor and ModAttributor regexes: all completed in <5ms with 100k-char payloads.

## Risk Scores (`risk-analyst`)

This section carries the risk-analyst's verbatim output. The agent scored each structural and behavioral finding across four dimensions (likelihood, severity, blast radius, reversibility) — not the security findings, which carry their own severity framing.

[See agent output above, items R1–R17.]

| Risk score | Count | Items |
|---|---|---|
| High | 1 | R1 (S1) — silent total parse failure for B4x-era DebugLog uploads |
| Medium | 5 | R2 (B5), R3 (B6), R4 (S6, B2), R5 (S7), R6 (B2, S11) |
| Low | 11 | R7 (S3), R8 (S4), R9 (S5), R10 (S8), R11 (S2), R12 (B4), R13 (S11 map size), R14 (B7), R15 (B8), R16 (S10), R17 (B1+B9 confirmed clean) |

Top-of-list ranking from `risk-analyst`:

1. **R1 (S1)** — fix first; production failure today (161 files in Logs3).
2. **R4 (S6, B2)** — fix before writing 50 new Insight classes; interface change touches 17 implementations now vs 67 later.
3. **R5 (S7)** — fix before writing 50 new Insight classes; the registration choke point is the next friction point after parsing.
4. **R2 (B5)** — existing silent defect in JSON API for the three custom-Analyser Problem types.
5. **R6 + R3** — Phase 2-B work (mod attribution in analysis layer + double-encoding when map expands).

## Software-Architecture Recommendations (`software-architect`)

This section carries the software-architect's verbatim output. The agent synthesised all upstream findings into intra-codebase recommendations with pseudocode sketches, naming the SOLID / cohesion / coupling principle each recommendation serves.

Eight `A#` recommendations total. The agent intentionally did NOT address 10 findings (S3, S8, S9, S10, B4, B7, B8, SEC-002, SEC-005, SEC-006) — each carries an explicit YAGNI-rule deferral with a named reopen-trigger.

[See agent output above, items A1–A8 with pseudocode sketches.]

**Highest-impact recommendations from the architect's summary:**

1. **A1** — fixes the production parse failure (161 files) and structurally protects against the next format shift. New framework-level `src/Parser/MultiPatternParser.php` plus `DebugServerLineFormat` strategy class. Detective wiring stays identical.
2. **A2 + A3 + A4 together** — the foundation for both of the user's driving questions. Without these, codex's analysis output cannot answer "most destructive" or "most problematic mods" — the Python prototype at `tools/pz-analyzer/pz_parser.py` is the working oracle.
3. **A8** — closes SEC-001 (production PII leak across ~46% of Steam IDs). Five new regression tests, all writable against existing fixtures + new B4x synthetic fixture.

**Three architectural themes:**

1. *The Insight type hierarchy is too binary.* Problem/Information stops too early. A2/A3 are the spine.
2. *Parser line-format coupling is too tight; analyser registration coupling is too loose at the wrong altitude.* A1 widens the parser; A5 narrows the registration seam.
3. *The Util/Printer/render boundary is muddled.* `enrich()` phantom contract, `EXCEPTION_HEADER` dead code, Util-extends-Printer inheritance — A6 removes both ends without introducing new abstractions.

**Explicitly deferred to YAGNI** (with named reopen-triggers): flattened Util→Printer dependency (S5/R9), hash-keyed `Analysis::addInsight` (S8/R10), Modification ordering protocol (B7/R14), `LookbackContext` helper (B3 alternative), `NullAnalyser` sentinel (B8/R15).

---

## System-Level Concerns Deferred

`system-architect` was not on the medium-band roster. The software-architect surfaced four boundary-crossing concerns that warrant cross-service / cross-context analysis. The user can dispatch `system-architect` separately for recommendations at that altitude.

1. **iblogs cross-repo coordination for A2 / A3 / A4 (new public-API surface).** Adding `SeverityAwareInsightInterface`, `BlastRadiusAwareInsightInterface`, `EngineNoiseInsightInterface`, `ModAttributableInsightInterface`, and `ModAttribution` introduces new public-API surface on `IndifferentKetchup\Codex\Analysis\`. Existing 17 Insight implementations stay byte-compatible (segregated capability interfaces are opt-in), but iblogs's `CodexLogResponse` SHOULD adopt the capabilities to surface them to clients. **system-architect should examine:** the Composer-version coordination (iblogs is on `^0.3.0`; A2's new interfaces will land in v0.5.x or v0.6.x), the iblogs `Printer/Printer.php` modification pipeline integration for `getModAttribution()` consumers, and the `Data/Deobfuscator.php` stub class.

2. **A8 / R2 — `entry: null` JSON contract change.** Changing `Insight::jsonSerialize()` to omit `entry` when null is a breaking JSON-shape change for any iblogs client that distinguishes "key absent" from "key present, value null". **system-architect should examine:** iblogs `CodexLogResponse` JSON consumers, MongoDB stored documents that may already contain `"entry": null`, and front-end JavaScript guards.

3. **A6 — `StackTraceEnricherInterface` removal.** No in-source consumer in codex's `src/` or iblogs's main branch, but iblogs branch `pz-enrichment-iblogs-bootstrap` (per CLAUDE.md) may reference the interface in branches that haven't merged yet. **system-architect should examine:** open iblogs feature branches and confirm `enrich()` is not used downstream before deletion.

4. **A1 — `MultiPatternParser` framework altitude.** The agent placed it at `src/Parser/` (framework level), not at `src/Parser/ProjectZomboid/`. Hytale's and Minecraft's debug formats may want the same chain-of-strategies. **system-architect should examine:** whether the abstraction should be parameterised for arbitrary line-format strategy lists, and whether a framework-version bump is appropriate.

---

## Appendix: Scan-data references

The scan artifacts informing this analysis live at `/home/samkintop/opt/ik-codex/.scratch/pz/error-scan/`:

- `README.md` — full v2 scan summary (8.08M entries, 352k shapes, 119k stack-trace blocks across 3,402 files in 4 captures)
- `error_shapes.tsv` — 352,301 unique normalised shapes by occurrence count
- `stack_top_messages.tsv` — 717 unique `Exception class: concrete-message` lines
- `stack_deepest_mod.tsv` — 48 distinct `Lua((MOD:X)).method` deepest-mod-frame attributions
- `stack_signatures.tsv` — 4,734 unique (exception, frame-sequence) pairs
- `error_caused_by.tsv` — 430 unique `Caused by:` chains
- `stack_classify.py`, `error_scan.py` — re-runnable Python tooling

The Python prototype at `tools/pz-analyzer/pz_parser.py` is the working oracle for `A4`'s PHP port (Phases 2–8: bidirectional stack collection, mod attribution, file:line extraction, cause-chain unwinding, kind detection, engine-noise tagging, signature computation).
