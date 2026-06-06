# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`indifferentketchup/codex-pz` — a generic PHP log parsing and analysis framework, plus per-game subclasses that adapt the framework to specific games' log formats. PHP `>=8.4`, MIT license. Forked from `aternos/codex`; namespace was renamed in-tree (`Aternos\Codex` → `IndifferentKetchup\CodexPz`) — only the LICENSE retains the original Aternos GmbH copyright line, which must remain byte-for-byte (MIT requires it).

## Local environment

PHP and Composer are **not** installed on the host. All Composer/PHPUnit invocations go through the official `composer:latest` Docker image (currently PHP 8.5, satisfies the `>=8.4` floor):

```
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest <subcommand>
```

Use `$(pwd)` or an absolute path — bare `$PWD` has misfired here, mounting nothing and silently no-op'ing the run.

For ad-hoc PHP that needs the codex autoloader (e.g. running `ProjectZomboidRedactor::redact()` over a directory of log files, or eyeballing analyser output), override the entrypoint:

```
docker run --rm --entrypoint php -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest -r '<php source>'
```

## Common commands

- All tests: `composer test` (= `phpunit test/tests` per `composer.json`)
- One test file or method (wrap in the same docker invocation):
  `docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest vendor/bin/phpunit --filter=testFooBar test/tests/path/to/SomeTest.php`
- Refresh autoloader after editing `composer.json`: `composer dump-autoload`
- After cloning: `composer install` (writes `vendor/`, gitignored)

## Framework architecture

```
LogFile (Path|String|Stream)
   │
   ▼
Log  ── extends AnalysableLog ── implements DetectableLogInterface
   │      │                          │
   │      │                          └─ static getDetectors(): Detector[]
   │      └─ static getDefaultAnalyser(): Analyser
   ├─ static getDefaultParser(): Parser
   │
   ▼  Log->parse()
Entry[] of Line[]   (each Entry has level, time, prefix, lines)
   │
   ▼  Log->analyse()
Analysis of Insight[]
            └── Information (label + value)  or
                Problem (with attached Solution[])
```

- **`Detective`** ranks candidate Log subclasses by running each candidate's `getDetectors()` and picking the highest-scoring result (`bool|float`). It receives a `LogFile`, returns a constructed `Log` subclass.
- **`PatternParser`** is regex-driven. Lines that don't match the LINE regex append to the previous `Entry` — this is the mechanism that handles multi-line records like Java stack traces under an ERROR header.
- **`PatternAnalyser`** walks entries, runs each registered insight class's static `getPatterns()` against entry text via `preg_match_all`, and emits coalesced insights (equal insights bump a counter instead of duplicating).
- **Custom `Analyser` subclasses** are the right move when analysis needs cross-entry state — pairing events, sliding-window thresholds, comparing consecutive snapshots. `PatternAnalyser` operates per-entry only and can't express those. Phase B.3 (`ConnectionFailureAnalyser`, `ItemDuplicationAnalyser`, `SkillProgressionAnomalyAnalyser`) shows the shape: extend `Analyser`, override `analyse()`, walk `$this->log` once, aggregate, then emit coalesced `Problem`/`Information` insights at the end. Tunable thresholds belong as `public const` constants on the subclass with the rationale in a docblock.
- **`RedactorInterface`** is a render-time PII filter — string-in/string-out, configured per game, implemented at `src/Util/<Game>/<Game>Redactor.php`. Consumers call `redact(string $content): string` on a concrete instance before rendering or exporting log content.
- Detectors available out of the box: `SinglePatternDetector`, `WeightedSinglePatternDetector`, `LinePatternDetector` (returns match ratio), `MultiPatternDetector` (AND), and the path-based `FilenameDetector` (uses `LogFileInterface::getPath()`, returns `false` when no path is available).

## Game subtrees

Layout is **components-outer with game suffix**, not games-outer:

```
src/<Component>/<Game>/...   e.g. src/Log/ProjectZomboid/ProjectZomboidServerLog.php
src/Pattern/<Game>/<Type>Pattern.php   (regex string constants; not a framework abstraction)
src/Util/<Game>/...          e.g. src/Util/ProjectZomboid/ProjectZomboidRedactor.php
test/tests/Games/<Game>/...
test/src/Games/<Game>/fixtures/<type>-minimal.txt   (synthetic fixtures only)
```

`src/Util/` is the sixth top-level component directory, introduced post-v0.1.0-tag. Its first occupant is the Redactor; future game-agnostic utilities (tokenising redactor variants, etc.) land here too.

Scaffolded games:

- `Hytale` — `HytaleServerLog` + `HytaleClientLog` ported from `aternos/codex-hytale`, plus `HytaleDetective` registering both. Auto-detect via `FirstLinesPatternDetector` on the upstream banner regexes. Analyser/Analysis ports deferred to Phase 2 (`getDefaultAnalyser()` returns an empty `PatternAnalyser` for now).
- `Minecraft` — Vanilla server only in v0.4.0: `MinecraftLog` abstract base, `VanillaServerLog`, `MinecraftDetective` registering it. Banner detection via `FirstLinesPatternDetector`. The 30+ other variants from `aternos/codex-minecraft` (Fabric, Forge, Bukkit/Spigot/Paper, etc.) are deferred to Phase 2. `MinecraftInlineFormat` (in `src/Printer/Minecraft/`) translates ANSI escape codes to `format-<name>` HTML spans.
- `SevenDaysToDie` — stub only (empty subdirectories plus a TODO detective stub).

`ProjectZomboid` continues to be the most fully-implemented game: 11 log subclasses, 11 pattern classes, detective wired with all 11, synthetic fixtures, dispatch tests, plus the analyser surface — 11 `PatternAnalyser`-driven Insight classes under `src/Analysis/ProjectZomboid/` and 4 custom `Analyser` subclasses under `src/Analyser/ProjectZomboid/` for cross-entry / threshold logic.

`src/Pattern/` is **not a framework abstraction** — patterns are plain `string` class constants. Each `<Type>Pattern` typically holds a `LINE` constant for the parser plus named-group extractor constants (`FIELDS`, `COMBAT`, `MOD_LOAD`, etc.) for analysers.

### ProjectZomboid specifics

- Two abstract bases: `ProjectZomboidLog` (`TIME_FORMAT = 'd-m-y H:i:s.v'`, UTC default, `makePatternParser()` helper) and `ProjectZomboidEventLog` (marker for the ten single-line logs; `ProjectZomboidServerLog` extends the parent directly because it permits multi-line entries).
- `ProjectZomboidDetective::__construct()` pre-registers all 11 log classes — instantiate it and call `setLogFile(...)->detect()`.
- Each Log subclass's `getDefaultAnalyser()` returns one of:
  - A custom `Analyser` subclass (cross-entry logic): `UserLog → ConnectionFailureAnalyser`, `ItemLog → ItemDuplicationAnalyser`, `PerkLog → SkillProgressionAnomalyAnalyser`.
  - A configured `PatternAnalyser` (per-entry pattern matching): `ServerLog`, `PvpLog`, `AdminLog` register their respective Insight classes.
  - An empty `PatternAnalyser` for logs with no analysers yet: `ChatLog`, `ClientActionLog`, `CmdLog`, `MapLog`, `BurdJournalsLog`. These are wiring stubs awaiting future analysis work.
- **`ProjectZomboidRedactor`** at `src/Util/ProjectZomboid/ProjectZomboidRedactor.php` — concrete `RedactorInterface` implementation. Downstream consumers call `redact(string): string` to scrub Steam IDs (zeroed placeholder), player names (`<player>`), and world coordinates (`0,0,0`) from log content. Three independent toggle methods default to on: `redactSteamIds(bool)`, `redactPlayerNames(bool)`, `redactCoordinates(bool)`. Pass order (Steam ID → player name → coords) is mandatory and enforced internally — see Pitfall 5.
- **`ProjectZomboidModAttributor`** at `src/Util/ProjectZomboid/ProjectZomboidModAttributor.php` — concrete `Modification` + `StackTraceEnricherInterface` implementation. Decorates `MOD:NAME)` tokens (from `Lua((MOD:NAME))` stack frames in `KahluaThread.flushErrorMessage` blocks) with HTML spans carrying Workshop IDs from a small static map. Idempotent; UTF-8-safe; `htmlspecialchars`-escapes the captured name. The iblogs companion (CSS `.mod-attribution` block + JS click handler that opens the Steam Workshop URL on `[data-workshop-id]`) ships in iblogs branch `pz-enrichment-iblogs-bootstrap`. Phase 2-B will broaden the workshop-ID map.

### Standard test template for a Log subclass

At minimum: (1) entry count after `parse()` matches the synthetic fixture's line count, (2) one or more named-group `FIELDS` regexes from the `<Type>Pattern` class extract correctly from a representative line, (3) `Detective` handed the fixture path returns an instance of this Log class. Use `#[DataProvider]` when the same shape repeats per file.

### Downstream consumers

`iblogs` (sibling repo at `/opt/iblogs`, package `indifferentketchup/iblogs`, fork of `aternosorg/mclogs`) is the primary consumer of codex via a Composer `vcs` repository entry pinned to the latest minor tag. Public-API changes in `src/{Detective,Log,Printer,Util}/*.php` and `src/Analysis/*.php` propagate there; when modifying those types, sanity-check the iblogs call sites at `/opt/iblogs/src/{Detective.php,Log.php,Printer/Printer.php,Printer/FormatModification.php,Api/Response/CodexLogResponse.php}` and the stub class at `/opt/iblogs/src/Data/Deobfuscator.php`.

The deployed iblogs instance lives at `bosslogs.indifferentketchup.com` (production renders the same code path as the local dev server on port 4217). iblogs's default branch is `main`, not `master`. iblogs's `composer.json` constraint is currently `^0.3.0`; cutting a v0.4.x will require widening that.

**Cross-repo sync rule.** Changes that affect both repositories must be committed *and pushed* together:

1. Make + commit the codex side first (cut a tag if it's a release).
2. Bump iblogs's `composer.json` codex constraint and adjust the call sites listed above.
3. Push both branches **in the same operation** — never tag codex with breaking changes unless the matching iblogs adjustment is ready to push, and never leave iblogs requiring a codex version that isn't on the remote.

If a change is purely internal to one repo (refactor inside codex with no public-API delta, or an iblogs-only feature like a new Filter), the rule doesn't apply. The mirror of this paragraph lives at `/opt/iblogs/CLAUDE.md` and must stay in step with this one.

## Out-of-library tools (`tools/pz-analyzer/`)

Python utilities alongside the Composer package, not on the PSR-4 autoload surface. Two artefacts coexist by design — the deterministic classifier is the production target; the Qwen tool is the developer's discovery aid for shapes the deterministic side hasn't captured yet.

- **`pz_redact_all.sh`** — one-shot Docker wrapper. Runs `ProjectZomboidRedactor` over `.scratch/pz/Logs/` and writes `.scratch/pz/Logs.redacted/`. Both Python tools below consume the redacted directory.
- **`pz_error_analysis.py`** — *pre-production*, Qwen-backed. Sends residual log shapes to the local Qwen endpoint at `http://100.101.41.16:8401/v1` (sam-desktop, model `qwen3.6-35b-a3b`) for natural-language classification with category / cause / fix output. Requires the `openai` package; in practice run via `/opt/analytics/.venv/bin/python` which has it installed.
- **`pz_parser.py` + `pz_classify.py`** — *production-bound deterministic classifier*. Stdlib only. Mirrors the patterns from `paraxaQQ/pzmm`'s `core/inspector.py` (Lua mod-marker attribution, bidirectional stack collection, file:line extraction, cause-chain unwinding, engine-noise tagging) plus a two-level signature scheme (`pattern_id` + `signature`). Designed to inform a future PHP port to `LuaErrorAnalyser` / `ModAttributionAnalyser` under `src/Analyser/ProjectZomboid/`. 32 stdlib `unittest` tests under `tools/pz-analyzer/tests/`; invocation: `python3 -m unittest discover -s tools/pz-analyzer/tests`.

## Pitfalls

1. **`PatternParser` is incompatible with named regex groups.** PHP's `preg_match` returns named groups *plus* their numeric duplicates in the same array; `PatternParser`'s foreach iterates both and throws on the string-key entries. Convention: `LINE` regexes (used by the parser) use **unnamed** groups with field order documented in the Pattern class's docblock. Named groups are fine inside extractor regexes invoked from analysers, since `PatternAnalyser` hands the whole match array to `Insight::setMatches`.
2. **PHPUnit 12 requires the `#[DataProvider('methodName')]` attribute.** The legacy `@dataProvider` annotation silently passes zero args and fails with `ArgumentCountError`.
3. **`Level::fromString()` defaults to `Level::INFO` for unknown tokens.** Project Zomboid log levels map: `LOG`/`INFO` → INFO; `WARN` → WARNING; `ERROR` → ERROR.
4. **`PatternParser` matches array** must declare a match-type for **every** capture group in the regex (`TIME`, `LEVEL`, or `PREFIX`); otherwise the parser throws on the unmapped index. Use non-capturing groups `(?:...)` for fields you want to skip.
5. **`ProjectZomboidRedactor` pass order is mandatory.** `PLAYER_AFTER_STEAMID_REGEX` anchors on the already-redacted Steam ID placeholder — it will not match raw Steam IDs. Do NOT swap the Steam ID and player-name passes, and do NOT stub out the Steam ID pass while leaving the player-name pass enabled.
6. **Three PZ DebugLog-server line formats exist; `ProjectZomboidServerLog` uses `MultiPatternParser` to handle all three.** B41 emits `[ts] LEVEL: Subsystem  f:N, t:N, st:N,N,N,N>`; B42 (build 42.17 onward) dropped the `t:` microsecond field and tightened spacing to `f:N st:N,N,N,N>` — both are matched by `DebugServerPattern::LINE_B41_B42` (the `(?:,\s+t:\d+)?,?` optional group handles both; preserve it). B4x (build 41.78.x) uses a completely different shape `[ts] LEVEL: Subsystem , <unix_ms>> <tick>> body` — matched by `DebugServerPattern::LINE_B4X`. `ProjectZomboidServerLog::getDefaultParser()` registers B41_B42 first, B4x second (first-match-wins); non-matching lines (tab-indented stack frames) fold under the preceding entry in all formats. `DebugServerPattern::LINE` is a back-compat alias for `LINE_B41_B42`. **B4x exceptions differ structurally:** the exception type is inline in the header (`Exception thrown <Class>`) and stack frames are *separate adjacent entries*, not tab-continuations — `StackTraceClassificationAnalyser` does a multi-entry forward-walk to assemble them. Fixtures: `debug-server-minimal.txt` (B41), `debug-server-42x-minimal.txt` (B42), `debug-server-b4x-minimal.txt` (B4x), `debug-server-b4x-exception-minimal.txt` (B4x multi-entry exception).
7. **Sentinel default patterns in the new abstract Log bases.** `MinecraftLog::$linePattern` and `HytaleLog::$prefixPattern` use sentinel defaults (`'//'` and `''` respectively). If a future subclass forgets to override them, the detector will silently match every line. `MinecraftLog::getDetectors()` throws `\LogicException` to catch this — `HytaleLog` doesn't have an equivalent guard yet (the analogous fix is Phase 2 polish).
8. **`ProjectZomboidModAttributor::MOD_NAME_TO_WORKSHOP_ID` is intentionally undersized.** Only four mappings are seeded in v0.5.0 (`GaelGunStore-Firearms`, `ImmersiveSolarArrays`, `WaterGoesBad`, `WaterPipes`), harvested directly from `Loading: steamapps/.../108600/<id>/mods/<name>/` paths in real logs. Mods absent from the map render with the `mod-attribution` span but no `data-workshop-id` attribute — graceful degradation for the common case but visually identical to known mods minus the click-through. Broadening the map (JSON file, live mining of the same log, or a hardcoded extension) is Phase 2-B work.

## Workflow conventions

- **One commit per concrete log type** when adding game support: pattern class + log subclass + synthetic fixture + test in a single commit, run `composer test`, then move on. `<Game>Detective::__construct()` wiring goes in its own follow-up commit once all log types are present.
- **Out-of-scope cleanup goes in its own commit.** Tempting workflow/lint fixes (e.g. deprecated CI syntax, comment hygiene) noticed mid-feature should not be folded in — separate commit or follow-up PR.
- **Pre-destructive checkpoint pattern.** Before bulk renames/moves: `git commit --allow-empty -m "pre-X checkpoint"` as a revert anchor. Skip the empty slot if it produces no diff at the end of a plan.
- **Release flow.** Semver: a new public API surface bumps the minor version, not the patch (`v0.1.x → v0.2.x`). Cut: rename `[Unreleased]` to `[X.Y.Z] — YYYY-MM-DD` in `CHANGELOG.md`, add a `[X.Y.Z]:` link reference at the bottom, fresh empty `[Unreleased]` above; lightweight `backup/pre-vX.Y.Z` tag (local only) before annotated `git tag -a vX.Y.Z`; push the annotated tag only.
- **Feature branches.** Substantive feature work lands on a `<feature>-bootstrap`-style branch off master with a `backup/pre-<feature>` lightweight tag at the branch start, merged `--no-ff` after user review. The `redactor` and `iblogs-bootstrap` branches set the precedent.
- **Specs and plans live at** `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md` and `docs/superpowers/plans/YYYY-MM-DD-<topic>.md` per the brainstorming and writing-plans skill conventions.

## Privacy / fixture rules

- `Logs.zip` at the repo root contains real production server data (Steam IDs, player names, world coordinates). It is gitignored.
- Extract for reference: `unzip -q Logs.zip -d .scratch/pz/`. Real logs then live under `.scratch/pz/Logs/` (gitignored). Use only as format reference. Do not paste raw Steam IDs, player names, or coordinates into chat output, commit messages, or any committed file.
- All fixtures committed under `test/src/Games/<Game>/fixtures/` must be **synthetic**, hand-crafted from the observed format with placeholder identifiers: `76561198000000001/2/3` for Steam IDs, `Player1`/`Player2`/`AdminUser` for names, generic coords (`1000-1100, 2000-2200, 0`).
