# Structural Analysis — PZ Error Pipeline Epic

**Plan analyzed:** `docs/superpowers/plans/2026-06-06-pz-error-pipeline-epic.md`
**Branch:** `pz-enrichment-bootstrap`
**Analyst scope:** proposed additions + one layer outward in each direction

---

**S-001: `Insight::getEntry()` is non-nullable in the interface but the plan adds a null guard only in `jsonSerialize()`**

- **Severity:** WARN
- **Dimension:** Abstraction / Coupling
- **File(s):**
  - `/home/samkintop/opt/ik-codex/src/Analysis/InsightInterface.php` (line 40)
  - `/home/samkintop/opt/ik-codex/src/Analysis/Insight.php` (lines 36–39, 76–79)
  - `/opt/iblogs/web/frontend/log.php` (line 107)
  - Plan Phase 1.6
- **Finding:** `InsightInterface::getEntry(): EntryInterface` is typed non-nullable. `Insight::$entry` is `protected ?EntryInterface $entry = null` — the implementation is already nullable but the interface contract is not. The plan adds a null guard in `jsonSerialize()`:

  ```php
  if ($this->entry !== null) {
      $base['entry'] = $this->entry;
  }
  ```

  This fix is local to `jsonSerialize()`. The existing iblogs call site at `/opt/iblogs/web/frontend/log.php` line 107 dereferences the return value without a null check:

  ```php
  <?php $number = $problem->getEntry()[0]->getNumber(); ?>
  ```

  The plan's new `StackTraceClassificationAnalyser` always calls `$insight->setEntry($entry)` before emitting, so this is not a crash vector for new Insights. But the underlying mismatch between the interface return type and the property declaration remains. Any future Insight that forgets to call `setEntry()` before being handed to iblogs will throw a null-dereference at `[0]->getNumber()` — a runtime failure with no compile-time signal. The plan's null guard in `jsonSerialize()` creates an inconsistency: the JSON path defends against null, but the template path does not.

  In the new template block (plan Phase 2.2) the plan itself uses:
  ```php
  $entry = $problem->getEntry();
  $lineNumber = $entry[0]?->getNumber();
  ```
  That nullsafe call only protects the array element dereference, not the `getEntry()` return being null in the first place.

- **Impact:** Structural contract mismatch propagates. The interface promises a non-null return; the base class stores null; no implementation guard forces a pre-flight. Adding new Insight classes under time pressure (15 classes in one epic) means one missed `setEntry()` call will reach the template and crash.

---

**S-002: `ProjectZomboidLog::makePatternParser()` return type is `PatternParser`, but the plan replaces the call site with `new MultiPatternParser()`**

- **Severity:** WARN
- **Dimension:** Dependency Direction / Coupling
- **File(s):**
  - `/home/samkintop/opt/ik-codex/src/Log/ProjectZomboid/ProjectZomboidLog.php` (line 23)
  - Plan Phase 1.1 (`ProjectZomboidServerLog::getDefaultParser()`)
- **Finding:** The `makePatternParser()` factory is typed to return `PatternParser` (the concrete class), not `ParserInterface`:

  ```php
  protected static function makePatternParser(string $pattern, array $matches): PatternParser
  ```

  The plan bypasses `makePatternParser()` entirely for `ProjectZomboidServerLog` and constructs `MultiPatternParser` directly. That's structurally fine because `getDefaultParser()` returns `ParserInterface`. However, the 10 other PZ Log subclasses that call `makePatternParser()` still receive a concrete `PatternParser`. This means:

  1. The factory's concrete return type is now misaligned with its sole variant: there are two ParserInterface implementations but the factory only knows one.
  2. If any future PZ log subclass needs B4x support, the developer will have two patterns to follow (factory vs direct construction). The factory is not updated in the plan's scope.

  There is no typed caller outside `src/Log/` that depends on the `PatternParser` return (all call sites assign to `ParserInterface` via `getDefaultParser()`), so this is not a breakage. But the `PatternParser` concrete return type on the factory becomes misleading — `makePatternParser` is named and typed as if it produces all PZ parsers, but after this epic it misses the one that matters most.

- **Impact:** Future developer adding B4x support to a second PZ log type will reach for `makePatternParser()`, get a wrong prototype, and either write a fork or change the factory under pressure.

---

**S-003: `CompositeAnalyser` has no merge contract in `AnalysisInterface` — the merge behavior is implicit**

- **Severity:** WARN
- **Dimension:** Abstraction / Coupling
- **File(s):**
  - `/home/samkintop/opt/ik-codex/src/Analysis/Analysis.php` (lines 43–56)
  - `/home/samkintop/opt/ik-codex/src/Analysis/AnalysisInterface.php`
  - Plan Phase 1.4 (`CompositeAnalyser`, ~30 lines)
- **Finding:** `Analysis::addInsight()` already de-duplicates by `isEqual()`:

  ```php
  public function addInsight(InsightInterface $insight): static
  {
      $insight->setAnalysis($this);
      foreach ($this as $existingInsight) {
          if (get_class($insight) === get_class($existingInsight) && $existingInsight->isEqual($insight)) {
              $existingInsight->increaseCounter();
              return $this;
          }
      }
      $this->insights[] = $insight;
      return $this;
  }
  ```

  A naive `CompositeAnalyser::analyse()` implementation that calls each child analyser and iterates their results into a merged `Analysis` will work correctly for distinct Insight classes. The structural risk is at the seam between `ServerExceptionProblem` (emitted by `PatternAnalyser`) and `LuaModRuntimeProblem` (emitted by `StackTraceClassificationAnalyser`): they are different classes, so `get_class()` never matches, and both will appear in the merged output for the same "Exception thrown" entry. The plan does not name this as a deferrral or an explicit coexistence decision.

  There is also a subtler structural issue: `Analysis::setLog()` must be called once on the merged `Analysis`. If `CompositeAnalyser` creates a fresh `Analysis` and populates it by calling `addInsight()` on results from two child analysers, the child `Analysis` objects each have `$analysis` back-pointers on their Insights pointing to the wrong object. The plan's sketch:

  ```php
  $insight->setEntry($entry);
  $insight->setFingerprint($this->fingerprint($insight, $body));
  $analysis->addInsight($insight);
  ```

  does not show the Insight being pulled from one `Analysis` and added to another, but that is the merge operation `CompositeAnalyser` must perform.

- **Impact:** No mechanism in `AnalysisInterface` expresses "merge from another Analysis." The implementation of ~30-line `CompositeAnalyser` must either iterate the child's `getInsights()` array directly, or call `addInsight()` with each child Insight. Both work with the current `Analysis` code, but the back-pointer reassignment (`$insight->setAnalysis($this)` inside `addInsight()`) means each Insight can only belong to one `Analysis`. This is not a contract violation but will cause silent surprises if any Insight calls `$this->getLog()` during merge — it will see the merged Analysis's log during addInsight but the child Analysis's log during any prior reference. This is a latent hazard with low immediate probability but no architectural mitigation.

---

**S-004: `ServerExceptionProblem` and `LuaModRuntimeProblem` will double-count the same entries**

- **Severity:** WARN
- **Dimension:** Coupling / Module Boundaries
- **File(s):**
  - `/home/samkintop/opt/ik-codex/src/Analysis/ProjectZomboid/ServerExceptionProblem.php`
  - `/home/samkintop/opt/ik-codex/src/Pattern/ProjectZomboid/DebugServerPattern.php` (EXCEPTION constant)
  - Plan Phase 1.3 (LuaModRuntimeProblem) and Phase 1.4 (StackTraceClassificationAnalyser)
- **Finding:** `ServerExceptionProblem` matches via `DebugServerPattern::EXCEPTION`:

  ```php
  public const string EXCEPTION = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\][^\n]+Exception thrown\n\t(?<type>[A-Za-z0-9_.$]+(?:Exception|Error))[^\n]*(?<body>(?:\n\t.+)*)/';
  ```

  `LuaModRuntimeProblem` is described as matching entries where `hasExceptionShape()` fires on an entry containing `Exception thrown`. Both the `PatternAnalyser` (with `ServerExceptionProblem`) and `StackTraceClassificationAnalyser` (producing `LuaModRuntimeProblem`) will be composed via `CompositeAnalyser` and fed the same log.

  For any entry where both patterns match — specifically a mod-attributed exception (the common case: `Lua((MOD:X)) > ... Exception thrown`) — the merged `Analysis` will contain both a `ServerExceptionProblem` and a `LuaModRuntimeProblem` for the same entry. They are different classes, so `Analysis::addInsight()` cannot coalesce them. The plan says in the "What we deferred" section only: "A2 capability interfaces are additive; existing 17 Insights are not retrofitted" — but does not address the coexistence of `ServerExceptionProblem` and `LuaModRuntimeProblem` as a double-counting risk.

  The plan's Phase 1.4 sketch shows `StackTraceClassificationAnalyser` branching on `$isNoise` to emit either `KahluaDumpInformation` or `LuaModRuntimeProblem`. For a `Lua((MOD:X)) Exception thrown` entry, `isNoise` is false → `LuaModRuntimeProblem` is emitted. The `PatternAnalyser` independently matches the same entry via `EXCEPTION` → `ServerExceptionProblem` is emitted. Both reach the merged `Analysis`.

- **Impact:** Every mod-attributed exception produces two problems in `getProblems()`. iblogs's problems panel will show duplicate rows. The severity sort `severity × counter` will list the richer `LuaModRuntimeProblem` first (High × N) and `ServerExceptionProblem` second (default Medium × N), but both appear. This is the most directly user-visible structural defect introduced by the plan.

---

**S-005: Capability interfaces (`SeverityAwareInsightInterface`, `ModAttributedInsightInterface`, `EngineNoiseInsightInterface`) placed in `src/Analysis/` are correctly game-agnostic**

- **Severity:** (negative result — sound)
- **Dimension:** Module Boundaries
- **File(s):**
  - `/home/samkintop/opt/ik-codex/src/Analysis/InsightInterface.php`
  - `/home/samkintop/opt/ik-codex/src/Analysis/PatternInsightInterface.php`
  - Plan Phase 1.2
- **Finding:** The existing capability interface precedent — `PatternInsightInterface` in `src/Analysis/` — is game-agnostic: it adds `getPatterns()` and `setMatches()` without referencing any game domain. The three new capability interfaces follow the same pattern. `SeverityAwareInsightInterface` returns a `Severity` enum with four cases (`Low`, `Medium`, `High`, `Critical`) that express a generic concern dimension applicable to any game. `ModAttributedInsightInterface` returns a `ModAttribution` value object — the `deepestModFrame` field stores a raw string from the log line; the interface itself does not embed PZ-specific parsing. `EngineNoiseInsightInterface` is a pure marker interface.

  `ModAttribution` (plan places at `src/Analysis/ModAttribution.php`) contains a `deepestModFrame` field described as holding `Lua((MOD:X))` stack frame text. This field is PZ-specific in practice. However, the class itself is a `final readonly` value object with no parsing logic — it is a data carrier. The parsing that produces the `deepestModFrame` string value happens in `StackTraceClassificationAnalyser` (PZ-namespaced). The abstraction boundary is correctly placed: the value object is generic; the population logic is game-specific.

  The altitude of all three interfaces matches the altitude of `PatternInsightInterface`. Placement in `src/Analysis/` is correct.

- **Impact:** No structural risk here. This is a well-drawn boundary.

---

**S-006: `Severity` enum's four cases underfit the scan distribution at the low end**

- **Severity:** SUGG
- **Dimension:** Abstraction
- **File(s):**
  - Plan Phase 1.2 (`Severity` enum)
  - Research doc: `docs/superpowers/research/2026-06-05-pz-analyser-architecture-analysis.md`
- **Finding:** The plan defines four severity cases with integer weights:

  ```php
  case Low = 20;       // engine noise, recoverable
  case Medium = 50;    // mod warnings, cross-mod conflicts
  case High = 80;      // mod crashes, server-tick exceptions
  case Critical = 100; // parse failures, fatal exceptions
  ```

  The scan distribution in the research doc is:
  - 33 shapes ≥10k entries (high)
  - 597 shapes 1k–9k (medium-high)
  - 8,373 shapes 100–999 (medium)
  - 49,257 shapes 10–99 (low)
  - 270k+ shapes 1–9 (very low)

  Six of the 15 new Insight classes are assigned `Low`. Engine noise and low-frequency warnings both map to `Low = 20`. The numeric weights 20/50/80/100 are used directly in the severity sort `severity × counter`. An engine-noise item that fires 1,523 times has a sort weight of 20 × 1523 = 30,460. A `High` mod crash that fires 12 times has a sort weight of 80 × 12 = 960. The engine-noise item ranks higher than the mod crash despite being tagged as engine noise — the CSS body-class filter hides it, but the badge count logic in Phase 2.2 already accounts for this. The four-case enum is structurally sufficient for the use cases the plan describes. The concern here is narrower: the `Low` case collapses genuinely different error families (pure engine noise vs. low-frequency mod warnings) into the same bucket, which reduces sort expressivity.

  This is only a suggestion because it directly follows from the new enum definition and the scan data above — but the plan explicitly defers `InsightRegistry` (A5) and granular classification to later epics, so a five-case enum now would be premature.

- **Impact:** Minor sort-quality degradation. Low-frequency mod warnings will be interleaved with high-frequency engine noise in the sorted panel when neither the CSS filter nor the counter separates them.

---

**S-007: `MultiPatternParser` at `src/Parser/` is justifiably framework-level**

- **Severity:** (negative result — sound)
- **Dimension:** Module Boundaries / Dependency Direction
- **File(s):**
  - `/home/samkintop/opt/ik-codex/src/Parser/PatternParser.php`
  - Plan Phase 1.1
- **Finding:** The plan places `MultiPatternParser` at `src/Parser/MultiPatternParser.php` (framework-level). It extends `PatternParser` and adds a list of `(regex, matchTypes)` pairs; `parse()` tries each regex per line, first-match wins, continuation behavior unchanged. This is a generic capability: any log format with format version variance would benefit from it. The existing `PatternParser` hierarchy (`Parser` → `PatternParser`) is in `src/Parser/`; extending it there is structurally consistent.

  The concern about PZ-exclusivity: `MultiPatternParser` depends only on `PatternParser::TIME`, `PatternParser::LEVEL`, `PatternParser::PREFIX` constants and the inherited `parseEntryMatch()` override hook — nothing PZ-specific enters. VanillaServerLog, HytaleServerLog, and future game Log classes could use it for their own multi-format scenarios. Framework placement is warranted.

  The only callers of `PatternParser` by concrete type (not `ParserInterface`) are in `ProjectZomboidLog::makePatternParser()` return type and the `new PatternParser()` construction sites. `MultiPatternParser extends PatternParser` satisfies Liskov substitution; the return type of `getDefaultParser()` across all Log subclasses is `ParserInterface`, so framework code typed against the interface is unaffected.

- **Impact:** No structural risk.

---

**S-008: `CompositeAnalyser` at `src/Analyser/` is justifiably framework-level, but `AnalyserInterface` requires `setLog()` to propagate to children**

- **Severity:** WARN
- **Dimension:** Abstraction / Coupling
- **File(s):**
  - `/home/samkintop/opt/ik-codex/src/Analyser/AnalyserInterface.php`
  - `/home/samkintop/opt/ik-codex/src/Analyser/Analyser.php`
  - Plan Phase 1.4
- **Finding:** `AnalyserInterface` declares `setLog(AnalysableLogInterface $log): static`. `Analyser` (abstract base) stores `$this->log`. `CompositeAnalyser` wraps two `AnalyserInterface` children. When `AnalysableLog::analyse()` calls:

  ```php
  $analyser->setLog($this);
  return $this->analysis = $analyser->analyse();
  ```

  ...it calls `setLog()` on the `CompositeAnalyser`. `CompositeAnalyser::setLog()` must forward the log to each child analyser before calling their `analyse()`. This is not an optional behavior — `PatternAnalyser::analyse()` and `StackTraceClassificationAnalyser::analyse()` both iterate `$this->log` directly. If `CompositeAnalyser::setLog()` only stores `$this->log` in the base `Analyser` field and does not propagate, both children's `$this->log` will be null and their `analyse()` calls will fail.

  The plan does not show the `setLog()` implementation of `CompositeAnalyser`. The ~30-line sketch shows only the constructor and `analyse()` flow. `Analyser::setLog()` is not `final`, so `CompositeAnalyser` can override it — but the requirement to propagate is not surfaced by any interface or abstract method. This is an implicit coupling that the plan's implementation sketch omits.

- **Impact:** If `CompositeAnalyser` extends `Analyser` (which provides `setLog()` storing to `$this->log`) and does not override `setLog()` to also forward to children, both child analysers will iterate a null log and throw. This will surface immediately in tests, but the structural concern is that the framework gives no signal that a composing analyser must propagate `setLog()`.

---

**S-009: The 15 new Insight classes' Pattern grouping is coherent, with one boundary worth flagging**

- **Severity:** SUGG
- **Dimension:** Module Boundaries / Cohesion
- **File(s):**
  - Plan Phase 1.3 (pattern grouping table)
- **Finding:** The five pattern class groupings are:

  - `LuaErrorPattern` — 3 Insight classes: `LuaRequireFailedProblem`, `LuaFunctionMissingProblem`, `RecursiveRequireProblem`. These are all Lua require/function errors at the script level. Cohesion is high.
  - `LuaModRuntimePattern` — 1 Insight class: `LuaModRuntimeProblem`. A one-insight pattern class. The plan justifies this as a `StackTraceClassificationAnalyser` target, not a `PatternAnalyser` target. This is a deliberate separation of runtime exception attribution from simpler Lua errors.
  - `EngineExceptionPattern` — 3 Insight classes: `AnimsetXmlMissingProblem`, `IsoPropertyTypeNotFoundProblem`, `BoneIndexNotFoundProblem`. These are engine-layer FileNotFoundException / type-resolution failures tied to animation and property loading. Cohesion is reasonable, though `AnimsetXmlMissingProblem` is also `ModAttributed` while the others are not — the pattern class groups them by engine subsystem (animation/bones/properties), not by attribution capability. This is acceptable.
  - `EngineNoisePattern` — 6 Insight classes including `KahluaDumpInformation`. `KahluaDumpInformation` is the paired output of `StackTraceClassificationAnalyser` when `isNoise` is true (plan Phase 1.4 sketch). Placing it in `EngineNoisePattern` suggests it has a `getPatterns()` implementation for `PatternAnalyser`. But the plan's Phase 1.4 sketch shows it being constructed directly in the analyser, not via pattern matching. If `KahluaDumpInformation` is both a `PatternInsightInterface` (registered with `addPossibleInsightClass()`) and also constructed by `StackTraceClassificationAnalyser`, there is a second double-count risk for Kahlua dump entries.
  - `ConfigDriftPattern` — 2 Insight classes: `UnknownSandboxOptionInformation`, `UnknownItemParamInformation`. Configuration vocabulary mismatches. Cohesion is high.

  The `KahluaDumpInformation` boundary ambiguity is the most notable cohesion concern: its membership in `EngineNoisePattern` suggests `PatternAnalyser` registration, but its role in `StackTraceClassificationAnalyser` suggests direct construction. If both register and construct it, the double-count mirrors S-004.

- **Impact:** If `KahluaDumpInformation` is registered with `addPossibleInsightClass()` AND also emitted by `StackTraceClassificationAnalyser`, Kahlua dump entries will produce two `KahluaDumpInformation` instances in the merged `Analysis`. The deduplication in `Analysis::addInsight()` requires same-class + `isEqual()` returning true; if `KahluaDumpInformation::isEqual()` coalesces on nothing (marker class), the counter would increment. But if `StackTraceClassificationAnalyser` constructs a new instance each time, and `PatternAnalyser` separately constructs one, there will be two instances even with coalescing — because coalescing runs `get_class() === get_class()` and then `isEqual()`, which would return true if `isEqual()` always returns true for same-class. In that case the counter inflates to 2 but only one instance appears. This is likely the intended behavior but is not explicit.

---

**S-010: `Insight::jsonSerialize()` — `instanceof` checks in base class create a downward dependency from framework to game-specific capability interfaces**

- **Severity:** WARN
- **Dimension:** Dependency Direction
- **File(s):**
  - `/home/samkintop/opt/ik-codex/src/Analysis/Insight.php`
  - Plan Phase 1.6
- **Finding:** The plan adds to `Insight::jsonSerialize()`:

  ```php
  if ($this instanceof SeverityAwareInsightInterface) {
      $base['severity'] = $this->getSeverity()->value;
  }
  if ($this instanceof ModAttributedInsightInterface) {
      $base['mod'] = $this->getModAttribution();
  }
  if ($this instanceof EngineNoiseInsightInterface) {
      $base['engine_noise'] = true;
  }
  ```

  `Insight` is a framework-level abstract class in `src/Analysis/`. The three capability interfaces are also in `src/Analysis/` (framework-level, per S-005 finding). The `instanceof` checks therefore remain within the same namespace boundary — `Insight` is not importing from a game-specific namespace.

  The structural question is whether this pattern — a base class that interrogates its own subtype through interfaces — is appropriate. The existing `Problem::jsonSerialize()` already does `array_merge(parent::jsonSerialize(), ["solutions" => ...])` which is a similar pattern. The `instanceof SeverityAwareInsightInterface` check is an opt-in capability probe, not a downward coupling to a concrete implementation. This is the established "Capability Interface" pattern in this codebase (compare `PatternInsightInterface`).

  However: the base `Insight` class is now coupled to three new interfaces that exist solely to support the PZ epic's enrichment goals. If Hytale or Minecraft never implement these interfaces, the dead branches in `jsonSerialize()` carry no cost except marginal cognitive load. But the `Severity` enum is referenced directly from `Insight` (via the interface), which means the `Insight` base class indirectly pulls in `Severity.php` whenever PHP resolves the `instanceof` check. This is not a hard circular dependency (both live in `src/Analysis/`), but it increases the framework base class's effective surface area.

- **Impact:** Low structural risk given same-namespace placement. The precedent of adding capability probes to `jsonSerialize()` is consistent with existing patterns. The `Severity` enum coupling is acceptable. This finding is escalated to WARN only because the plan explicitly introduces this pattern as new — it was not present before the epic.

---

**S-011: `log.php` `method_exists` probe for `getCauseChain()` is an untyped interface bypass**

- **Severity:** WARN
- **Dimension:** Abstraction / Coupling
- **File(s):**
  - `/opt/iblogs/web/frontend/log.php`
  - Plan Phase 2.2
- **Finding:** The plan's template block includes:

  ```php
  $stack = method_exists($problem, 'getCauseChain') ? $problem->getCauseChain() : null;
  ```

  The three capability interfaces are expressed as PHP interfaces with proper `instanceof` checks elsewhere in the same template block:

  ```php
  $isNoise = $problem instanceof EngineNoiseInsightInterface;
  $severity = $problem instanceof SeverityAwareInsightInterface
      ? $problem->getSeverity() : Severity::Medium;
  $mod = $problem instanceof ModAttributedInsightInterface
      ? $problem->getModAttribution() : null;
  ```

  `getCauseChain()` is not part of any declared interface in the plan. The `method_exists()` call is a duck-typing probe that bypasses the PHP type system entirely. This creates implicit coupling between `log.php` and the internal implementation detail of `LuaModRuntimeProblem` — if the method is renamed, the template silently stops rendering the cause chain with no compile-time error, no IDE type error, and no test failure unless a dedicated rendering test exists.

  The fix is to declare a `CauseChainInsightInterface` (or add `getCauseChain(): ?string` to one of the existing capability interfaces) and use `instanceof` in the template. The cause chain is a structural property of the Insight, not an implementation detail — it warrants an interface.

- **Impact:** The `method_exists()` probe is a leaky abstraction in the template layer. It couples iblogs's rendering to the method name of a specific codex class without any contract enforcement. The other three capability checks in the same template use `instanceof` correctly; this one doesn't.

---

**S-012: `Setting::getDefault()` is a new method on an existing enum — iblogs `Settings` class must be checked for compatibility**

- **Severity:** SUGG
- **Dimension:** Coupling
- **File(s):**
  - Plan Phase 2.1 (Setting enum)
  - `/opt/iblogs/web/frontend/log.php` (lines 173–183, Settings popover loop)
- **Finding:** The plan adds a `getDefault(): bool` method to the `Setting` enum. The existing `log.php` template iterates `Setting::cases()` and calls `$setting->getLabel()` and `$setting->getBodyClass()`. The plan implies that `Settings::get(Setting $setting)` uses the new `getDefault()` as the default cookie value. The existing `Settings` class is not shown in the read files — it is at `/opt/iblogs/src/Frontend/Settings/Settings.php`. If `Settings::get()` currently hardcodes `false` as the default or does not call any method on the `Setting` enum for defaults, adding `getDefault()` to the enum is safe (additive). But if `HIDE_ENGINE_NOISE` should default to `true` (as the plan states), the `Settings` class must be updated to call `$setting->getDefault()` instead of its current default logic.

  This is marked as a suggestion because it is probably an easy wiring fix, but the plan does not show the `Settings::get()` call site, making it impossible to confirm without reading that file.

- **Impact:** If `Settings::get()` defaults all settings to `false` and is not updated to call `getDefault()`, the `HIDE_ENGINE_NOISE` setting will default to off instead of on. Engine noise will appear by default, contrary to the plan's intent.

---

**S-013: Open/Closed deferral of `InsightRegistry` is justified at the current scale**

- **Severity:** (negative result — sound)
- **Dimension:** Module Boundaries / Coupling
- **File(s):**
  - `/home/samkintop/opt/ik-codex/src/Log/ProjectZomboid/ProjectZomboidServerLog.php` (lines 34–41)
  - Plan "What we deferred" — A5
- **Finding:** After this epic, `getDefaultAnalyser()` in `ProjectZomboidServerLog` will contain approximately 19 `addPossibleInsightClass()` calls. The plan acknowledges the open/closed concern and defers `InsightRegistry` until the list exceeds ~30. The existing structure of `PatternAnalyser` is already open/closed-compliant at the analyser level — registration is the only modification point. At 19 entries the method is long but not pathological: it is a flat list with no branching, all entries are the same shape, and the full list fits in one screen. The merge-conflict risk at 19 entries is low. The deferral threshold of ~30 is reasonable.

- **Impact:** No structural risk at the planned scale.

---

**S-014: The `StackTraceClassificationAnalyser`'s `HIT_CAP` mirrors `ErrorContextAnalyser`'s — structural duplication is incidental**

- **Severity:** (negative result — sound)
- **Dimension:** Duplication
- **File(s):**
  - `/home/samkintop/opt/ik-codex/src/Analyser/ProjectZomboid/ErrorContextAnalyser.php` (line 59: `HIT_CAP = 500`)
  - Plan Phase 1.4 (`HIT_CAP = 500`)
- **Finding:** Both analysers independently define `public const int HIT_CAP = 500`. This is incidental duplication: each constant governs an independent cap on different processing pipelines. `ErrorContextAnalyser::HIT_CAP` limits how many error-context windows are emitted; `StackTraceClassificationAnalyser::HIT_CAP` limits how many stack trace blocks are classified. They happen to share a value but have different semantics and independent evolution paths. There is no shared caller. Extracting a common constant would couple unrelated analysers to a shared source of truth that has no single business meaning. The duplication should remain separate.

- **Impact:** No structural risk.

---

## Structural Summary

**Focus area analyzed:** The full plan for the PZ Error Pipeline epic — 15 new Insight classes, 3 capability interfaces, `ModAttribution` value object, `Severity` enum, `MultiPatternParser`, `CompositeAnalyser`, `StackTraceClassificationAnalyser`, `Insight::jsonSerialize()` enrichment, iblogs `log.php` extension. One layer outward: the existing `Analyser`, `Analysis`, `Parser`, `Log`, and `Log/ProjectZomboid` hierarchies; the iblogs `log.php` consumer.

**Key concerns:**

1. **S-004 (WARN) — Double-counting between `ServerExceptionProblem` and `LuaModRuntimeProblem`.** This is the most user-visible structural defect: `PatternAnalyser` and `StackTraceClassificationAnalyser` will both fire on mod-attributed exception entries. The `CompositeAnalyser` merge has no mechanism to suppress `ServerExceptionProblem` when a richer `LuaModRuntimeProblem` is already emitted for the same entry. The plan must either remove `ServerExceptionProblem` from the `PatternAnalyser` registration, narrow its pattern to exclude `Lua((MOD:X))` entries, or add a post-merge deduplication step in `CompositeAnalyser`. Without an explicit decision here, every production mod crash doubles in the problems panel.

2. **S-008 (WARN) — `CompositeAnalyser::setLog()` must propagate to child analysers.** The `AnalysableLog::analyse()` call chain calls `setLog()` only on the top-level analyser. If `CompositeAnalyser` extends `Analyser` without overriding `setLog()`, both child analysers will have a null `$this->log` and their `analyse()` implementations will fail. This will be caught by tests immediately but the framework gives no structural signal that this propagation is required.

3. **S-011 (WARN) — `method_exists($problem, 'getCauseChain')` in `log.php` is an untyped probe.** All other capability checks in the same template block use `instanceof`. The cause chain access is the lone exception. A `CauseChainInsightInterface` (or addition to an existing capability interface) would make this consistent and type-safe.

**Well-structured areas:**

- **Capability interface placement (S-005, negative):** `SeverityAwareInsightInterface`, `ModAttributedInsightInterface`, `EngineNoiseInsightInterface` are correctly placed at framework altitude in `src/Analysis/`. The abstraction boundary between the generic value object `ModAttribution` and the PZ-specific parsing in `StackTraceClassificationAnalyser` is cleanly drawn.
- **`MultiPatternParser` placement (S-007, negative):** Framework-level placement is justified. No PZ-specific logic enters the parser; the extension hook (`parseEntryMatch()`) is properly inherited.
- **`InsightRegistry` deferral (S-013, negative):** The 19-entry registration list is within the threshold where inline registration is more readable than a registry indirection. The deferral is correctly calibrated.
- **Incidental duplication in `HIT_CAP` (S-014, negative):** Correctly identified as incidental; the two constants serve semantically distinct purposes and should remain independent.

**Skipped dimensions:** Git churn analysis was used in the prior architectural analysis (`docs/superpowers/research/2026-06-05-pz-analyser-architecture-analysis.md`). This analysis focuses on the proposed plan rather than the existing codebase's churn, so no new churn data was collected. No new limitations apply.
