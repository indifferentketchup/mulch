# Junior-Developer Review: PZ Error Pipeline Epic Plan

## Scope

Reviewed artifact: `/home/samkintop/opt/ik-codex/docs/superpowers/plans/2026-06-06-pz-error-pipeline-epic.md` (the epic plan).

Branch: `pz-enrichment-bootstrap`.

Referenced for verification (read-only context, not graded):
- `/home/samkintop/opt/ik-codex/src/Pattern/ProjectZomboid/DebugServerPattern.php`
- `/home/samkintop/opt/ik-codex/src/Log/ProjectZomboid/ProjectZomboidServerLog.php`
- `/home/samkintop/opt/ik-codex/src/Parser/PatternParser.php`
- `/home/samkintop/opt/ik-codex/src/Analyser/{Analyser,AnalyserInterface,PatternAnalyser}.php`
- `/home/samkintop/opt/ik-codex/src/Analysis/{Insight,InsightInterface,Information,Problem,Analysis}.php`
- `/home/samkintop/opt/ik-codex/src/Log/{Log,AnalysableLog,Entry}.php`
- `/home/samkintop/opt/ik-codex/src/Util/ProjectZomboid/ProjectZomboidModAttributor.php`
- `/opt/iblogs/web/frontend/log.php`
- `/opt/iblogs/web/frontend/parts/head.php`
- `/opt/iblogs/web/public/css/iblogs.css`
- `/opt/iblogs/web/public/js/log.js`
- `/opt/iblogs/src/Frontend/Settings/{Setting,Settings}.php`
- `/opt/iblogs/src/Frontend/Cookie/SettingsCookie.php`
- `/opt/iblogs/src/Log.php`
- `/opt/iblogs/composer.json`
- `/home/samkintop/opt/ik-codex/composer.json`
- `/home/samkintop/opt/ik-codex/CLAUDE.md`
- `/home/samkintop/opt/ik-codex/docs/superpowers/research/2026-06-05-pz-analyser-architecture-analysis.md`

## Plain-Language Restatement

Upload a 100k-line PZ debug log; iblogs's existing log page renders every typed error with severity badge, mod tag chip, occurrence counter, and a collapsible stack-trace block. To get there: in codex, teach `DebugServerPattern` a third line shape (B4x), add three small opt-in interfaces (severity, mod attribution, engine noise), write 15 new pattern-driven Insight classes plus one custom cross-entry Analyser that classifies stack traces, add a `CompositeAnalyser` and a `MultiPatternParser`, and broaden the JSON shape with severity/mod/fingerprint fields. In iblogs, extend one Setting enum, rewrite the existing problems-panel `<?php foreach … ?>` block to use the new fields via `instanceof` downcasts, and add ~120 lines of CSS that reuses existing tokens.

(The restatement was straightforward to write because the plan is well-structured. That itself is a positive signal — fewer hidden assumptions than usual.)

## Question Log

### Who and Why
- **Q1 [Answered]:** Who is the primary user of the rendered output? — The iblogs admin viewing a deployed log at `bosslogs.indifferentketchup.com`; the plan's non-goals make this explicit ("upload a 100k-line DebugLog-server.txt; iblogs's existing log page renders…"). CLAUDE.md confirms `bosslogs.indifferentketchup.com` as the deployment target.
- **Q2 [Answered]:** Why now? — The R1/S1 production bug (161 B4x files silently produce 0 errors at the deployed instance) is named the production-blocker by the architectural analysis at `docs/superpowers/research/2026-06-05-pz-analyser-architecture-analysis.md` lines 31-34.
- **Q3 [Answered]:** What does this replace? — Plan section "Architecture (additive, not replacing)" is explicit. The existing 4 Insights and 17 implementations stay byte-compatible (line 22, 151).

### What and Scope
- **Q4 [Answered]:** Two-sentence summary? — Plan gives it implicitly in "Driving goal" and "Why this shape" at the top.
- **Q5 [Answered]:** Acceptance criteria? — Plan has a dedicated section. **But** see JD-006 below for measurability gaps.
- **Q6 [Answered]:** Smallest valuable shippable version? — Plan defers SEC-001 to an independent track, and explicitly says A5 InsightRegistry can wait. Reasonable scoping.

### Assumptions and Evidence
- **Q7 [Open]:** Has anyone actually verified that the existing 17 Insight implementations all extend the base `Insight` class (and so will inherit the new `getFingerprint()` method without modification)? — See JD-005.
- **Q8 [Open]:** What is the dev container performance baseline today? "100k-line parses in <2s" depends on hardware. — See JD-006.

### Prior Art, Specialist Domains, Done and Exit
- **Q9 [Answered]:** Does the plan conflict with CLAUDE.md? — Mostly aligned. Two muddied conventions surfaced: see JD-002 and JD-010.
- **Q10 [Answered]:** Which parts need specialist follow-up? — See "Specialist handoffs" below.

### Verification of Specific Claims
- **Q11 [Answered]:** Plan claims `DebugServerPattern::LINE` exists. — Confirmed at `src/Pattern/ProjectZomboid/DebugServerPattern.php:18`.
- **Q12 [Assumed]:** Plan claims the new B4x regex captures "identical groups (TIME, LEVEL, PREFIX) so the existing parseEntryMatch() flow handles both with no other changes." — Verified: both regexes produce three capture groups in the same positional order, and `PatternParser::parseEntryMatch()` is `protected` (line 164), so `MultiPatternParser` extending `PatternParser` can call it. No issue here.
- **Q13 [Answered]:** Plan references `Insight::jsonSerialize()` returning `message/counter/entry`. — Confirmed at `src/Analysis/Insight.php:73-80`. The plan's new shape is additive plus the entry-null guard.
- **Q14 [Answered]:** Plan claims `log.php` problem-panel block is at lines 97-133. — Confirmed exactly at `/opt/iblogs/web/frontend/log.php:97-133`.
- **Q15 [Answered]:** Plan claims `--font-mono`, `--bg-inset`, `--error-bg` are existing CSS tokens. — All three confirmed in `/opt/iblogs/web/public/css/iblogs.css` lines 61, 66, 71. **But** see JD-007 about `--bg-inset` looking visually identical to `--bg-surface`.
- **Q16 [Answered]:** Plan claims `log.js` settings handler is fully generic. — Confirmed at `/opt/iblogs/web/public/js/log.js:241-253` — `applySetting()` is data-attr-driven, no hardcoded enum cases; only `floatingScrollbar` has a special re-init branch.
- **Q17 [Answered]:** Plan adds `Setting::HIDE_ENGINE_NOISE` with `getDefault() => true`. — Setting at `/opt/iblogs/src/Frontend/Settings/Setting.php` exists, but **the plan does not modify `Settings::get()` to honor `getDefault()`**. See JD-001 — this is a real bug in the plan.
- **Q18 [Answered]:** Does iblogs use any popover/overlay that conflicts with `<details>`? — Yes: `.popover-wrapper`, `popovertarget="..."`, `<div popover>` attribute used at `/opt/iblogs/web/frontend/log.php:150-185`. These are the native HTML popover API, not `<details>`. No conflict.
- **Q19 [Answered]:** Does `updateLineNumber` exist in `log.js`? — Yes, defined at `/opt/iblogs/web/public/js/log.js:9-25`.
- **Q20 [Answered]:** Are `--severity-high: #f97316` raw-hex tokens consistent with iblogs CSS convention? — iblogs `:root` block has `--border: rgba(255,255,255,0.08)` and `--surface: rgba(255,255,255,0.04)` as raw rgba, so raw hex is precedent. Mild concern only: see JD-008.
- **Q21 [Open]:** Is `iterator_to_array($this->log)` safe in `StackTraceClassificationAnalyser`? — `Log` implements `Iterator` (`/home/samkintop/opt/ik-codex/src/Log/Log.php:113-160`); `iterator_to_array()` is correct PHP and the `Log->iterator` field is a private cursor that gets `rewound`. But `addInsight` itself iterates the **Analysis** (`/home/samkintop/opt/ik-codex/src/Analysis/Analysis.php:43-56`) — see JD-009.
- **Q22 [Answered]:** Does `MOD_TOKEN_REGEX = '/Lua\(\(MOD:([^)]+)\)\)\.(\S+)/'` use named groups that conflict with `PatternParser` (Pitfall 1)? — Used **only** in the new `StackTraceClassificationAnalyser` (extends `Analyser` directly, not `PatternAnalyser`/`PatternParser`), so the named-vs-unnamed-group rule does not apply. No conflict.
- **Q23 [Answered]:** Does iblogs already reference any of the proposed capability interfaces in branches? — Grep across `/opt/iblogs/src` returned no matches, so this is greenfield. Safe.
- **Q24 [Answered]:** Does `MultiPatternParser` at `src/Parser/` break Minecraft/Hytale that currently extend `PatternParser`? — Minecraft `VanillaServerLog` and Hytale's two Log subclasses use `makePatternParser()` returning `PatternParser`. They don't inherit from `MultiPatternParser`. As long as `MultiPatternParser extends PatternParser` and is opt-in via the constructor, no impact. The "deferred risk" of cross-game pollution is real but mitigated. See JD-011.

## Assumptions

This review proceeded on these explicit assumptions:

1. The plan is an artifact to be reviewed; no agent has started executing it yet (it ends with "Approve this plan and I'll spawn the Phase 1 paseo agent").
2. The existing 17 Insight implementations referenced in the plan and architecture analysis are the count of pre-existing concrete Insight classes; verification of every one was out of scope.
3. The `composer test` baseline currently passes (the plan implicitly assumes so when it tells the paseo agent to "run `composer test` after each batch").
4. Real Logs3 B4x files referenced in acceptance criteria 2 are in the gitignored `.scratch/pz/Logs3/` directory (confirmed present on disk).

## Open Questions

**OQ1: How will the new `Setting::HIDE_ENGINE_NOISE` default to `true` for users who already have a cookie set?**
- **Why it matters:** `Settings::get()` at `/opt/iblogs/src/Frontend/Settings/Settings.php:29-36` returns `false` for any key missing from the cookie JSON. The plan adds `getDefault(): bool` to the enum but never touches `Settings::get()`. A user with an existing `IBLOGS_SETTINGS` cookie will see the engine-noise rows visible by default, contradicting the plan's stated default.
- **Findings affected:** JD-001.
- **How to resolve:** Plan must specify the change to `Settings::get()` (e.g., `return $this->data[$key->value] ?? $key->getDefault();`) and to `log.js`'s `getCurrentSettings()` which seeds the cookie on first save — also need the `<input … checked>` initial state in `log.php:181` to consult `getDefault()` when the cookie key is absent.

**OQ2: Does running `Analysis::addInsight()` inside `StackTraceClassificationAnalyser::analyse()` interact safely with the shared iterator on `Analysis`?**
- **Why it matters:** `Analysis::addInsight()` (`src/Analysis/Analysis.php:43-56`) uses `foreach ($this as $existingInsight)` to dedupe. `Analysis` is itself an iterator with a `$this->iterator` field. The plan's analyser builds a fresh `Analysis` per call so the very first call is fine, but the plan also says `CompositeAnalyser` "merges their `Analysis` outputs" — merging while iterating is the risky path. The merge implementation is unspecified.
- **Findings affected:** JD-003, JD-009.
- **How to resolve:** Plan must spec the `CompositeAnalyser::analyse()` merge strategy (use `getInsights()` accessor on the second analysis, build new flat array, return single `Analysis`).

**OQ3: How does the bench test reach 161 B4x files that are gitignored?**
- **Why it matters:** Acceptance criteria says "All 161 Logs3 B4x files in `.scratch/pz/Logs3/` produce non-empty Analysis." Those files are not in CI. If this is a local-only check, say so. If it should run in CI, the plan needs a step.
- **Findings affected:** JD-006.
- **How to resolve:** Mark the check as "local verification, not committed" or generate a synthetic equivalent.

**OQ4: Does any of the existing 17 Insight implementations override `jsonSerialize()` without calling `parent::jsonSerialize()`?**
- **Why it matters:** The plan rewrites `Insight::jsonSerialize()` to add `fingerprint`. If subclasses build their own array from scratch, the fingerprint disappears for those types. `Information::jsonSerialize()` (line 83-89) does `array_merge(parent::jsonSerialize(), …)` so it's safe; `Problem::jsonSerialize()` (line 162-167) likewise. The 11 PZ Insight subclasses were not all read.
- **Findings affected:** JD-005.
- **How to resolve:** Grep all 17 implementations for `jsonSerialize` overrides; confirm each calls parent.

## Summary

The plan is well-structured, internally consistent, and most claims about adjacent files check out exactly. Two material gaps: the `Setting::HIDE_ENGINE_NOISE` default-handling chain is incomplete (the plan adds `getDefault()` to the enum but never modifies `Settings::get()` or the cookie/checkbox seeding), and the `CompositeAnalyser` merge contract is hand-waved. A handful of smaller clarifications (bench-test fixture access, fingerprint-vs-subclass `jsonSerialize` interaction, severity-tokens-as-raw-hex precedent) should be addressed before paseo dispatch, but none block the architectural shape.

| Severity          | Count |
|-------------------|-------|
| Blocks decision   | 1     |
| Muddies artifact  | 4     |
| Worth clarifying  | 5     |
| Polish            | 2     |

Open Questions: 4
Specialist handoffs: 3

Full review written to: /home/samkintop/opt/ik-codex/docs/junior-developer-review.md

## Findings

**JD-001: `Setting::HIDE_ENGINE_NOISE` default-true chain is incomplete.**
- **Protocol:** Hidden-Assumption Audit, Standards & Conventions Conflict
- **Location:** Plan section 2.1 (lines 304-339) and 2.4 (lines 635-637); affected production files `/opt/iblogs/src/Frontend/Settings/Settings.php:29-36`, `/opt/iblogs/web/frontend/log.php:181`, `/opt/iblogs/web/public/js/log.js:255-269`.
- **Evidence:** Plan adds `function getDefault(): bool` to the `Setting` enum. The existing `Settings::get()` is `$value = $this->data[$key->value] ?? false; … if (is_bool($value)) { return $value; } return false;`. There is no consultation of `getDefault()` anywhere. The checkbox in `log.php` is `<?= ($settings->get($setting)) ? " checked" : ""; ?>` — for a fresh visitor with no cookie, `get()` returns `false`, the box renders unchecked, and engine noise is **visible** despite the plan's "default true" intent. The plan also claims "no JS edits needed" (section 2.4), but `getCurrentSettings()` and `saveSettings()` in `log.js` write whatever the DOM checkboxes show — so the first cookie write also baselines to `false`.
- **What the artifact assumes / claims / leaves unclear:** That adding `getDefault()` to the enum is sufficient to flip the default. It is not — three call sites need adjustment.
- **Why this matters (in plain terms):** Ship as-written and the headline feature ("engine noise hidden by default") silently doesn't work. Users who never click the Settings dropdown see the unfiltered avalanche the architectural analysis specifically calls out as the destructive case.
- **Related questions:** Q17 (Answered), OQ1.
- **Standard or precedent (if any):** Existing convention in `Setting` enum: `getBodyClass()` returns nullable but every consumer (`Settings::getBodyClasses`, `log.php`, `log.js`) treats it consistently. The plan's `getDefault()` would be the first method on the enum that no consumer reads.
- **Specialist to consult (if any):** N/A — this is a wiring gap a generalist catches at the whiteboard.
- **Severity:** Blocks decision
- **Suggested next step:** Add to the plan an explicit edit list: (a) `Settings::get()` line 31 changes to `?? $key->getDefault()`; (b) `log.php:181` `checked` evaluation must seed from `getDefault()` when the cookie key is absent; (c) `log.js`'s initial-state read needs equivalent treatment, or the first user interaction will overwrite the default to `false`.

**JD-002: `CompositeAnalyser` is introduced as ~30 lines but the merge contract is unspecified.**
- **Protocol:** Hidden-Assumption Audit, Plain-Language Reframing
- **Location:** Plan section 1.4, lines 251 and 240-249.
- **Evidence:** Plan says "New: `src/Analyser/CompositeAnalyser.php` — chains two analysers, merges their `Analysis` outputs. ~30 lines." That's the entire specification. `AnalyserInterface::analyse(): AnalysisInterface` (`src/Analyser/AnalyserInterface.php:28`) says it returns one `Analysis`. `Analysis::addInsight()` walks the existing insights every call (O(n²) for n insights), so a naïve "iterate analysis B, addInsight to analysis A" merge is correct but slow when both produce hundreds of items. The plan's deduplication contract (coalesce vs append) across the boundary is also unstated.
- **What the artifact assumes / claims / leaves unclear:** Whether the composite calls `setLog()` on each child (and what `$log` state is shared), whether it short-circuits on errors from one child, and whether merge uses `addInsight()` (coalesces) or `getInsights()` array-merge (does not).
- **Why this matters (in plain terms):** The whole point of `StackTraceClassificationAnalyser` and `PatternAnalyser` co-existing is that they emit different categories of insights. If the merge silently coalesces a `LuaModRuntimeProblem` from one analyser with a same-class instance from the other, severity/modAttribution fields could be lost.
- **Related questions:** Q21 (Open), OQ2.
- **Standard or precedent (if any):** Existing custom Analysers (`ConnectionFailureAnalyser`, `ItemDuplicationAnalyser`, etc.) each build one `Analysis` and emit through `addInsight()`. No precedent for chaining.
- **Specialist to consult (if any):** `software-architect` if the contract becomes non-trivial. Generalist-level question first: does the merge use coalescing or appending?
- **Severity:** Muddies artifact
- **Suggested next step:** Add a 10-line pseudocode block to section 1.4 showing `CompositeAnalyser::analyse()` body. Specify which `addInsight` path is taken.

**JD-003: Custom Analyser uses `iterator_to_array($this->log)` then re-uses indices for lookback — but `Log`'s iterator cursor is shared with iblogs's `getLinesCount()`.**
- **Protocol:** Hidden-Assumption Audit, Specialist-Domain Boundary
- **Location:** Plan section 1.4 line 200 (`$entries = iterator_to_array($this->log);`); affected `/opt/iblogs/src/Log.php:273-281` (`foreach ($codexLog as $entry)`).
- **Evidence:** `Log` implements `Iterator` with a mutable `protected int $iterator = 0` cursor at `/home/samkintop/opt/ik-codex/src/Log/Log.php:20`. `iterator_to_array()` calls `rewind()` and walks to `valid()` returning false. After that, `$log->iterator` points past the end. iblogs's `getLinesCount()` later calls `foreach ($codexLog as $entry)` which will `rewind()` correctly — so today this is benign. But `Analysis::analyse()` is memoized (line 29-31 of `AnalysableLog.php`), so the test of "did the iterator get consumed" depends on call order between `analyse()` and `getLinesCount()`.
- **What the artifact assumes / claims / leaves unclear:** That `Log` is iterable (true) and that consuming the iterator is safe (true, because PHP `foreach` always calls `rewind` on Iterator implementations). This finding is low-impact in the current architecture but worth a one-line plan note since the cursor is shared state.
- **Why this matters (in plain terms):** Shared mutable state across modules is a future bug magnet. A one-line note "we rely on PHP foreach calling rewind on iblogs's side" or `(new \ArrayIterator(iterator_to_array(...)))` would document the assumption.
- **Related questions:** Q21 (Open).
- **Standard or precedent (if any):** Existing custom Analysers (`ConnectionFailureAnalyser` etc.) iterate via `foreach ($this->log as $entry)` — they don't materialize. Plan introduces the first `iterator_to_array` call site for indexed lookback.
- **Specialist to consult (if any):** N/A.
- **Severity:** Worth clarifying
- **Suggested next step:** One sentence in the plan: "We materialize `$entries` once to support index-based 40-line lookback; cursor consumption is recoverable because every Log consumer calls `foreach` (which rewinds)."

**JD-004: Plan adds `getFingerprint(): string` to the base `Insight` class but the field is initialized to `null` in only one section.**
- **Protocol:** Hidden-Assumption Audit
- **Location:** Plan section 1.5 (lines 254-255) and 1.6 (line 264).
- **Evidence:** Section 1.5 says "Add `getFingerprint(): string` to base `Insight` class." Section 1.6's `jsonSerialize()` calls `$this->getFingerprint()` unconditionally. But the **PatternAnalyser path** (existing 4 + 15 new pattern-only Insights) never calls `setFingerprint()` — only `StackTraceClassificationAnalyser` does (line 228). So `getFingerprint()` either returns `null` (and breaks the JSON return type), an empty string, or throws.
- **What the artifact assumes / claims / leaves unclear:** What the default fingerprint is for Insights whose Analyser doesn't compute one.
- **Why this matters (in plain terms):** The signature `getFingerprint(): string` (not `?string`) means a non-null return is mandatory. The pattern-only Insights have no stack frames to hash. Either the signature is wrong, the JSON serialization needs `if ($fp !== null)`, or every pattern Insight needs a pattern-class-based fallback.
- **Related questions:** —
- **Standard or precedent (if any):** Existing PZ `ServerExceptionProblem::isEqual()` keys on exception type; the equivalent for pattern Insights is the pattern class name itself.
- **Specialist to consult (if any):** N/A.
- **Severity:** Muddies artifact
- **Suggested next step:** Specify the default. Recommendation: `getFingerprint(): string` returns `sha256(static::class)[:16]` by default, overridable per subclass. Or change to `?string` and gate JSON output on null.

**JD-005: Plan rewrites `Insight::jsonSerialize()` without auditing whether existing subclasses override it.**
- **Protocol:** Standards & Conventions Conflict, Plain-Language Reframing
- **Location:** Plan section 1.6 (lines 257-281); affected `src/Analysis/Information.php:83-89`, `src/Analysis/Problem.php:162-167`.
- **Evidence:** `Information::jsonSerialize` and `Problem::jsonSerialize` both call `array_merge(parent::jsonSerialize(), …)` — safe. But the 11 PZ-specific concrete Insight classes under `src/Analysis/ProjectZomboid/` were not audited in the plan. If any rebuilds the array literally (does not call parent), it loses `fingerprint`, `severity`, `mod`, `engine_noise` after the rewrite.
- **What the artifact assumes / claims / leaves unclear:** That every existing Insight subclass either does not override `jsonSerialize` or calls parent.
- **Why this matters (in plain terms):** Adding fields to a base method silently fails for any subclass that doesn't merge with parent — a classic Liskov-substitution friction. The architectural analysis (B5/R2) flagged the `entry: null` defect already exists for the three custom-Analyser Problem types; the plan's fix needs to cover the same surface.
- **Related questions:** OQ4.
- **Standard or precedent (if any):** PHP "always merge with parent" convention in this codebase is observed in both `Information` and `Problem`.
- **Specialist to consult (if any):** `gap-analyzer` for the audit, or just a 5-minute grep before the paseo agent runs.
- **Severity:** Worth clarifying
- **Suggested next step:** Add to the plan: "Phase 1 audit step — grep `src/Analysis/ProjectZomboid/**/*.php` for `jsonSerialize` overrides; any that don't call parent get a one-line fix in the same commit as `Insight::jsonSerialize` changes."

**JD-006: Acceptance criteria contain hardware-dependent numbers and a gitignored-file dependency.**
- **Protocol:** Scope & Definition-of-Done, Evidence-and-Reasoning Check
- **Location:** Plan section "Acceptance criteria" lines 693-704.
- **Evidence:** Two specific claims:
  1. "100k-line synthetic `DebugLog-server.txt` mixing B41/B42/B4x parses + analyses in under 2 seconds on the dev container." — Dev container is FrankenPHP; tests run via Composer Docker image (PHP 8.5 per CLAUDE.md). Those two are not the same baseline. The 2-second number is uncited.
  2. "All 161 Logs3 B4x files in `.scratch/pz/Logs3/` produce non-empty Analysis." — `.scratch/pz/Logs3/` is gitignored (CLAUDE.md "Privacy / fixture rules"). CI cannot reach it; reviewers cannot verify it. The criterion is a manual local check disguised as an assertion.
- **What the artifact assumes / claims / leaves unclear:** Which environment the bench runs in, and whether the 161-file check is committed automation or a one-time local sanity pass.
- **Why this matters (in plain terms):** "Done" needs to be measurable by the next person who picks this up. An acceptance criterion that names a specific number on unspecified hardware against unreachable files is not actionable.
- **Related questions:** Q8 (Open), OQ3.
- **Standard or precedent (if any):** CLAUDE.md is explicit that real logs are out-of-tree; fixtures must be synthetic.
- **Specialist to consult (if any):** N/A.
- **Severity:** Muddies artifact
- **Suggested next step:** Either (a) replace 2-second number with a percentile claim against a generated synthetic fixture committed in `test/`, or (b) explicitly tag the bench as "informational, run locally," and tag the 161-file check the same way.

**JD-007: `--bg-inset` is aliased to `--bg-surface` in the existing CSS — the plan's `pre` background may render visually identical to surrounding chrome.**
- **Protocol:** Plain-Language Reframing
- **Location:** Plan section 2.3 line 604; existing CSS at `/opt/iblogs/web/public/css/iblogs.css:61`.
- **Evidence:** Existing `--bg-inset: var(--bg-surface);` — they are the **same** color. The plan's `.problem-stack pre { background: var(--bg-inset); }` will not visually distinguish the code block from `.problems-panel` chrome (which uses `var(--bg-surface)` elsewhere in the existing CSS).
- **What the artifact assumes / claims / leaves unclear:** That `--bg-inset` is a distinct elevation level. Today it is not.
- **Why this matters (in plain terms):** A flat-looking stack-trace block reduces the "this is code" affordance. Either re-alias `--bg-inset` to something darker (e.g., `color-mix(in srgb, var(--bg) 80%, #000)`) or use a different token.
- **Related questions:** Q15 (Answered).
- **Standard or precedent (if any):** `.collapsed-lines-count` uses `var(--surface)` (rgba 4% white over `--bg`) for similar "inset" feel.
- **Specialist to consult (if any):** `user-experience-designer` if visual hierarchy matters for code-block legibility; generalist call is "use `--surface` which is already darker, or define a real inset token."
- **Severity:** Worth clarifying
- **Suggested next step:** Use `var(--surface)` instead of `var(--bg-inset)`, or redefine `--bg-inset` as part of this plan.

**JD-008: Raw-hex severity tokens added without dark-mode contrast verification claim.**
- **Protocol:** Specialist-Domain Boundary, Evidence-and-Reasoning Check
- **Location:** Plan section 2.3 lines 490-494, and UX validation table line 660.
- **Evidence:** Plan claims "severity orange/yellow chosen for AAA contrast over `--bg` (`#0F172A` baseline)." But `--bg` is config-driven (`/opt/iblogs/web/frontend/parts/head.php:18` reads `ConfigKey::FRONTEND_COLOR_BACKGROUND`) — there is no fixed baseline. Production may render `#f97316` orange on whatever background a future config sets.
- **What the artifact assumes / claims / leaves unclear:** That `--bg` is `#0F172A` at all times. iblogs is themable.
- **Why this matters (in plain terms):** Contrast claims must reference the variable, not a specific value. If a brand color override flips `--bg` light, orange-500 over light is a usability regression.
- **Related questions:** Q20 (Answered).
- **Standard or precedent (if any):** Existing CSS uses `color-mix(in srgb, var(--accent) X%, var(--bg) Y%)` derivations exclusively, never raw hex except for `rgba(0,0,0,…)` shadows and the `border`/`surface` neutral rgba.
- **Specialist to consult (if any):** `user-experience-designer` for the actual contrast verification across config color variants.
- **Severity:** Worth clarifying
- **Suggested next step:** Either constrain to dark-only deployments (state this explicitly), or derive severity tokens from `--bg` + `--text` via `color-mix` so they self-tune.

**JD-009: `StackTraceClassificationAnalyser::analyse()` builds an `Analysis` then `Composite` merges it — same-class coalescing may double-count.**
- **Protocol:** Hidden-Assumption Audit, Behavioral Boundary
- **Location:** Plan section 1.4 lines 196-232 and line 251 (`merges their `Analysis` outputs`).
- **Evidence:** `Analysis::addInsight` coalesces by `get_class($a) === get_class($b) && $a->isEqual($b)`. Plan's `PatternAnalyser` block adds (e.g.) `LuaModRuntimeProblem` instances pattern-matched per entry. Then `StackTraceClassificationAnalyser` also emits `LuaModRuntimeProblem` instances per stack trace (lines 218-225). If both target the same entry, the same problem gets added twice — once via pattern, once via stack — and either the counter inflates or one wins depending on merge order.
- **What the artifact assumes / claims / leaves unclear:** Whether `LuaModRuntimeProblem` is the responsibility of pattern Analyser, stack Analyser, or both.
- **Why this matters (in plain terms):** The architectural analysis (B2 / R6) called out the existing `ServerExceptionProblem` coalescing-too-aggressively problem. The plan introduces a second producer for the same class without a coordination story.
- **Related questions:** Q21 (Open), OQ2.
- **Standard or precedent (if any):** Today, each PatternAnalyser-registered class has exactly one producer.
- **Specialist to consult (if any):** `behavioral-analyst` if the dataflow gets complex.
- **Severity:** Muddies artifact
- **Suggested next step:** Specify the producer table: which of the 15 new classes is emitted by `PatternAnalyser`, which by `StackTraceClassificationAnalyser`, and whether any class crosses the boundary.

**JD-010: Plan's "Single commit each (per CLAUDE.md workflow)" for 15 Insight classes references the wrong rule.**
- **Protocol:** Standards & Conventions Conflict
- **Location:** Plan section 1.3 line 155.
- **Evidence:** CLAUDE.md "Workflow conventions": "One commit per concrete log type when adding game support: pattern class + log subclass + synthetic fixture + test in a single commit." That rule is about **log types**, not Insight classes. The 15 Insights are all the same log type (ServerLog). The rule cited literally says "log type."
- **What the artifact assumes / claims / leaves unclear:** Whether the project actually has a one-commit-per-Insight convention. It does not.
- **Why this matters (in plain terms):** Following the actual rule would suggest grouping the 5 Pattern classes (LuaErrorPattern, LuaModRuntimePattern, EngineExceptionPattern, EngineNoisePattern, ConfigDriftPattern) by Pattern class. The plan's commit cadence is fine, but the citation is wrong.
- **Related questions:** Q9 (Answered).
- **Standard or precedent (if any):** No commit-per-Insight precedent; recent commits (`44b94b2 feat(pz): ProjectZomboidModAttributor…`) are feature-scoped, not class-scoped.
- **Specialist to consult (if any):** N/A.
- **Severity:** Polish
- **Suggested next step:** Rephrase: "Group commits by Pattern class (5 commits + tests), or one commit per Pattern+its-Insights bundle. The CLAUDE.md one-per-log-type rule doesn't directly apply here."

**JD-011: `MultiPatternParser` at framework altitude — risk note is too thin given the deferred items don't have reopen triggers.**
- **Protocol:** YAGNI Evidence Sweep, Standards & Conventions Conflict
- **Location:** Plan section 1.1 line 96 and "What this delivers" line 712.
- **Evidence:** `src/Parser/MultiPatternParser.php` is positioned as framework-level but the consuming code is PZ-only. Plan defers the question of whether Minecraft / Hytale should use it with "Minecraft/Hytale don't need it yet." The yagni-rule says deferred items need a named reopen trigger ("when X happens, revisit"). The plan's deferral is open-ended.
- **What the artifact assumes / claims / leaves unclear:** Under what condition another game would adopt `MultiPatternParser`.
- **Why this matters (in plain terms):** A framework class with one consumer is fine. A framework class with one consumer and no reopen trigger becomes "I forgot why this lived at framework altitude" three months later. The CLAUDE.md Pitfall 7 noted the sentinel-default trap for `MinecraftLog` — adding a second framework-level extension point for parsers risks the same silent inheritance issue.
- **Related questions:** Q24 (Answered).
- **Standard or precedent (if any):** YAGNI rule: deferred items have reopen triggers.
- **Specialist to consult (if any):** `system-architect` if the framework-altitude question turns into a real cross-game contract.
- **Severity:** Worth clarifying
- **Suggested next step:** Either move to `src/Parser/ProjectZomboid/PzMultiPatternParser.php` until another game adopts it, or add a one-liner reopen trigger ("revisit framework promotion when Minecraft or Hytale adds a second LINE shape").

**JD-012: Plan's `<details>` reduced-motion exception is asymmetric.**
- **Protocol:** Plain-Language Reframing
- **Location:** Plan section 2.3 lines 623-630.
- **Evidence:** Reduced-motion block disables `transition` on `.fa-chevron-right` and `.problem-mod-tag`. But `.problem-stack > summary > .fa-chevron-right` has `transition: transform 200ms ease;` and **also** a `transform: rotate(90deg)` on `[open]`. Disabling transition doesn't disable the rotation — that's correct (the icon still rotates instantly, which is what reduced-motion wants). Just worth noting the plan got this right; flagging here for completeness.
- **What the artifact assumes / claims / leaves unclear:** Nothing — this is correct.
- **Why this matters (in plain terms):** Polish only.
- **Related questions:** Q18 (Answered).
- **Severity:** Polish
- **Suggested next step:** None.

> **Protocol 1 — Clarifying-Question Sweep:** All 24 seed questions logged above with tags.

> **Protocol 2 — Hidden-Assumption Audit:** JD-001 (Setting default chain), JD-002 (CompositeAnalyser merge), JD-003 (iterator state), JD-004 (fingerprint default), JD-009 (dual producers) all surface non-stated assumptions.

> **Protocol 3 — Evidence-and-Reasoning Check:** JD-006 (uncited 2-second claim; gitignored-file acceptance), JD-008 (raw-hex tokens without contrast verification claim against config-driven --bg).

> **Protocol 4 — Standards & Conventions Conflict:** JD-010 (mis-cited CLAUDE.md commit rule), JD-001 (Settings convention break), JD-011 (YAGNI/reopen-trigger rule). CLAUDE.md cross-repo sync rule was correctly applied by the plan.

> **Protocol 5 — Specialist-Domain Boundary Check:** Three handoffs.

> **Protocol 6 — Scope & Definition-of-Done Check:** JD-006 (acceptance criteria measurability).

> **Protocol 7 — YAGNI Evidence Sweep:** JD-011 (framework-altitude MultiPatternParser). Otherwise the plan's deferrals are well-justified by the architectural analysis. Phase 7 was not actively violated elsewhere — every new construct (severity enum, capability interfaces, 15 Insights, StackTraceClassificationAnalyser) has named evidence from the v2 scan and a current iblogs rendering need.

> **Protocol 8 — Plain-Language Reframing:** Restatement at top was straightforward to write. JD-007 (visually-identical `--bg-inset` token) surfaced through the restatement. JD-012 noted in passing.

## Junior-Developer Review Summary

### What I Don't Understand Yet

- **OQ1 (verdict-changing):** How does `HIDE_ENGINE_NOISE` actually default to true for fresh visitors with no cookie? The plan's `getDefault()` method is unread by any consumer.
- **OQ2 (verdict-changing):** What does `CompositeAnalyser::analyse()` actually do? "Merge two Analysis outputs" is hand-wavy when both analysers can emit the same Insight class.
- **OQ3:** Is the "161 B4x files" acceptance criterion local-verification-only or CI-automated?
- **OQ4:** Have all 17 existing Insight subclasses been audited for `jsonSerialize` overrides that would silently lose the new `fingerprint`/`severity`/`mod`/`engine_noise` fields?

### What the Artifact Seems to Assume

- That adding a method to the `Setting` enum is sufficient to flip a default (JD-001 — wrong; three call sites must change).
- That the `Insight` base class's new `getFingerprint()` works for pattern-only Insights that have no stack frames (JD-004 — unspecified default).
- That `--bg-inset` provides visual elevation distinct from `--bg-surface` (JD-007 — same value).
- That `--bg` is reliably `#0F172A` for contrast claims (JD-008 — config-driven).
- That the same Insight class won't be emitted by both `PatternAnalyser` and `StackTraceClassificationAnalyser` (JD-009 — unspecified).
- That CLAUDE.md's one-commit-per-log-type rule applies to Insight classes (JD-010 — it doesn't).

### Where the Artifact Conflicts with How We Already Work

- JD-010: The "Single commit each (per CLAUDE.md workflow)" citation in section 1.3 references a rule about log types, not Insight classes.
- JD-001: The plan introduces a default-via-enum-method pattern with no consumer in `Settings::get()`, breaking the actual cookie/checkbox convention.
- JD-011: Framework-altitude class `MultiPatternParser` lacks a YAGNI reopen trigger, contradicting `plugins/han/references/yagni-rule.md`'s convention.

CLAUDE.md and the architectural analysis at `docs/superpowers/research/2026-06-05-pz-analyser-architecture-analysis.md` are otherwise correctly honored — cross-repo sync, version-bump strategy, redactor-pass-order pitfall, B41/B42 coexistence pitfall, named-vs-unnamed regex group pitfall all align.

### Where a Specialist Should Take Over

- **`user-experience-designer`** — JD-007 (visually identical inset token), JD-008 (raw-hex severity tokens against config-driven --bg). Generalist observation: code blocks need elevation differentiation; orange/yellow contrast claim references a value that is not fixed.
- **`software-architect`** — JD-002 (`CompositeAnalyser` merge contract), JD-009 (dual-producer coalescing). Generalist observation: when two analysers can emit the same Insight class, the coalescing rule must be specified.
- **`adversarial-security-analyst`** — Plan defers SEC-001 (Steam ID universe regex) to "an independent track" without scheduling it. Plan section "What this delivers vs. what we deferred" line 717: "Should be hotfixed before this epic ships to production." Generalist observation: "should be hotfixed independently" is not a schedule, and the architectural analysis flagged this as CRITICAL with permanent MongoDB persistence implications. Confirm this hotfix is actually queued.

### What "Done" Looks Like — and What It Doesn't

The acceptance criteria section is comprehensive (12 bullet points). Two concerns:

- JD-006: The "<2s on dev container" and "161 Logs3 B4x files" criteria mix hardware-dependent and out-of-tree-data assertions. Replace with synthetic-fixture percentile assertions or explicitly mark as local verification.
- Rollback plan: not stated. For codex, the plan implies "git revert v0.6.0" but never says it. For iblogs, the composer constraint widen is forward-only — what's the rollback if Phase 3 reveals a JSON contract surprise?

### What the Artifact Includes That Has No Evidence of Being Needed

Most of the additions trace to specific scan evidence (the v2 error scan, architectural analysis A1-A8). The only items that drew YAGNI scrutiny:

- **`MultiPatternParser` at framework altitude** (JD-011): one consumer, no named reopen trigger. Recommendation: move to `src/Parser/ProjectZomboid/` until a second game needs it, or add a reopen trigger.
- **`getFingerprint()` on every Insight** (JD-004): only `StackTraceClassificationAnalyser` computes meaningful fingerprints. The 15 pattern-only Insights would need a fallback. Either the method is `?string` or the fallback hashes `static::class` + Pattern key. Worth deciding before the paseo agent commits.

Everything else (severity enum, capability interfaces, 15 Insights, `StackTraceClassificationAnalyser`, the iblogs template extension, CSS additions) passes the evidence test.

### The Artifact in Plain Terms

Restated at the top. The plan is well-organized and most concrete claims about adjacent files check out exactly when the files are read. The two non-cosmetic muddied points are the `Setting::HIDE_ENGINE_NOISE` default chain (JD-001 — the plan adds a method nothing reads) and the `CompositeAnalyser` merge contract (JD-002 — "merges their Analysis outputs" in 5 words). Address those, sharpen the bench-test criteria (JD-006), confirm the `jsonSerialize` audit (JD-005) and dual-producer story (JD-009), and the plan is ready for paseo dispatch.
