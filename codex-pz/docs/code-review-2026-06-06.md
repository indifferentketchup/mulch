# Code Review — PZ Error Pipeline Epic Plan

**Reviewed:** `docs/superpowers/plans/2026-06-06-pz-error-pipeline-epic.md` on branch `pz-enrichment-bootstrap` against `origin/master`.
**Size:** MEDIUM (one new plan document, but specifies edits to ~25 files across two repos; no actual implementation diff yet).
**Reviewer roster:** `junior-developer`, `adversarial-validator`, `structural-analyst`, `adversarial-security-analyst` (dispatched in parallel) + manual review (Steps 4–6) of the plan against the actual files referenced.
**Branch context:** No PR; plan distills A1–A8 of the prior architectural analysis at `docs/superpowers/research/2026-06-05-pz-analyser-architecture-analysis.md`.
**Focus areas:** "/architectural-analysis make sure everything is good for this upgrade and then /paseo-epic" — i.e., verify the plan is sound before spawning paseo execution.

---

## Verdict

**🛑 Not ready for paseo-epic dispatch.** 4 Critical, 16 Warning, 9 Suggestion findings. Fix the four Critical items first, then proceed.

The plan is well-structured and most claims about adjacent files verify exactly. The four blocking issues:

1. **Setting `HIDE_ENGINE_NOISE` "default true" is dead code** — the plan adds `getDefault()` to the enum but never modifies `Settings::get()` which hardcodes `?? false`. First-time visitors see the engine-noise avalanche the toggle is supposed to hide. (3 reviewers caught this independently.)
2. **B4x exception format is fundamentally different from B41/B42** — the plan's `LINE_B4X` regex fixes header parsing, but B4x stack traces are separate log entries (not tab-indented continuations). `ServerExceptionProblem` / `StackTraceClassificationAnalyser` will silently produce zero results on B4x exception entries. R1/S1 is half-fixed.
3. **CSS `.problem-solutions` class collision** — existing CSS targets it as a `<div>` with background/flex/padding; plan changes element to `<details>` without removing/overriding the existing 6 rules → broken visual output for the most-used Solutions block.
4. **PII leak amplified through new JSON fields without a release gate** — the new `mod`, `causeChain`, `entry` (re-enabled), and `fingerprint` fields surface the existing SEC-001 Steam ID universe leak through additional channels; the "should be hotfixed before deploy" language is aspirational, not encoded in acceptance criteria.

Once these are addressed (plan edits, not code changes — this is still a plan review), the remaining Warnings are mostly clarifications and one design tightening (StackTraceClassificationAnalyser vs ServerExceptionProblem dual-producer story).

---

## Summary

| Category | Count |
|---|---:|
| 🔴 Critical | 4 |
| 🟠 Warning | 16 |
| 🟡 Suggestion | 9 |
| 🟡 YAGNI | 1 |

**Security findings:** SEC-001 (Critical), SEC-002, SEC-003, SEC-004, SEC-005 (Warnings) — surfaced separately, all five carry full evidence in `docs/security-analysis.md`.

| ID | Severity | Category | Title |
|---|---|---|---|
| CRIT-001 | Critical | Wiring gap | `Setting::HIDE_ENGINE_NOISE` default-true chain is incomplete |
| CRIT-002 | Critical | Behavior | B4x exception format gap — fix is half-complete |
| CRIT-003 | Critical | Layout/CSS | `.problem-solutions` CSS rules collide with `<details>` element replacement |
| CRIT-004 | Critical | Security (SEC-001) | New JSON fields amplify pre-existing PII leak without a release gate |
| WARN-001 | Warning | Abstraction | `Insight::getEntry()` non-nullable in interface vs nullable property — null-deref hazard for 15 new Insight classes |
| WARN-002 | Warning | Coupling | `ProjectZomboidLog::makePatternParser()` factory bypassed without alignment |
| WARN-003 | Warning | Abstraction | `CompositeAnalyser` merge contract unspecified |
| WARN-004 | Warning | Coupling | `ServerExceptionProblem` and `LuaModRuntimeProblem` double-count the same entries (most user-visible defect) |
| WARN-005 | Warning | Coupling | `CompositeAnalyser::setLog()` must propagate to children — not surfaced in plan |
| WARN-006 | Warning | Dependency direction | `Insight::jsonSerialize()` capability probes establish a new framework pattern |
| WARN-007 | Warning | Abstraction | `method_exists($problem, 'getCauseChain')` in log.php is untyped probe (the lone exception in a block where everything else uses `instanceof`) |
| WARN-008 | Warning | Abstraction | `getFingerprint(): string` is mandatory on base `Insight` but only StackTraceClassificationAnalyser computes one — no default specified |
| WARN-009 | Warning | Acceptance criteria | Hardware-dependent (`<2s on dev container`) + gitignored-file (`161 Logs3 B4x files`) assertions |
| WARN-010 | Warning | Behavioral hazard | `Insight::jsonSerialize()` `entry` omission is a BREAKING JSON change for API consumers |
| WARN-011 | Warning | Behavioral hazard | `LuaModRuntimeProblem` double-registration (PatternAnalyser + StackTraceClassificationAnalyser) silently discards richer data |
| WARN-012 | Warning | Documentation | iblogs `CLAUDE.md` claims constraint is `^0.4.0` but actual is `^0.5.0` (will become `^0.6.0` after this epic) |
| WARN-013 | Warning | Documentation | codex `CLAUDE.md` Pitfall 6 documents B41/B42 only; B4x addition needs documentation update |
| WARN-014 | Warning | Security (SEC-002) | `causeChain` field passes raw log bytes through to JSON API without ANSI / control-character normalization |
| WARN-015 | Warning | Security (SEC-003) | Phase orchestration creates tag-vs-lock window during cross-repo deploy |
| WARN-016 | Warning | Security (SEC-004) | Acceptance criteria lack "no PII in new fields" regression assertion |
| SUGG-001 | Suggestion | State | `iterator_to_array($this->log)` consumes the iterator cursor — relies on PHP `foreach` rewind |
| SUGG-002 | Suggestion | Audit | `Insight::jsonSerialize()` rewrite needs audit of subclass overrides for `parent::` call |
| SUGG-003 | Suggestion | Visual | `--bg-inset` is aliased to `--bg-surface` — stack-trace `pre` won't visually differentiate |
| SUGG-004 | Suggestion | Visual | Plan claims `--bg = #0F172A` baseline but actual default is `#1a1a1a`; tokens are config-driven |
| SUGG-005 | Suggestion | Documentation | Plan cites CLAUDE.md "one commit per concrete log type" rule for Insight classes — rule is about log types |
| SUGG-006 | Suggestion | Abstraction | `Severity` enum's `Low` collapses engine noise + low-frequency mod warnings — sort weighting inverts intent |
| SUGG-007 | Suggestion | Behavior | `KahluaDumpInformation` membership in both `EngineNoisePattern` (PatternAnalyser) and StackTraceClassificationAnalyser → potential double-count |
| SUGG-008 | Suggestion | Security (SEC-005) | iblogs `composer.lock` pins codex v0.3.0 — two minor versions behind manifest `^0.5.0` (current operational drift) |
| SUGG-009 | Suggestion | Visual | Severity `#f97316` orange achieves WCAG AA (5.47:1 over its own badge bg), not AAA as plan claims |
| YAGNI-001 | YAGNI | Placement | `MultiPatternParser` at `src/Parser/` (framework-level) — one consumer, no named reopen trigger |

---

## 🔴 Critical

### CRIT-001 — `Setting::HIDE_ENGINE_NOISE` default-true chain is incomplete

**Category:** [Wiring gap]
**Location:** Plan §2.1 (Setting enum extension), §2.4 ("zero new JavaScript"); production files `/opt/iblogs/src/Frontend/Settings/Settings.php:31`, `/opt/iblogs/web/frontend/log.php:181`, `/opt/iblogs/web/public/js/log.js:255-269`.
**Confirmed by:** JD-001 (Blocks decision), S-012 (SUGG → escalated), AV-006 (Refuted), and manual review.

**Evidence.** Plan adds a `getDefault(): bool` method to the `Setting` enum and claims `HIDE_ENGINE_NOISE` defaults to `true`. The existing `Settings::get()` hardcodes the fallback:

```php
// /opt/iblogs/src/Frontend/Settings/Settings.php:29-36
public function get(Setting $key): bool
{
    $value = $this->data[$key->value] ?? false;     // ← never consults getDefault()
    if (is_bool($value)) {
        return $value;
    }
    return false;
}
```

The `<input ... checked>` in `log.php:181` evaluates `($settings->get($setting)) ? " checked" : ""` — for a fresh visitor with no cookie, `get()` returns `false`, the checkbox renders unchecked, and `log.js`'s `getCurrentSettings()` (line 255) baselines the first cookie write to `false`. Engine noise is **shown** by default, contradicting the plan's stated intent.

**Fix.** Plan needs three coordinated edits beyond the enum:
1. `Settings::get()` line 31: `?? false` → `?? $key->getDefault()`
2. `log.php:181` checked attribute: when cookie key is absent, seed from `$setting->getDefault()` instead of `$settings->get()`
3. `log.js`'s `getCurrentSettings()`/initial state: align with the PHP-side default

**Why blocking.** The headline UX feature of the entire iblogs side ("hide engine noise by default") silently doesn't work as written. Ship as-is and users see the undifferentiated avalanche the architectural analysis specifically names as the destructive case.

---

### CRIT-002 — B4x exception format gap; plan fixes parsing but leaves exception analysis silent

**Category:** [Behavior]
**Location:** Plan §1.1 (B4x LINE regex), §1.4 (StackTraceClassificationAnalyser), §1.3 (15 Insight classes including stack-frame-dependent ones).
**Confirmed by:** AV-004 (Partially Refuted), AV-009 (deeper).

**Evidence.** The plan's `LINE_B4X` regex correctly matches B4x header lines (verified by AV against real fixture). But sample from `/home/samkintop/opt/ik-codex/.scratch/pz/Logs3/Logs/logs_12-05/12-05-26_13-16-13_DebugLog-server.txt`:

```
[12-05-26 14:06:26.336] ERROR: Multiplayer , 1778594786336> 12,396,937,283> DeadCharacterPacket...> Exception thrown java.nio.BufferUnderflowException at Buffer.nextGetIndex. Message: ...
[12-05-26 14:06:26.336] ERROR: Multiplayer , 1778594786336> 12,396,937,283> DebugLogStream.printException> Stack trace:.
```

In B4x, the exception type is **inline in the header** (`Exception thrown java.nio.BufferUnderflowException ...`), and stack frames appear as **separate log entries** (same or near timestamp, different prefix like `DebugLogStream.printException`). This is fundamentally different from B41/B42 where the type appears on a tab-indented continuation of the same Entry.

`DebugServerPattern::EXCEPTION` requires `Exception thrown\n\t<type>` — the `\n\t` never occurs in B4x. AV confirmed match count = 0 against real B4x exception entries.

`StackTraceClassificationAnalyser::hasExceptionShape()` and Phase 3/5 (mod attribution + cause chain) are designed around the entry body containing the full stack as a multi-line string. For B4x: the exception entry body is one line, the stack is spread across N adjacent entries.

**Impact.** Acceptance criterion "All 161 Logs3 B4x files produce non-empty Analysis" would technically pass (WARN-level Insights like `AnimClipNotFoundProblem` and `MissingIconInformation` fire on warning lines), but the most consequential Insights — `ServerExceptionProblem`, `LuaModRuntimeProblem`, `RecursiveRequireProblem`, `LuaFunctionMissingProblem` — silently produce **zero results on B4x exception entries**. The architectural analysis's R1 ("silent total parse failure") is described as "users see 0 lines | 0 errors" — after this plan ships, users see N lines but still 0 exception errors. R1 is half-fixed; the critical half is missing.

**Fix.** Plan must add either:
- A new `EXCEPTION_B4X` pattern matching the inline `Exception thrown <type>` shape, plus an Insight class for it, OR
- A multi-entry pairing strategy in `StackTraceClassificationAnalyser`: when an entry contains `Exception thrown` inline, walk forward N adjacent entries by timestamp to assemble the stack, then classify.

Either way, this is a substantive plan addition, not a one-line fix.

---

### CRIT-003 — `.problem-solutions` CSS rules collide with `<details>` element replacement

**Category:** [Layout/CSS]
**Location:** Plan §2.2 (changes `<div class="problem-solutions">` to `<details class="problem-solutions">`); existing CSS at `/opt/iblogs/web/public/css/iblogs.css:866-888`.
**Confirmed by:** AV-002.

**Evidence.** Existing CSS rules target `.problem-solutions` as a div:

```css
.problem-solutions {
    display: flex;
    flex-direction: column;
    gap: clamp(...);
    padding: clamp(...);
    background-color: var(--surface);
    border-radius: 5px;
}
.problem-solutions-label { font-size: ...; font-weight: 600; ... }
```

Plan changes the HTML element to `<details class="problem-solutions">` and adds NEW CSS rules (border-top, padding-top, chevron rotation) but **never removes or overrides** the existing `display: flex`, `background-color: var(--surface)`, `padding`, `border-radius` rules. Result: collapsed `<details>` renders with a background tile, double padding, and flex layout that fights the native disclosure behavior.

Separately, the plan removes the `<span class="problem-solutions-label">` element but leaves the `.problem-solutions-label` CSS rule as an orphan (no breakage, but signals incomplete cleanup).

**Fix.** Plan §2.3 (CSS additions) needs an explicit "remove or override" subsection:
- Drop or scope existing `.problem-solutions { display, padding, background, border-radius }` rules.
- Drop orphan `.problem-solutions-label` rules.
- Verify the new rules don't fight a default `<details>` `display: block` behavior.

Also affects `.problem-stack` (a new `<details>` element introduced by the plan) — same scrutiny applies.

---

### CRIT-004 — New JSON fields amplify pre-existing PII leak without a release gate (SEC-001)

**Category:** [Security — A02 Cryptographic Failures / PII exposure]
**Location:** Plan §1.6 (`Insight::jsonSerialize()` extension), §1.4 (`StackTraceClassificationAnalyser` → `setCauseChain`), §2.2 (`<pre><?= htmlspecialchars($stack); ?></pre>`); upstream channel at `src/Analysis/ProjectZomboid/ConnectionFailureProblem.php:53-61`; persistence at `/opt/iblogs/src/Log.php:354`; API at `/opt/iblogs/src/Api/Action/LogInsightsAction.php:18-29`.
**Confirmed by:** SEC-001 (High).

**Evidence + Exploit.**
1. Architectural analysis already documented (`docs/superpowers/research/2026-06-05-pz-analyser-architecture-analysis.md` SEC-001): `ProjectZomboidRedactor::STEAM_ID_REGEX = '/76561198\d{9}/'` misses the 76561197 and 76561199 Steam universes (199 of 433 production IDs measured = ~46%). The player-name regex is anchored on the redacted-placeholder text — when Steam ID isn't redacted, the player name attached to it isn't either.
2. iblogs `Log::save()` runs the Redactor as the persistence boundary, so unredacted IDs/names become permanently stored in MongoDB.
3. The plan's §1.6 `Insight::jsonSerialize()` adds new fields (`mod`, `entry` re-enabled for custom-Analyser Problems, `causeChain`, `fingerprint`). The new `StackTraceClassificationAnalyser::extractCauseChain()` copies `Caused by:` body text verbatim from the entry — same PII-bearing channel.
4. `LogInsightsAction` returns the JSON at `bosslogs.indifferentketchup.com/1/insights/<id>`, surfacing every new field including raw cause chains containing Steam IDs and player names.
5. The `<pre><?= htmlspecialchars($stack); ?></pre>` template additionally renders the leaked content as HTML.

The plan's "What we deferred" text — *"Should be hotfixed before this epic ships to production... because the existing leak is critical"* — is **aspirational prose, not a release gate**. Acceptance criteria (plan lines 691–704) contain zero PII assertions. By the criteria as written, v0.6.0 can be tagged and iblogs deployed with the new surfaces live and the underlying Redactor still leaking.

**Fix.** Plan must add a top-row acceptance criterion:

> v0.6.0 MUST NOT be tagged until `STEAM_ID_REGEX` covers universes 76561197, 76561198, and 76561199, and `PLAYER_AFTER_STEAMID_REGEX` is verified by a fixture in `test/src/Games/ProjectZomboid/fixtures/pii-roundtrip-multi-universe-minimal.txt`. The pipeline `parse() → analyse() → jsonSerialize() → json_encode()` MUST NOT emit any digit sequence matching `/76561197\d{9}|76561198\d{9}|76561199\d{9}/` for that fixture.

Sequence the PR for the Redactor fix as Phase 0, before any of the new Insight / Analyser PRs in Phase 1.

---

## 🟠 Warning

### WARN-001 — `Insight::getEntry()` non-nullable interface vs nullable property
**Category:** [Abstraction] **Files:** `src/Analysis/InsightInterface.php:40`, `src/Analysis/Insight.php:36-39, 76-79`, `/opt/iblogs/web/frontend/log.php:107`.
**Confirmed by:** S-001.

`InsightInterface::getEntry(): EntryInterface` is non-nullable; `Insight::$entry` is `?EntryInterface`; iblogs dereferences `$problem->getEntry()[0]->getNumber()` without null guard. The plan's `jsonSerialize()` null guard sidesteps but doesn't fix the type mismatch. With 15 new Insight classes added in one epic, one missed `setEntry()` becomes a runtime null-deref. **Fix:** Change `getEntry(): ?EntryInterface` to match the property; iblogs template and new plan template both need null-safe access patterns.

### WARN-002 — `makePatternParser()` factory bypassed without alignment
**Category:** [Coupling] **Files:** `src/Log/ProjectZomboid/ProjectZomboidLog.php:23`.
**Confirmed by:** S-002.

Factory is typed `: PatternParser` (concrete). Plan replaces the sole complex call site with direct `new MultiPatternParser()` construction, bypassing the factory. The factory remains correct for 10 simple PZ log subclasses but misrepresents the family. **Fix:** Either widen the factory to optionally produce `MultiPatternParser`, or rename it to clarify it's the "simple parser" path.

### WARN-003 — `CompositeAnalyser` merge contract unspecified
**Category:** [Abstraction] **Files:** `src/Analysis/Analysis.php:43-56`, `AnalysisInterface.php`. **Confirmed by:** JD-002, S-003.

Plan describes `CompositeAnalyser` in 5 words: "merges their Analysis outputs". `Analysis::addInsight()` calls `$insight->setAnalysis($this)` reassigning the back-pointer. The merge implementation needs ~10 lines of pseudocode showing: (a) which child fires first, (b) how the merge calls `addInsight()` vs `getInsights()` array-merge, (c) whether `setLog()` is propagated (see WARN-005). **Fix:** Add 10-line pseudocode block to plan §1.4.

### WARN-004 — `ServerExceptionProblem` + `LuaModRuntimeProblem` will double-count
**Category:** [Coupling] **Files:** `src/Analysis/ProjectZomboid/ServerExceptionProblem.php`, plan §1.3 row 2, §1.4. **Confirmed by:** S-004, JD-009.

**Most user-visible structural defect.** `PatternAnalyser` (with `ServerExceptionProblem` registered) and `StackTraceClassificationAnalyser` (emitting `LuaModRuntimeProblem`) both fire on `Exception thrown` entries, including mod-attributed ones. Different classes → `Analysis::addInsight()` can't coalesce them → every production mod crash produces **two** rows in the problems panel. Plan does not name this seam. **Fix:** Plan must decide: (a) remove `ServerExceptionProblem` from `PatternAnalyser` registration, (b) narrow its pattern to exclude `Lua((MOD:X))` entries, or (c) add post-merge dedup in `CompositeAnalyser`.

### WARN-005 — `CompositeAnalyser::setLog()` must propagate to children
**Category:** [Coupling] **Files:** `src/Analyser/AnalyserInterface.php`, `src/Analyser/Analyser.php`. **Confirmed by:** S-008, AV-005.

`AnalysableLog::analyse()` calls `$analyser->setLog($this)` on the outermost. If `CompositeAnalyser extends Analyser` and inherits the default `setLog()` (stores to `$this->log` only), child analysers have `$this->log === null` and crash at `foreach ($this->log as $entry)`. Framework gives no compile-time signal. **Fix:** Plan §1.4 must show `CompositeAnalyser::setLog()` override forwarding to both children.

### WARN-006 — `Insight::jsonSerialize()` `instanceof` probes are new framework pattern
**Category:** [Dependency direction] **Files:** `src/Analysis/Insight.php`. **Confirmed by:** S-010.

The plan establishes a new pattern: framework base class with capability `instanceof` branches. Acceptable (same namespace) but worth documenting. **Fix:** Add a one-line note to the plan: "We follow the `PatternInsightInterface` capability pattern; future capabilities slot in identically."

### WARN-007 — `method_exists($problem, 'getCauseChain')` is untyped probe
**Category:** [Abstraction] **Files:** `/opt/iblogs/web/frontend/log.php`. **Confirmed by:** S-011.

The lone exception in a template block where all other capability checks use `instanceof`. Untyped duck-typing creates a cross-repo silent-break point. **Fix:** Declare `CauseChainInsightInterface { public function getCauseChain(): ?string; }` and use `instanceof` in the template, identical to the other three capabilities.

### WARN-008 — `getFingerprint(): string` has no default for pattern-only Insights
**Category:** [Abstraction] **Files:** Plan §1.5–1.6. **Confirmed by:** JD-004.

Section 1.5 says "Add `getFingerprint(): string` to base `Insight` class." But only `StackTraceClassificationAnalyser` calls `setFingerprint()`. The 15 pattern-only Insights inherit the method with no value to return — non-null return type means PHP throws or returns `null` (TypeError). **Fix:** Either signature `?string` and gate JSON output, or provide a sensible default like `sha256(static::class . '|' . $patternKey)[:16]`.

### WARN-009 — Acceptance criteria mix hardware-dependent + gitignored-file assertions
**Category:** [Acceptance] **Files:** Plan §"Acceptance criteria" lines 691-704. **Confirmed by:** JD-006.

"<2s on dev container" doesn't say which container baseline. "All 161 Logs3 B4x files" — those files are gitignored per CLAUDE.md "Privacy / fixture rules"; CI can't reach them, reviewers can't verify. **Fix:** Replace with synthetic-fixture percentile assertions (`50th percentile of N runs < 2s on FrankenPHP dev container`), and explicitly tag the 161-file check as "local verification, not CI" or generate synthetic equivalent.

### WARN-010 — `entry` JSON-field omission is a BREAKING wire-format change
**Category:** [Behavior] **Files:** Plan §1.6. **Confirmed by:** AV-001.

Current `Insight::jsonSerialize()` returns `entry => $this->getEntry()` unconditionally (even when null). Plan's `if ($this->entry !== null) { $base['entry'] = ... }` removes the key entirely when entry is null. Any API consumer that checks `data.entry === null` vs `'entry' in data` behaves differently after the change. **Fix:** Either keep `entry` unconditionally (accept null), OR explicitly document this as a wire-format change in CHANGELOG.md and check iblogs frontend JS for assumptions.

### WARN-011 — `LuaModRuntimeProblem` double-registration silently discards richer data
**Category:** [Behavior] **Files:** Plan §1.3 (table row 2), §1.4. **Confirmed by:** AV-005.

Plan registers `LuaModRuntimeProblem` in BOTH `PatternAnalyser` (table row 2) and `StackTraceClassificationAnalyser`. `Analysis::addInsight()` keeps the FIRST matching insight and increments counter. Whichever fires first wins. If `PatternAnalyser` fires first with a simpler version, the `StackTraceClassificationAnalyser`'s richer version (with causeChain, deepestModFrame, fileAndLine) gets discarded into a counter bump. **Fix:** Choose one producer. Recommend: remove `LuaModRuntimeProblem` from `PatternAnalyser` registration (it's a stack-classifier output).

### WARN-012 — iblogs CLAUDE.md stale composer constraint
**Category:** [Docs Update: iblogs CLAUDE.md] **Manual finding.**

iblogs CLAUDE.md says "Current constraint in `composer.json`: `^0.4.0`" — actual constraint is `^0.5.0` (was bumped when v0.5.0 was cut; doc was not updated). Plan needs to bump this to `^0.6.0` AND update the doc text in the same operation. **Fix:** Add to Phase 2 step list: "Update iblogs CLAUDE.md constraint reference."

### WARN-013 — codex CLAUDE.md Pitfall 6 needs update for B4x
**Category:** [Docs Update: codex CLAUDE.md] **Manual finding.**

Pitfall 6 documents B41 + B42 only. After this epic, three formats coexist. **Fix:** Add to Phase 1: update Pitfall 6 in the same commit as the `LINE_B4X` addition, noting the `MultiPatternParser` fallthrough.

### WARN-014 — `causeChain` field passes raw bytes through to JSON API without normalization (SEC-002)
**Category:** [Security] See full evidence at `docs/security-analysis.md` SEC-002. **Fix:** Apply `preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body)` in `StackTraceClassificationAnalyser::extractCauseChain()` to strip ANSI / control bytes; preserves `\x09` tab, `\x0A` LF, `\x0D` CR.

### WARN-015 — Phase orchestration tag-vs-lock window (SEC-003)
**Category:** [Security] See `docs/security-analysis.md` SEC-003. **Fix:** Insert Phase 2.5 "Push v0.6.0 tag to codex remote before Phase 3 begins"; expand Phase 3 to include `composer update indifferentketchup/codex --with-dependencies` + commit the regenerated lock.

### WARN-016 — Acceptance criteria lack PII-regression assertion (SEC-004)
**Category:** [Security] See `docs/security-analysis.md` SEC-004. **Fix:** Add a Phase 1 acceptance test that builds a fixture covering all three Steam universes + named players + a `Lua((MOD:...))` stack trace, runs the full pipeline, and asserts `!preg_match('/76561(?:197|198|199)\d{9}/', json_encode($insight->jsonSerialize()))`.

---

## 🟡 Suggestion

### SUGG-001 — `iterator_to_array($this->log)` consumes the iterator cursor
**Files:** Plan §1.4 line 200. **Confirmed by:** JD-003.

`Log` implements `Iterator` with a shared `$this->iterator` cursor. `iterator_to_array()` walks it. iblogs's later `foreach ($codexLog as $entry)` calls `rewind()` correctly, so this is benign today. **Fix (advisory):** Add one line to plan: "Cursor consumption relies on PHP `foreach` rewind."

### SUGG-002 — `Insight::jsonSerialize()` audit of subclass overrides
**Files:** `src/Analysis/Information.php:83-89`, `src/Analysis/Problem.php:162-167`, 11 PZ Insight subclasses under `src/Analysis/ProjectZomboid/`. **Confirmed by:** JD-005.

`Information` and `Problem` both call `array_merge(parent::jsonSerialize(), ...)`. The 11 PZ-specific Insights weren't audited — if any rebuilds the array literally, it loses the new fields. **Fix:** Add a Phase 1 audit step: grep `src/Analysis/ProjectZomboid/**/*.php` for `jsonSerialize` overrides, verify each calls parent.

### SUGG-003 — `--bg-inset` is aliased to `--bg-surface`
**Files:** Plan §2.3 line 604; iblogs CSS line 61 (`--bg-inset: var(--bg-surface);`). **Confirmed by:** JD-007.

Stack-trace `pre` background won't visually differentiate from surrounding panel. **Fix:** Use `var(--surface)` (which is actually darker), or redefine `--bg-inset` as part of this plan with `color-mix(in srgb, var(--bg) 80%, #000)`.

### SUGG-004 — Plan's `--bg = #0F172A` baseline is incorrect
**Files:** Plan UX validation table; actual default at `/opt/iblogs/src/Config/ConfigKey.php:51` (`#1a1a1a`). **Confirmed by:** JD-008, AV-008, manual.

`--bg`/`--text`/`--accent`/`--error` are injected at runtime from Config (`/opt/iblogs/web/frontend/parts/head.php:17-21`) — they are config-driven per-deploy, not fixed. **Fix:** State explicitly the plan assumes dark-mode default deployment; either constrain to that or derive severity colors from `--bg` + `--text` via `color-mix` so they self-tune.

### SUGG-005 — Plan mis-cites CLAUDE.md "one commit per log type" rule
**Files:** Plan §1.3 line 155. **Confirmed by:** JD-010.

Rule is about log types, not Insight classes. **Fix:** Rephrase: "Group commits by Pattern class (5 commits, each pattern + its Insights + tests + registration)."

### SUGG-006 — `Severity` enum `Low` collapses two different concerns
**Files:** Plan §1.2. **Confirmed by:** S-006.

Engine noise (pure infrastructure noise) and low-frequency mod warnings both land at `Low = 20`. Sort weight `20 × 1523 hits` (noise) outranks `80 × 12 hits` (mod crash). CSS filter mitigates visual; sort/badge logic doesn't. **Fix (advisory):** Add a fifth `Noise = 5` case to separate engine noise from low-frequency mod warnings, OR document that engine-noise items are sorted separately via the body-class filter.

### SUGG-007 — `KahluaDumpInformation` potential double-count
**Files:** Plan §1.3 (EngineNoisePattern table) + §1.4 (StackTraceClassificationAnalyser sketch). **Confirmed by:** S-009.

Plan lists `KahluaDumpInformation` in `EngineNoisePattern` (implying `PatternAnalyser` registration) AND as direct output of `StackTraceClassificationAnalyser` when `$isNoise=true`. Same producer collision as WARN-004. **Fix:** Decide one producer.

### SUGG-008 — iblogs `composer.lock` pins codex v0.3.0 (SEC-005)
**Files:** `/opt/iblogs/composer.lock`. See `docs/security-analysis.md` SEC-005.

Current operational drift — manifest says `^0.5.0`, lock pins v0.3.0. Pre-existing; this epic adds a third version to the picture. **Fix:** Add iblogs CI guard verifying `composer.lock` resolved version ≥ `composer.json` constraint floor for all first-party deps.

### SUGG-009 — Severity orange achieves AA, not AAA (as plan claims)
**Files:** Plan UX validation table. **Confirmed by:** AV-008.

`#f97316` over its own 10% tinted badge bg = 5.47:1 (AA). The yellow `#eab308` does reach AAA (7.57:1). **Fix:** Update plan's "AAA" claim to "AA" for the orange severity tier, or pick a darker orange like `#ea580c` (orange-600).

---

## 🟡 YAGNI

> These findings will not be corrected unless explicitly requested. They are documented so the team can decide consciously whether to keep, simplify, or defer the items.

### YAGNI-001 — `MultiPatternParser` at framework altitude
**Files:** Plan §1.1. **Confirmed by:** JD-011 (tension with S-007 below).
- **(a) Failing evidence type:** Rule of Three. One concrete consumer today (PZ ServerLog).
- **(b) Matched anti-pattern:** Framework abstraction with one consumer + no named reopen trigger.
- **(c) Simpler form considered:** `src/Parser/ProjectZomboid/PzMultiPatternParser.php` until a second game's Log class needs format multiplexing.

**Tension with S-007 (negative-result finding, no severity):** `structural-analyst` argued framework placement is justified because the abstraction is genuinely game-agnostic and Hytale's two log formats are a candidate consumer. The two views: junior-developer applies the YAGNI rule strictly (no second consumer = move out of framework); structural-analyst applies the abstraction-altitude rule (correct altitude is where the abstraction is meaningful, regardless of current consumer count). **Resolution:** if the plan keeps it at framework altitude, add a one-liner reopen trigger: *"Revisit if no second game's Log subclass has registered a second LINE format within 90 days."*

---

## Validation (Step 9.0 self-consistency)

One contradictory-recommendation pair detected: **JD-011 (move MultiPatternParser to PZ namespace) vs S-007 (framework placement is justified)** — both target the same file location decision. Both demoted (JD-011 stays SUGG / now under YAGNI-001; S-007 was already negative-result). Tension annotated on both per Step 9.0.

No other overlapping line-range contradictions detected.

## Structural verification (Step 9.1)

All task IDs sequential within each category; all `file_path:line_number` references point to real files from the Step 1 file list; SEC-001 finding has full `EXPLOIT:` content in `docs/security-analysis.md` and cross-references CRIT-004 here; YAGNI section opens with the required verbatim statement and items don't duplicate under CRIT/WARN/SUGG; agents-not-dispatched (test-engineer, edge-case-explorer, behavioral-analyst, concurrency-analyst, data-engineer, devops-engineer, on-call-engineer) produce no findings, as expected.

---

## Path forward

Three options, in order of recommendation:

1. **Fix the four CRIT items in the plan**, re-run a focused review on those sections only, then proceed to paseo-epic. Estimated plan-edit effort: 30–60 min (mostly CRIT-002 which needs a real design decision on B4x exception handling, and CRIT-004 which needs a Phase 0 Redactor hotfix scheduled). The other two (CRIT-001 Settings chain, CRIT-003 CSS collision) are straightforward edits.

2. **Address CRIT-001, CRIT-003, CRIT-004 in the plan; promote CRIT-002 to a Phase 1.5 spec doc** to be designed-then-implemented as the first paseo agent's job. This unlocks paseo dispatch sooner at the cost of one extra paseo phase.

3. **Defer CRIT-004 entirely** to a parallel SEC-001 hotfix track and accept the PII amplification ships before the underlying redactor fix. **Not recommended** — this is the production-impact item; ships to bosslogs.indifferentketchup.com without the gate.

I recommend option 1. Want me to:
- (a) Apply the four CRIT fixes to the plan in-place and re-run a focused review?
- (b) Lay out a Phase 0 design sketch for the Redactor hotfix (SEC-001) so it can ship first?
- (c) Sketch the B4x multi-entry stack-pairing strategy (CRIT-002) before plan revision?
- (d) Stop here and let you decide?
