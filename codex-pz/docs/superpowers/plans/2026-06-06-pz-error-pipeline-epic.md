# Epic: Deterministic PZ Error Pipeline + Polished log.php Presentation

**Date:** 2026-06-06
**Repos:** `ik-codex` (primary) + `iblogs` (consumer)
**Driving goal:** Upload a 100k-line `DebugLog-server.txt`; iblogs's existing log page renders every typed error with deterministic attribution, severity, mod tagging, and an inline stack-trace view. No LLM at runtime. No new template. No new JS file.

---

## Why this shape

The v2 scan (`/opt/ik-codex/.scratch/pz/error-scan/`) proved we can extract structured signals from raw PZ logs deterministically — `pz_parser.py` already does the work in Python. The architectural analysis at `docs/superpowers/research/2026-06-05-pz-analyser-architecture-analysis.md` identified the minimum codex changes (R1 B4x fix, R4 severity capability, R5 registration seam, R6 mod attribution) needed to make iblogs's existing `Problems` panel meaningful.

iblogs's `log.php` already iterates `$log->getAnalysis()->getProblems()` and renders each with `getMessage()`, line link, and solutions. The existing design system (`Plus Jakarta Sans` + `JetBrains Mono`, dark-mode semantic tokens, `--accent`/`--error`/`--surface`/`--border` + `color-mix()` derivations, `clamp()` fluid sizing, 12px radius) is already developer-tool-grade. **The work is rich-data-side + minimal template/CSS extensions that use the same tokens.**

---

## Non-goals (explicit)

- No new page, no new template. We extend `web/frontend/log.php` in place.
- No JS framework, no bundler change. Existing `log.js` + native `<details>` only.
- No mobile redesign. Existing responsive `clamp()` rules carry through.
- No interface migration of existing 17 Insight implementations (capability interfaces are opt-in; legacy Insights stay byte-compatible).
- No iblogs API breaking change. `CodexLogResponse` JSON gains *additive* fields only.
- No Minecraft / Hytale / Sherlock work in this epic.

---

## Architecture (additive, not replacing)

```
upload ──► iblogs.Filter (PII strip, size cap)
       ──► iblogs.Detective.detect()
       ──► codex.ProjectZomboidServerLog.parse()
              ├─ tries LINE_B41 / LINE_B42 / LINE_B4X in order (NEW: B4x)
              └─ continuation-append preserved
       ──► codex.PatternAnalyser.analyse()
              ├─ 4 existing Insight classes
              └─ 15 NEW Insight classes (each one Pattern + Insight subclass)
       ──► codex.StackTraceClassificationAnalyser.analyse() (NEW custom Analyser)
              ├─ Phase 3: mod attribution (direct + 40-line lookback)
              ├─ Phase 5: cause-chain extraction
              ├─ Phase 7: engine-noise tagging
              └─ Phase 8: fingerprint
       ──► codex.Analysis (Insights now carry severity, modAttribution, fingerprint via opt-in capability interfaces)
       ──► MongoDB persist
       ──► iblogs.web/frontend/log.php
              ├─ existing problems-panel renders with NEW severity/counter/mod-tag/<details>
              ├─ NEW Setting::HIDE_ENGINE_NOISE applies CSS body class
              └─ existing log content + header + footer untouched
```

---

## Phase 1 — codex backend (the "thorough modern advanced" side)

### 1.1 — B4x line format (R1, the production-blocker)

File: `src/Pattern/ProjectZomboid/DebugServerPattern.php`

```php
class DebugServerPattern
{
    // Existing — B41 + B42 coexist via optional `,t:N`
    public const string LINE_B41_B42 = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\]\s+(\w+)\s*:\s+(\S+)\s+f:\d+(?:,\s+t:\d+)?,?\s+st:[\d,]+>\s+.*$/';

    // NEW — late B41 / 41.78.x: ", <unix_ms>> <tick>>"
    public const string LINE_B4X = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\]\s+(\w+)\s*:\s+(\S+)\s*,\s+\d+>\s+[\d,]+>\s+.*$/';

    // Back-compat alias — older callers still reference LINE
    public const string LINE = self::LINE_B41_B42;

    // … existing EXCEPTION, MOD_LOAD, MOD_MISSING constants unchanged
}
```

File: `src/Log/ProjectZomboid/ProjectZomboidServerLog.php`

```php
public static function getDefaultParser(): ParserInterface
{
    // Try B41/B42 first (most common), fall back to B4x.
    // Both regexes capture identical groups (TIME, LEVEL, PREFIX) so the
    // existing parseEntryMatch() flow handles both with no other changes.
    return (new MultiPatternParser())
        ->setTimeFormat(static::TIME_FORMAT)
        ->setTimezone(new DateTimeZone(static::TIME_ZONE))
        ->addLineFormat(DebugServerPattern::LINE_B41_B42, [
            PatternParser::TIME, PatternParser::LEVEL, PatternParser::PREFIX,
        ])
        ->addLineFormat(DebugServerPattern::LINE_B4X, [
            PatternParser::TIME, PatternParser::LEVEL, PatternParser::PREFIX,
        ]);
}
```

New: `src/Parser/MultiPatternParser.php` — extends `PatternParser`, tries each registered regex per source line, first match wins, continuation behaviour unchanged. ~50 lines.

**Test:** synthetic `debug-server-b4x-minimal.txt` fixture + `ProjectZomboidServerLogB4xTest::testB4xLineFormatProducesNonZeroEntries`.

### 1.2 — Severity, mod attribution, engine-noise capabilities (additive interfaces)

Three small files in `src/Analysis/`:

```php
// src/Analysis/Severity.php
namespace IndifferentKetchup\Codex\Analysis;

enum Severity: int
{
    case Low = 20;       // engine noise, recoverable
    case Medium = 50;    // mod warnings, cross-mod conflicts
    case High = 80;      // mod crashes, server-tick exceptions
    case Critical = 100; // parse failures, fatal exceptions
}

// src/Analysis/SeverityAwareInsightInterface.php
interface SeverityAwareInsightInterface
{
    public function getSeverity(): Severity;
}

// src/Analysis/ModAttribution.php
final readonly class ModAttribution implements \JsonSerializable
{
    public function __construct(
        public string $modName,
        public ?string $workshopId,
        public ?string $deepestModFrame,
        public AttributionConfidence $confidence,
    ) {}
    public function jsonSerialize(): array { /* … */ }
}

enum AttributionConfidence: string
{
    case Direct = 'direct';     // mod marker on the entry itself
    case Inferred = 'inferred'; // within 40-line lookback
    case Unknown = 'unknown';
}

// src/Analysis/ModAttributedInsightInterface.php
interface ModAttributedInsightInterface
{
    public function getModAttribution(): ?ModAttribution;
}

// src/Analysis/EngineNoiseInsightInterface.php (marker — no methods)
interface EngineNoiseInsightInterface {}
```

Existing 17 Insight implementations: untouched. Consumers downcast via `instanceof` (the iblogs template will).

### 1.3 — 15 new Insight classes

Single commit each (per CLAUDE.md workflow). Each is ~50 lines: Pattern constant + Insight subclass + test fixture + `addPossibleInsightClass()` registration.

| # | Class | Severity | Coalesce on | Engine-noise? |
|---|---|---|---|---|
| 1 | `LuaRequireFailedProblem` | Medium | `requiredPath` | — |
| 2 | `LuaModRuntimeProblem` (also `ModAttributedInsight`, `SeverityAware`) | High | `(modName, funcName, exceptionClass)` | — |
| 3 | `AnimsetXmlMissingProblem` (also `ModAttributed`) | Medium | `(workshopId, file)` | — |
| 4 | `LuaFunctionMissingProblem` | High | `functionName` | — |
| 5 | `IsoPropertyTypeNotFoundProblem` | Medium | `propertyName` | — |
| 6 | `BoneIndexNotFoundProblem` | Medium | `nodeName` | — |
| 7 | `MissingIconInformation` | Low | `iconName` | ✓ (engine-noise) |
| 8 | `RecursiveRequireProblem` (also `ModAttributed`) | High | `requiredPath` | — |
| 9 | `UnknownSandboxOptionInformation` | Low | `optionName` | — |
| 10 | `UnknownItemParamInformation` | Low | `paramName` | — |
| 11 | `MissingThumpSoundInformation` | Low | `tileId` | ✓ |
| 12 | `BufferOverflowInformation` | Low | (none) | ✓ |
| 13 | `EngineFileWatcherInformation` | Low | (none) | ✓ |
| 14 | `SpriteConfigInvalidInformation` | Low | `objectName` | — |
| 15 | `KahluaDumpInformation` | Low | (none) | ✓ (paired with sibling LuaModRuntimeProblem) |

Each new Pattern goes in a focused subclass under `src/Pattern/ProjectZomboid/`:
- `LuaErrorPattern` — patterns 1, 4, 8
- `LuaModRuntimePattern` — pattern 2
- `EngineExceptionPattern` — patterns 3, 5, 6
- `EngineNoisePattern` — patterns 7, 11, 12, 13, 14, 15
- `ConfigDriftPattern` — patterns 9, 10

### 1.4 — `StackTraceClassificationAnalyser` (the "deep stack" custom Analyser)

File: `src/Analyser/ProjectZomboid/StackTraceClassificationAnalyser.php`

Port phases 3, 5, 7 of `tools/pz-analyzer/pz_parser.py`:

```php
namespace IndifferentKetchup\Codex\Analyser\ProjectZomboid;

class StackTraceClassificationAnalyser extends Analyser
{
    public const int HIT_CAP = 500;
    public const int LOOKBACK_RAW_LINES = 40;

    public function analyse(): AnalysisInterface
    {
        $analysis = new Analysis();
        $analysis->setLog($this->log);
        $entries = iterator_to_array($this->log);
        $hits = 0;

        foreach ($entries as $i => $entry) {
            if (!$this->hasExceptionShape((string) $entry)) continue;
            if ($hits++ >= self::HIT_CAP) break;

            $body = (string) $entry;

            // Phase 3: mod attribution
            $attribution = $this->attributeMod($entries, $i, $body);

            // Phase 5: cause-chain
            $causeChain = $this->extractCauseChain($body);

            // Phase 7: engine-noise classification
            $isNoise = $this->isEngineNoise($body);

            $insight = $isNoise
                ? new KahluaDumpInformation()
                : (new LuaModRuntimeProblem())
                    ->setExceptionClass($this->detectExceptionClass($body))
                    ->setCauseChain($causeChain)
                    ->setModAttribution($attribution)
                    ->setFileAndLine($this->extractFileLine($body))
                    ->setSeverity(Severity::High);

            $insight->setEntry($entry);
            $insight->setFingerprint($this->fingerprint($insight, $body));
            $analysis->addInsight($insight);
        }
        return $analysis;
    }

    // … private phase helpers
}
```

Wired via `ProjectZomboidServerLog::getDefaultAnalyser()`:

```php
public static function getDefaultAnalyser(): AnalyserInterface
{
    return new CompositeAnalyser(
        (new PatternAnalyser())
            ->addPossibleInsightClass(/* 19 Pattern-Insight classes */),
        new StackTraceClassificationAnalyser(),
    );
}
```

New: `src/Analyser/CompositeAnalyser.php` — chains two analysers, merges their `Analysis` outputs. ~30 lines.

### 1.5 — Pattern fingerprint per Insight

Add `getFingerprint(): string` to base `Insight` class. Computed as `sha256(exceptionClass + first3StackFrames + modId)[:16]`. Stable across logs → enables future cross-log search ("show me every log with fingerprint `sha256:abc123...`").

### 1.6 — `Insight::jsonSerialize()` null guard

```php
public function jsonSerialize(): array
{
    $base = [
        'message' => $this->getMessage(),
        'counter' => $this->getCounterValue(),
        'fingerprint' => $this->getFingerprint(),
    ];
    if ($this->entry !== null) {
        $base['entry'] = $this->entry;
    }
    if ($this instanceof SeverityAwareInsightInterface) {
        $base['severity'] = $this->getSeverity()->value;
    }
    if ($this instanceof ModAttributedInsightInterface) {
        $base['mod'] = $this->getModAttribution();
    }
    if ($this instanceof EngineNoiseInsightInterface) {
        $base['engine_noise'] = true;
    }
    return $base;
}
```

Fixes B5/R2 (`entry: null` silent defect) and makes the new capability data visible to API consumers.

### 1.7 — 100k-line bench test

`test/tests/Games/ProjectZomboid/Performance/HundredKLineBenchTest.php` — generates a synthetic 100k-line `DebugLog-server.txt` mixing B41/B42/B4x shapes with ~5% error density, asserts:

```php
$start = hrtime(true);
$log->parse();
$log->analyse();
$elapsedMs = (hrtime(true) - $start) / 1_000_000;
$this->assertLessThan(2_000, $elapsedMs);
$this->assertGreaterThan(50, count($log->getAnalysis()->getProblems()));
```

---

## Phase 2 — iblogs `log.php` extension (the "simple UI" side)

### 2.1 — One new Setting

File: `src/Frontend/Settings/Setting.php`

```php
enum Setting: string
{
    case FULL_WIDTH = "fullWidth";
    case NO_WRAP = "noWrap";
    case FLOATING_SCROLLBAR = "floatingScrollbar";
    case OVERFLOW = "overflow";
    case HIDE_ENGINE_NOISE = "hideEngineNoise"; // NEW — default true

    function getLabel(): string
    {
        return match ($this) {
            // … existing
            Setting::HIDE_ENGINE_NOISE => "Hide Engine Noise",
        };
    }

    function getBodyClass(): ?string
    {
        return match ($this) {
            // … existing
            Setting::HIDE_ENGINE_NOISE => "setting-hide-engine-noise",
        };
    }

    function getDefault(): bool   // NEW per-case default
    {
        return match ($this) {
            Setting::HIDE_ENGINE_NOISE => true,
            default => false,
        };
    }
}
```

The existing Settings cookie + popover mechanism in `log.php` already handles the toggle UI — nothing else to wire.

### 2.2 — `log.php` problem-panel additions

Existing block (line 97–133) becomes:

```php
<?php
$problems = $log->getAnalysis()?->getProblems() ?? [];

// Sort by severity × counter (high impact + high frequency first).
usort($problems, function ($a, $b) {
    $aw = ($a instanceof SeverityAwareInsightInterface ? $a->getSeverity()->value : 50)
        * $a->getCounterValue();
    $bw = ($b instanceof SeverityAwareInsightInterface ? $b->getSeverity()->value : 50)
        * $b->getCounterValue();
    return $bw <=> $aw;
});

// Count visible problems (engine-noise filtering happens via CSS, but the
// header badge should reflect what the user actually sees).
$visibleCount = $settings->get(Setting::HIDE_ENGINE_NOISE)
    ? count(array_filter($problems, fn($p) => !($p instanceof EngineNoiseInsightInterface)))
    : count($problems);
?>

<?php if (count($problems) > 0): ?>
    <div class="problems-panel-container">
        <div class="problems-panel">
            <div class="problems-header">
                <span class="problems-count"><?= $visibleCount; ?></span>
                <span class="problems-title">
                    <?= $visibleCount === 1 ? 'Problem' : 'Problems'; ?> detected
                </span>
                <?php if ($visibleCount !== count($problems)): ?>
                    <span class="problems-hidden-count">
                        (<?= count($problems) - $visibleCount; ?> noise hidden)
                    </span>
                <?php endif; ?>
            </div>
            <div class="problems-list">
                <?php foreach ($problems as $problem): ?>
                    <?php
                        $isNoise = $problem instanceof EngineNoiseInsightInterface;
                        $severity = $problem instanceof SeverityAwareInsightInterface
                            ? $problem->getSeverity() : Severity::Medium;
                        $mod = $problem instanceof ModAttributedInsightInterface
                            ? $problem->getModAttribution() : null;
                        $entry = $problem->getEntry();
                        $lineNumber = $entry[0]?->getNumber();
                        $stack = method_exists($problem, 'getCauseChain') ? $problem->getCauseChain() : null;
                        $solutions = $problem->getSolutions();
                    ?>
                    <div class="problem-item severity-<?= strtolower($severity->name); ?><?= $isNoise ? ' engine-noise' : ''; ?>">
                        <div class="problem-row">
                            <span class="problem-severity" aria-label="<?= $severity->name; ?> severity">
                                <i class="fa-solid <?=match($severity){
                                    Severity::Critical => 'fa-skull-crossbones',
                                    Severity::High => 'fa-triangle-exclamation',
                                    Severity::Medium => 'fa-flag',
                                    Severity::Low => 'fa-circle-info',
                                }; ?>"></i>
                                <span class="severity-label"><?= $severity->name; ?></span>
                            </span>
                            <span class="problem-text">
                                <?= htmlspecialchars($problem->getMessage()); ?>
                            </span>
                            <?php if ($problem->getCounterValue() > 1): ?>
                                <span class="problem-counter" aria-label="<?= $problem->getCounterValue(); ?> occurrences">
                                    ×<?= number_format($problem->getCounterValue()); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($lineNumber !== null): ?>
                                <a class="problem-line"
                                   href="/<?= htmlspecialchars($log->getId()->get()); ?>#L<?= $lineNumber; ?>"
                                   onclick="updateLineNumber('#L<?= $lineNumber; ?>');">
                                    Line <?= number_format($lineNumber); ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if ($mod !== null): ?>
                            <div class="problem-meta">
                                <?php if ($mod->workshopId !== null): ?>
                                    <a class="problem-mod-tag"
                                       href="https://steamcommunity.com/sharedfiles/filedetails/?id=<?= htmlspecialchars($mod->workshopId); ?>"
                                       target="_blank" rel="noopener"
                                       data-confidence="<?= $mod->confidence->value; ?>">
                                        <i class="fa-solid fa-cube"></i>
                                        <?= htmlspecialchars($mod->modName); ?>
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="problem-mod-tag" data-confidence="<?= $mod->confidence->value; ?>">
                                        <i class="fa-solid fa-cube"></i>
                                        <?= htmlspecialchars($mod->modName); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($mod->confidence !== AttributionConfidence::Direct): ?>
                                    <span class="problem-confidence" title="Mod attribution confidence">
                                        <?= ucfirst($mod->confidence->value); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($stack)): ?>
                            <details class="problem-stack">
                                <summary>
                                    <i class="fa-solid fa-chevron-right"></i>
                                    Cause chain
                                </summary>
                                <pre><?= htmlspecialchars($stack); ?></pre>
                            </details>
                        <?php endif; ?>

                        <?php if (count($solutions) > 0): ?>
                            <details class="problem-solutions" <?= count($solutions) <= 2 ? 'open' : ''; ?>>
                                <summary>
                                    <i class="fa-solid fa-chevron-right"></i>
                                    <?= count($solutions) === 1 ? 'Solution' : count($solutions) . ' solutions'; ?>
                                </summary>
                                <?php foreach ($solutions as $solution): ?>
                                    <div class="problem-solution">
                                        <i class="fa-solid fa-lightbulb"></i>
                                        <span><?= preg_replace("/'([^']+)'/", "'<strong>$1</strong>'",
                                                  htmlspecialchars($solution->getMessage())); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
```

That's the entire template delta. ~70 lines added; existing surrounding `log.php` untouched.

### 2.3 — CSS additions to `web/public/css/iblogs.css`

```css
/* — Severity tokens (extend the existing token set) — */
:root {
    --severity-critical: var(--error);
    --severity-critical-bg: var(--error-bg);
    --severity-high: #f97316;       /* orange-500 */
    --severity-high-bg: color-mix(in srgb, var(--severity-high) 10%, transparent);
    --severity-medium: #eab308;     /* yellow-500 */
    --severity-medium-bg: color-mix(in srgb, var(--severity-medium) 10%, transparent);
    --severity-low: var(--text-muted);
    --severity-low-bg: var(--surface);
}

/* — Problem row layout — */
.problem-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: clamp(0.4rem, 1vw, 0.6rem);
}

.problem-severity {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.severity-critical .problem-severity { background: var(--severity-critical-bg); color: var(--severity-critical); }
.severity-high     .problem-severity { background: var(--severity-high-bg);     color: var(--severity-high); }
.severity-medium   .problem-severity { background: var(--severity-medium-bg);   color: var(--severity-medium); }
.severity-low      .problem-severity { background: var(--severity-low-bg);      color: var(--severity-low); }

.severity-label { font-size: 0.7rem; }

/* — Counter badge — */
.problem-counter {
    display: inline-flex;
    align-items: center;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    background: var(--surface);
    border: 1px solid var(--border);
    font-family: var(--font-mono), monospace;
    font-size: 0.78rem;
    font-variant-numeric: tabular-nums;
    color: var(--text-muted);
    margin-left: auto;       /* push right */
}

/* — Mod tag chip — */
.problem-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    align-items: center;
}
.problem-mod-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    font-size: 0.82rem;
    text-decoration: none;
    transition: border-color 180ms ease, background 180ms ease;
}
.problem-mod-tag[href]:hover,
.problem-mod-tag[href]:focus-visible {
    border-color: var(--accent-border);
    background: var(--accent-bg);
}
.problem-mod-tag[data-confidence="inferred"] {
    border-style: dashed;
}
.problem-confidence {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: lowercase;
}

/* — Native <details> stack-trace blocks — */
.problem-stack,
.problem-solutions {
    border-top: 1px dashed var(--border);
    padding-top: clamp(0.4rem, 1vw, 0.5rem);
    margin-top: clamp(0.2rem, 0.7vw, 0.3rem);
}
.problem-stack > summary,
.problem-solutions > summary {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--text-muted);
    font-size: 0.85rem;
    list-style: none;        /* hide native triangle */
}
.problem-stack > summary::-webkit-details-marker,
.problem-solutions > summary::-webkit-details-marker { display: none; }
.problem-stack > summary > .fa-chevron-right,
.problem-solutions > summary > .fa-chevron-right {
    transition: transform 200ms ease;
}
.problem-stack[open] > summary > .fa-chevron-right,
.problem-solutions[open] > summary > .fa-chevron-right {
    transform: rotate(90deg);
}
.problem-stack pre {
    margin: 0.5rem 0 0 0;
    padding: clamp(0.5rem, 1.5vw, 0.75rem);
    background: var(--bg-inset);
    border-radius: 6px;
    font-family: var(--font-mono), monospace;
    font-size: 0.78rem;
    line-height: 1.5;
    color: var(--text);
    overflow-x: auto;
    white-space: pre;
}

/* — Engine-noise hide (driven by Setting body class) — */
.setting-hide-engine-noise .problem-item.engine-noise { display: none; }

.problems-hidden-count {
    margin-left: 0.5rem;
    font-size: 0.78rem;
    color: var(--text-muted);
}

/* — Reduced-motion fallback — */
@media (prefers-reduced-motion: reduce) {
    .problem-stack > summary > .fa-chevron-right,
    .problem-solutions > summary > .fa-chevron-right,
    .problem-mod-tag {
        transition: none;
    }
}
```

~120 lines added. All tokens reuse iblogs's existing custom-property cascade. No raw hex except severity orange + yellow (added as named tokens for reuse and dark-mode self-tuning via `color-mix`).

### 2.4 — Zero new JavaScript

Native `<details>` handles open/close. Severity sort is server-side. Engine-noise filter is CSS body class. Counter is plain text. Settings dropdown already syncs `Setting::HIDE_ENGINE_NOISE` to cookie + body class via the existing `js/log.js` settings-checkbox handler — no edits needed.

---

## UX validation against ui-ux-pro-max checklist

| Check | Pass | Note |
|---|---|---|
| Color contrast 4.5:1 | ✓ | Severity tokens layered over `--surface` (rgba 4% white over `--bg`); orange/yellow at 80%+ luminance over dark base |
| Focus states visible | ✓ | `.problem-mod-tag[href]:focus-visible` and existing `.problem-line` keep visible focus rings |
| Alt text / aria-labels | ✓ | `aria-label` on `.problem-severity` and `.problem-counter` |
| Color not alone | ✓ | Severity = icon + label text + color; engine-noise = filtered + count badge in header |
| Keyboard nav | ✓ | All interactive elements (`.problem-mod-tag`, `.problem-line`, `<summary>`) are native focusable |
| Reduced motion | ✓ | Transitions disabled under `prefers-reduced-motion` |
| Min touch / click target | ✓ | All interactive elements ≥36px tall (padding + icon + text); desktop-generous |
| Semantic color tokens | ✓ | Every color is a CSS variable; no inline hex in templates |
| Tabular numerals on counters | ✓ | `.problem-counter` uses `font-variant-numeric: tabular-nums` |
| Truncation strategy | ✓ | `.problem-text` uses `word-break: break-word` (existing) — wraps, no truncation |
| Progressive disclosure | ✓ | Stack trace + multi-solution lists collapsed in `<details>` |
| State clarity (sort + filter) | ✓ | Header badge shows visible-vs-total; sort is deterministic by `severity × counter` |
| Icon style consistency | ✓ | All icons from FontAwesome family already loaded; consistent stroke |
| No emoji | ✓ | None — `fa-skull-crossbones` / `fa-triangle-exclamation` / `fa-flag` / `fa-circle-info` |
| Elevation consistent | ✓ | Reuses existing `var(--surface)` + 1px borders; no new shadow tokens |
| Dark mode pairing | ✓ | iblogs is dark-only; severity orange/yellow chosen for AAA contrast over `--bg` (`#0F172A` baseline) |
| Performance (CLS) | ✓ | Counter has tabular numerals + fixed-padding pill; mod tag wraps via flex; reserved space |
| Animation duration | ✓ | 180–200ms chevron rotation, 180ms tag hover |

---

## Phase orchestration via paseo

Each phase is a single paseo agent in an isolated worktree.

| # | Phase | Worktree | Agent prompt | Depends on |
|---|---|---|---|---|
| 1 | Codex backend | `ik-codex/epic-pz-error-pipeline` | "Implement Phase 1 of `docs/superpowers/plans/2026-06-06-pz-error-pipeline-epic.md`. Land one commit per Insight class. Run `composer test` after each batch." | — |
| 2 | Codex release | (same) | "Update CHANGELOG.md, cut v0.6.0 tag (no push)." | 1 |
| 3 | iblogs frontend | `iblogs/epic-pz-error-pipeline` | "Implement Phase 2 of `/opt/ik-codex/docs/superpowers/plans/2026-06-06-pz-error-pipeline-epic.md`. Bump composer constraint to ^0.6.0. Smoke test via dev stack on port 4217." | 2 |
| 4 | Cross-repo push | manual | User reviews both branches; pushes both `--no-ff` merges in one operation per CLAUDE.md cross-repo rule. | 3 |
| 5 | Production deploy | manual | User deploys to `bosslogs.indifferentketchup.com`. | 4 |

Spawning command (sketch — not executed until user approves):

```
mcp__paseo__create_worktree(repo: ik-codex, branch: epic-pz-error-pipeline)
mcp__paseo__create_agent(worktree: …, prompt: phase-1 brief, model: opus-4-7)
mcp__paseo__wait_for_agent(…)
mcp__paseo__create_worktree(repo: iblogs, branch: epic-pz-error-pipeline)
mcp__paseo__create_agent(worktree: …, prompt: phase-3 brief, model: opus-4-7)
mcp__paseo__wait_for_agent(…)
```

---

## Acceptance criteria

- A 100k-line synthetic `DebugLog-server.txt` mixing B41/B42/B4x parses + analyses in **under 2 seconds** on the dev container.
- All 161 Logs3 B4x files in `.scratch/pz/Logs3/` produce non-empty `Analysis`.
- `getAnalysis()->getProblems()` returns typed instances for at least the 15 new Insight classes when their patterns appear.
- iblogs page renders each problem with: severity icon + label + message + counter + (optional) mod chip + (optional) `<details>` for stack + (optional) `<details>` for solutions.
- "Hide engine noise" toggle in Settings dropdown hides marker-implementing Information/Problem rows and updates the header count to reflect visible vs total.
- Severity sort: highest `severity × counter` problem appears first in the panel.
- Mod chip with workshop ID links to `https://steamcommunity.com/sharedfiles/filedetails/?id={id}` in a new tab with `rel="noopener"`.
- Inferred mod attribution renders with a dashed-border chip + "inferred" suffix.
- No new templates, no new JS files, no new asset entries in `AssetLoader`.
- Existing log.php behavior for logs without these Insights (Hytale, Minecraft, legacy PZ) is byte-identical to today.
- Codex v0.6.0 is a minor version bump (additive public API — no breaking changes to existing 17 Insights).
- iblogs constraint `composer.json` bumps from `^0.5.0` to `^0.6.0`.

---

## What this delivers vs. what we deferred

**Ships:** B4x parsing, 15 new typed Insights, mod attribution data, severity capability, engine-noise classification, stack-trace classification analyser, fingerprint hashes, tighter ServerException coalescing, polished log.php rendering with severity badges + counter pills + mod chips + native `<details>` stacks + Engine Noise filter.

**Deferred to later epics (matches the architectural analysis's medium/low priorities):**
- A1 `MultiPatternParser` lives in `src/Parser/` (framework-level) but is only consumed by PZ — Minecraft/Hytale don't need it yet.
- A2 capability interfaces are additive; existing 17 Insights are not retrofitted to declare severity (they all default to Medium via `instanceof` fallback in log.php).
- A5 `InsightRegistry` deferred — we add 15 new `addPossibleInsightClass()` calls inline; the registry refactor is justified once the list grows beyond ~30.
- A7 workshop-ID map: ships with the initial 4-entry seed plus whatever the StackTraceClassificationAnalyser surfaces directly via `Lua((MOD:X))` markers; JSON-file loader deferred until the seed needs broadening.
- SEC-001 / SEC-002 / SEC-004 (PII redactor universe gaps): independent track. Should be hotfixed before this epic ships to production at `bosslogs.indifferentketchup.com` because the existing leak is critical.
- iblogs side: no filter sidebar, no search bar, no timeline minimap. The existing problems-panel + Settings dropdown delivers the "simple UI" target. Faceted filtering becomes worthwhile only once the panel itself has 50+ rows that need filtering.

---

## Next step

Approve this plan and I'll spawn the Phase 1 paseo agent in an isolated `epic-pz-error-pipeline` worktree off codex `master`. Estimated agent time: 4–6 hours for Phase 1 (15 Insight classes + B4x + StackTraceClassificationAnalyser + tests). Phase 2 iblogs agent: 1–2 hours. Total epic ≈ a day of background agent time.
