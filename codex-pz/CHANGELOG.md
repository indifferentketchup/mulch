# Changelog

All notable changes to `indifferentketchup/codex-pz` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.6.0] — 2026-06-06

Deterministic Project Zomboid error-extraction pipeline: parses all three `DebugLog-server` line formats (adds **B4x**, build 41.78.x — previously a silent total parse failure across 161 production files), classifies every error into typed Insights carrying severity, mod attribution, and cause chains, and surfaces them as additive JSON fields for downstream rendering. New public-API surface (capability interfaces, the `Severity`/`ModAttribution` value types, `MultiPatternParser`, `CompositeAnalyser`, `StackTraceClassificationAnalyser`, 14 new Insight classes). The existing Insight implementations stay wire-compatible (the `entry` key is retained; new fields are opt-in via capability interfaces).

### Renamed (breaking — coordinated with iblogs)

- **Package `indifferentketchup/codex` → `indifferentketchup/codex-pz`, root namespace `IndifferentKetchup\Codex` → `IndifferentKetchup\CodexPz`, Gitea slug `ik-codex` → `ik-codex-pz`.** This is a package-identity change: every `use IndifferentKetchup\Codex\…` becomes `…\CodexPz\…`. The sole downstream consumer (iblogs) is updated in the same operation (constraint, VCS URL, and all imports). Requires the Gitea repository to be renamed to `ik-codex-pz` and the iblogs `composer.lock` regenerated against the new name.

### Added

- **Capability interfaces + value types** (`src/Analysis/`): `Severity` enum (five tiers — `Noise`/`Low`/`Medium`/`High`/`Critical`, weighted for `severity × counter` sorting), `ModAttribution` value object + `AttributionConfidence` enum, and four opt-in capability interfaces — `SeverityAwareInsightInterface`, `ModAttributedInsightInterface`, `EngineNoiseInsightInterface` (marker), `CauseChainInsightInterface`. `Insight::jsonSerialize()` additively surfaces `severity`/`mod`/`engineNoise`/`causeChain` only when the interface is implemented, plus a deterministic `fingerprint` (default `sha256:<16hex of class>`; stack-trace insights override it with an exception+frames+mod hash). `InsightInterface::getEntry()` retyped `?EntryInterface` to match the nullable property.
- **B4x line parsing** — `DebugServerPattern::LINE_B41_B42` (the former `LINE`, kept as an alias) + new `LINE_B4X` for the `, <unix_ms>> <tick>>` shape; `src/Parser/MultiPatternParser.php` tries registered formats first-match-wins while preserving continuation-append; `ProjectZomboidServerLog` wires both via the new `makeMultiPatternParser()` factory.
- **`StackTraceClassificationAnalyser`** (`src/Analyser/ProjectZomboid/`) — owns all `Exception thrown`-shaped entries. Assembles stacks for both B41/B42 (tab-continuation) and **B4x (multi-entry adjacent-frame forward-walk)**; ports the deterministic mod-attribution (direct `Lua((MOD:X))` + lookback), cause-chain unwinding, file:line extraction, engine-noise tagging, and fingerprinting from `tools/pz-analyzer/pz_parser.py`. Emits `LuaModRuntimeProblem`, `JavaExceptionProblem`, `EngineNoiseExceptionInformation`. Control bytes (ANSI/etc.) are stripped at the analyser boundary on every field carrying raw log content.
- **11 single-line warning-family Insights** (`src/Analysis/ProjectZomboid/`) + their Pattern classes (`LuaWarningPattern`, `AnimationWarningPattern`, `AssetWarningPattern`, `ConfigDriftPattern`): require-failed, function-missing, recursive-require, bone-index, anim-clip, sprite-config, missing-icon, missing-thumpsound, buffer-overflow, unknown-sandbox-option, unknown-item-param.
- **`CompositeAnalyser`** (`src/Analyser/`) — composes the per-entry `PatternAnalyser` and the cross-entry `StackTraceClassificationAnalyser`, propagating `setLog()` to children. The **one-producer seam** is enforced architecturally via `PatternAnalyser::shouldAnalyseEntry()` (new protected hook, default `true`) + `WarningPatternAnalyser`, which skips `Exception thrown` entries so a single underlying error yields exactly one problem row (no double-count).
- **PII hardening (security)** — `ProjectZomboidRedactor::STEAM_ID_REGEX` now covers all three SteamID64 universes (`7656119[789]`, was `76561198` only — ~46% of real IDs were leaking); the player-name pass fires on raw IDs directly rather than chaining off the redacted placeholder. New `test/tests/Security/PIIRoundTripTest.php` asserts the full `parse → analyse → jsonSerialize → json_encode` pipeline emits zero Steam IDs / player names for a multi-universe fixture.

### Changed

- `ProjectZomboidServerLog::getDefaultAnalyser()` now returns a `CompositeAnalyser`; `ServerExceptionProblem` is no longer registered by default (the StackTrace analyser supersedes it — the class is retained, unchanged, for isolated use).

### Test counts

- PHP suite: **411 tests, 908 assertions** (up from 323 / 694 at v0.5.0). 100k-line synthetic parse+analyse bench ≈ 0.8s (informational).

## [0.5.0] — 2026-05-07

Adds `ProjectZomboidModAttributor` as the first concrete `StackTraceEnricherInterface` implementation, decorating `Lua((MOD:NAME)).func(file:N)` stack frames with HTML spans carrying Steam Workshop IDs where known. New public-API class makes this a minor bump rather than a patch. The static workshop-ID map is intentionally small for this release (four mappings harvested from production logs); broadening the map is Phase 2-B work.

### Added

- **`ProjectZomboidModAttributor`** (`src/Util/ProjectZomboid/ProjectZomboidModAttributor.php`) — concrete `Modification` + `StackTraceEnricherInterface` implementation. Parses `MOD:<name>)` tokens via UTF-8-aware regex, escapes the captured name through `htmlspecialchars(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')`, and emits `<span class="mod-attribution"[ data-workshop-id="ID"]>NAME</span>` shape. Both `modify()` (from `Modification`) and `enrich()` (from `StackTraceEnricherInterface`) route through the same private `decorate()` method. Idempotent — the regex character class excludes `<` so already-decorated text won't double-wrap. Defensive `?? $text` null-coalesce on `preg_replace_callback` mirrors the adjacent `ProjectZomboidRedactor` pattern. `MOD_NAME_TO_WORKSHOP_ID` typed class constant seeded with four mappings harvested from `Loading: steamapps/.../108600/<id>/mods/<name>/` paths in production logs (`GaelGunStore-Firearms`, `ImmersiveSolarArrays`, `WaterGoesBad`, `WaterPipes`). Unknown mods render with the span but no `data-workshop-id` attribute. 10 unit tests covering known/unknown lookup, multi-frame inputs, plain-text passthrough, idempotence, hostile-`<script>` HTML escaping, internal `&`/`"`/`'` escaping, apostrophes in mod names, `enrich()` → `decorate()` delegation, and empty-string passthrough.
- `docs/superpowers/plans/2026-05-07-pz-enrichment.md` — design + as-built plan for Phase 2-A, including the deferred Phase 2-B+ items (PZ chat formatter, Minecraft variant ports, Sherlock port, Minecraft Analyser/Insight ports, broadening the workshop-ID map).

### Test counts

- PHP suite: **323 tests, 694 assertions** (up from 313 / 682 at v0.4.0).

### Notes for downstream consumers (iblogs)

iblogs's `composer.json` constraint must be widened from `^0.4.0` to `^0.5.0` to consume this release. The iblogs Printer needs to add `ProjectZomboidModAttributor` as a Modification when the printed log is a `ProjectZomboidLog`-derived instance. iblogs CSS needs a `.mod-attribution` style block plus a JS click handler for `[data-workshop-id]` opening the Steam Workshop URL `https://steamcommunity.com/sharedfiles/filedetails/?id=<id>` in a new tab.

## [0.4.0] — 2026-05-07

Restores Minecraft + Hytale game support that was dropped at the May-1 swap from `aternos/codex-minecraft` / `aternos/codex-hytale` to `indifferentketchup/codex-pz`, lays the public-API foundation for cross-game stack-trace enrichment and inline format translation, ports `aternos/codex-minecraft`'s ANSI-code translator into the new `InlineFormatModification` shape, and adds a first-lines auto-detect helper so the `Detective` pipeline can rank multi-MB logs cheaply on banner / header signatures. New public-API surface (the `StackTraceEnricherInterface`, the `InlineFormatModification` abstract base, `FirstLinesPatternDetector`, the Hytale + Minecraft Vanilla log subclasses, two new game detectives) makes this a minor bump rather than a patch.

### Added

- **`StackTraceEnricherInterface`** (`src/Util/StackTraceEnricherInterface.php`) — single-method interface for game-specific stack-trace decoration. Implementations are deferred to Phase 2 (`MinecraftDeobfuscator` mirroring `aternos/sherlock`'s Mojang/Yarn map lookup; `ProjectZomboidModAttributor` resolving `Lua((MOD:NAME)).func(file:N)` frames against a Workshop-ID lookup). Shipped now so the iblogs printer can call `enrich($trace)` from day 1 with a no-op fallback.
- **`InlineFormatModification`** (`src/Printer/InlineFormatModification.php`) — abstract typed-marker subclass of `Modification` for game-specific inline format translation. Concrete subclasses implement the inherited `modify(string): string`. Placed at the flat `src/Printer/` level alongside the existing `Modification.php` / `PatternModification.php`.
- **`FirstLinesPatternDetector`** (`src/Detective/FirstLinesPatternDetector.php`) — runs a regex against only the first N lines of a log; returns the configured weight on match, false otherwise. `setLineCount(int)` and `setWeight(float)` fluent setters; weight is validated to `[0.0, 1.0]`. Useful for cheap auto-detect on multi-MB logs based on banner / header signatures.
- **Hytale game support.** `HytaleLog` abstract base (`src/Log/Hytale/HytaleLog.php`); concrete `HytaleServerLog` and `HytaleClientLog`; pattern classes `HytaleServerPattern` and `HytaleClientPattern` (`src/Pattern/Hytale/`); `HytaleDetective` (`src/Detective/Hytale/HytaleDetective.php`) registering both log classes. Lifted from upstream `aternos/codex-hytale` with detection upgraded to `FirstLinesPatternDetector`. Skipped: `HytaleAnalyser`/`Analysis` ports (Phase 2), `getVersion`/`jsonSerialize` (Phase 2). Synthetic fixtures plus dispatch / per-log tests.
- **Minecraft game support (Vanilla server only).** `MinecraftLog` abstract base (`src/Log/Minecraft/MinecraftLog.php`) with sentinel-default `$linePattern` and a `LogicException` guard against un-overridden subclasses; concrete `VanillaServerLog` (`src/Log/Minecraft/Vanilla/VanillaServerLog.php`); pattern class `VanillaServerPattern` with `LINE` (byte-for-byte from upstream `VanillaLog::$pattern`) and `DETECTION_BANNER` regex; `MinecraftDetective` (`src/Detective/Minecraft/MinecraftDetective.php`) registering Vanilla server only with a TODO comment listing the deferred Phase 2 variants. Skipped: 30+ other Minecraft variants (Fabric, Quilt, Forge, NeoForge, Bukkit/Spigot/Paper/Purpur/Folia, Mohist/Magma/Arclight, BungeeCord/Velocity/Geyser/Waterfall, Bedrock, Pocketmine, plus crash reports and launcher client logs) — all Phase 2.
- **`MinecraftInlineFormat`** (`src/Printer/Minecraft/MinecraftInlineFormat.php`) — concrete `InlineFormatModification` that translates ANSI escape codes (e.g. `\e[0;31;22m` for red) emitted by Minecraft server's terminal logger into HTML spans with `format-<name>` classes. Byte-for-byte mirror of upstream `aternos/codex-minecraft`'s `FormatModification`, with the 22-entry `FORMAT_CODES` map promoted to a typed class constant and the `getClasses()` extension hook hardcoded to `format-<name>` since iblogs's CSS already uses that naming.
- `docs/superpowers/plans/2026-05-07-multi-game-port.md` — design and as-built plan for the multi-game port, including Phase 2 deferrals.

### Changed

- **`CLAUDE.md`** "Game subtrees" section: Hytale and Minecraft are no longer "stubs only — empty `.gitkeep`s plus a TODO `<Game>Detective`". Per-game lines updated to reflect their new state. SevenDaysToDie continues to be a stub.
- **Cross-repo sync rule** mirror added to ik-codex-pz's CLAUDE.md (matches the iblogs CLAUDE.md paragraph; was committed earlier in this branch as part of branch setup).
- **`.gitignore`** broadens `Logs.zip` to `Logs*.zip` so additional production log captures (`Logs2.zip` etc.) are also excluded.

### Test counts

- PHP suite: **313 tests, 682 assertions** (up from 287 / 654 at v0.3.0).

### Notes for downstream consumers (iblogs)

iblogs's `composer.json` constraint must be widened from `^0.3.0` to `^0.4.0` to consume this release. Rewiring `iblogs/src/Detective.php` to register `MinecraftDetective` + `HytaleDetective` + `ProjectZomboidDetective` is a separate task on the iblogs side. The iblogs printer can opt into `MinecraftInlineFormat` by instantiating it when the log class is `MinecraftLog`-derived; the equivalent dispatch for Hytale and PZ is Phase 2.

## [0.3.0] — 2026-05-04

Adds IP-address redaction to the PZ redactor, a new `ErrorContextAnalyser` for surrounding-context surfacing, the `tools/pz-analyzer/` Python toolset (pre-production Qwen-driven research analyser and production-bound deterministic classifier), and a parser fix for the PZ B42 log shape that was silently breaking level/prefix attribution since The Indie Stone dropped the per-line `t:` field. New public API surface across the redactor and the analyser-side classes makes this a minor bump rather than a patch.

### Added

- **IP redaction in `ProjectZomboidRedactor`** (`src/Util/ProjectZomboid/ProjectZomboidRedactor.php`) — fourth pass that scrubs IPv4 (strict 0-255 octets, optional `:port` suffix) and IPv6 (full, abbreviated, bracketed-with-port, IPv4-mapped) addresses, replacing them with the literal `[REDACTED_IP]`. New public API: `IP_REPLACEMENT`, `IPV4_REGEX`, `IPV6_REGEX` constants and a `redactIpAddresses(bool)` toggle (defaults on, mirroring the existing three category toggles). Pattern-disjoint from the Steam-ID → name → coordinates chain; runs first by convention. Strict regexes plus `filter_var()` validation prevent false positives on PZ timestamps and PHP / Java scope ops. 20 new unit tests across two files (`ProjectZomboidRedactorIpv4Test.php`, `ProjectZomboidRedactorIpv6Test.php`).
- **`ErrorContextAnalyser`** (`src/Analyser/ProjectZomboid/ErrorContextAnalyser.php`) — generic-purpose analyser that walks `Entry[]` once and emits one `ErrorContextProblem` per ERROR / WARNING entry with up to `CONTEXT_BEFORE` (20) entries of leading context and `CONTEXT_AFTER` (20) entries of trailing context. Overlapping windows clip to `lastEmittedIndex + 1` so no Entry appears in two context arrays; emission caps at `HIT_CAP` (500) with a single `ErrorContextTruncatedInformation` appended when reached. Standalone — not auto-registered to any existing Log subclass's `getDefaultAnalyser()`; consumers wire it in explicitly. Companion classes `ErrorContextProblem` and `ErrorContextTruncatedInformation` under `src/Analysis/ProjectZomboid/`. 3 unit tests, 134 assertions.
- **`tools/pz-analyzer/`** — Python toolset adjacent to the library (not part of the Composer package's autoload surface). `pz_redact_all.sh` is a one-shot Docker wrapper that runs the PHP redactor over `.scratch/pz/Logs/` and produces a gitignored `.scratch/pz/Logs.redacted/` directory. `pz_error_analysis.py` is a developer-facing Qwen-backed pre-production analyser that calls a local OpenAI-compatible endpoint to classify residual log shapes the deterministic side hasn't yet captured. `pz_parser.py` + `pz_classify.py` are the production-bound deterministic-only counterpart: pure parser module with mod attribution, file:line extraction, cause-chain unwinding, engine-noise tagging, and a two-level signature scheme (`pattern_id` + `signature`), plus a stdlib-only orchestrator that walks the redacted directory and emits a JSON report. 32 Python unit tests across three files, 16 synthetic fixtures.
- `docs/superpowers/specs/2026-05-04-pz-deterministic-classifier-design.md` — design contract for `pz_parser.py` / `pz_classify.py`. The PHP-side `ErrorContextAnalyser` ships without a separate spec; its design fell out of a brainstorming session inline with the pzmm-pattern-port discussion.
- New synthetic fixture `test/src/Games/ProjectZomboid/fixtures/debug-server-42x-minimal.txt` mirroring the existing B41 fixture in PZ B42 line shape.

### Changed

- **`DebugServerPattern::LINE` regex relaxed** to handle PZ build 42.x. The Indie Stone dropped the per-line `t:` (microsecond) field and tightened the spacing between `f:N`, `t:N`, and `st:N,N,N,N>` markers somewhere on the way to build 42.17. The previous regex required the full `f:\d+,\s+t:\d+,\s+st:` triplet and silently failed on every B42 line. Now `(?:,\s+t:\d+)?` makes the `t:N,` field optional and `,?` makes the inter-field comma optional. Backwards-compatible — every B41 line continues to parse identically. `ProjectZomboidServerLogTest` now runs each parser-shape assertion via `#[DataProvider]` against both fixtures.
- **Pass order in `ProjectZomboidRedactor::redact()`**: the new IP pass runs first, so the chain is now `IP → Steam ID → player name → coordinates`. The mandatory Steam ID → name → coordinates ordering is preserved; placement of the IP pass is by convention since its regexes are pattern-disjoint from the rest.
- **`CLAUDE.md`** documents `iblogs` as the primary downstream consumer with a per-component checklist for cross-repo public API impact; the release-flow cadence; the feature-branch workflow set by the `redactor` and `iblogs-bootstrap` precedents; and the `docs/superpowers/specs|plans/` path convention.
- **`.gitignore`** excludes `__pycache__/` (Python bytecode caches generated under `tools/pz-analyzer/`) and `*.bak` / `*.bak-*` (editor / manual backup files).

### Fixed

- PZ build 42.x server logs now parse with proper level / prefix attribution. Previously, every B42 line failed `DebugServerPattern::LINE` and the resulting ServerLog entries fell through as level `INFO` with no prefix. This silently disabled `ServerExceptionProblem` and `ModMissingProblem` (their regexes anchor on `[timestamp]...` at entry start, which a level-less orphan entry doesn't emit). The anchorless `EngineVersionInformation` continued to fire against the joined entry text, producing the user-visible symptom "one Information badge, empty Problems panel" on B42 logs. The fix restores per-line parsing, re-enables both Problem classes, and makes the error-count badge populate correctly.

### Test counts

- PHP suite: **287 tests, 654 assertions** (up from 260 / 492 at v0.2.0).
- Python suite under `tools/pz-analyzer/`: **32 tests** (stdlib `unittest`, sub-10 ms).

## [0.2.0] — 2026-05-01

Render-time PII redaction utility added on the same calendar day as v0.1.0. Cut as a minor version bump rather than a patch because it adds a new public API surface (`RedactorInterface` plus the per-game implementation), which under semver is a minor change, not a patch. Consumers (notably iblogs) pin to `^0.2.0` to opt into the redactor-aware version.

### Added

- `RedactorInterface` (`src/Util/RedactorInterface.php`) and `ProjectZomboidRedactor` (`src/Util/ProjectZomboid/ProjectZomboidRedactor.php`) — render-time PII filter that scrubs Steam IDs, player names, and world coordinates from Project Zomboid log content. Three independent toggles default to on. Designed as a string-in/string-out utility so consumers can apply it at any rendering or export step. Documented v1 limitations: in PvP combat lines, only the attacker's name and coords are redacted; victim's name and coords (after `hit`) are deferred to v2. In admin lines, `teleported X to <coords>` coordinates are not redacted in v1.
- 65 new test methods across six files under `test/tests/Util/Redactor/` — per-category unit tests, combined / toggle / idempotence matrix, and integration coverage that drives all 11 existing PZ fixtures through the redactor end-to-end. Suite total: 260 tests, 492 assertions.
- `docs/superpowers/specs/2026-04-30-redactor-design.md` flipped from "deferred" to "implemented" status. Plan committed at `docs/superpowers/plans/2026-05-01-redactor.md`.

### Changed

- New top-level `src/Util/` directory introduced. The Redactor is its first occupant; future utilities (e.g. tokenising redactor variants) land here.

## [0.1.0] — 2026-05-01

First public release. Codex is a generic PHP log parsing and analysis framework with full Project Zomboid server-log support across eight analysers. The Composer package name is `indifferentketchup/codex-pz` (the repository directory and Gitea slug are `ik-codex-pz`; the package name is not).

### Added

- **Framework foundation** — generic `Log` / `Entry` / `Line` / `Parser` / `Analyser` / `Detective` / `Insight` pipeline forked from upstream `aternos/codex` and renamed end-to-end to `IndifferentKetchup\CodexPz\*` in `66a2fcc`. Zero `Aternos\Codex\*` namespace references remain in `src/` or `test/`.
- **`FilenameDetector`** at `IndifferentKetchup\CodexPz\Detective\FilenameDetector` — path-based detector that uses the new `LogFileInterface::getPath()` accessor to dispatch on a filename hint. Falls back to `false` for path-less log files (`StringLogFile`, `StreamLogFile`).
- **Project Zomboid log subclasses (11)** under `IndifferentKetchup\CodexPz\Log\ProjectZomboid\*` covering every PZ server-log file type: a multi-line `ProjectZomboidServerLog` for `DebugLog-server.txt`, an abstract `ProjectZomboidEventLog` base for the ten single-line logs, and concrete subclasses for `admin.txt`, `BurdJournals.txt`, `chat.txt`, `ClientActionLog.txt`, `cmd.txt`, `item.txt`, `map.txt`, `PerkLog.txt`, `pvp.txt`, `user.txt`.
- **Pattern classes (11)** under `IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\*` holding regex string constants. Each `<Type>Pattern` carries a `LINE` regex used by `PatternParser`, plus named-group extractor regexes (`FIELDS`, `COMBAT`, `MOD_LOAD`, etc.) used by analysers.
- **`ProjectZomboidDetective`** at `IndifferentKetchup\CodexPz\Detective\ProjectZomboid\ProjectZomboidDetective` — pre-registers all 11 log subclasses in its constructor with paired filename-hint plus content-signature detectors.
- **Phase B.1 ServerLog analysers (3)**: `EngineVersionAnalyser` (extracts engine version, build hash, and build date from the server banner), `ModLoadAnalyser` (mod load order plus missing-mod problems with attached `ModMissingSolution`), `ServerExceptionAnalyser` (Java exception type and stack-trace body, coalesced by exception type).
- **Phase B.2 PvP and Admin analysers (2)**: `PvpDamageAnalyser` (filters zombie hits and zero-damage rows at the regex itself), `AdminAuditAnalyser` (verb-pattern dispatch across six admin actions: added item, added xp, granted access, changed option, reloaded options, teleported).
- **Phase B.3 deferred analysers (3)** — first custom `Analyser` subclasses in the tree, addressing logic that vanilla `PatternAnalyser` cannot express: `ConnectionFailureAnalyser` (event pairing across the file), `ItemDuplicationAnalyser` (sliding-window heuristic with `THRESHOLD_COUNT=5`, `THRESHOLD_WINDOW_SECONDS=10`), `SkillProgressionAnomalyAnalyser` (consecutive-snapshot delta with `THRESHOLD_DELTA=3`). All three threshold constants ship with rationale docblocks and are tunable via subclass override.
- **Synthetic test fixtures** under `test/src/Games/ProjectZomboid/fixtures/`, hand-crafted from observed PZ log shapes with placeholder identifiers per the project's privacy rules: Steam IDs `76561198000000001`–`76561198000000004`, names `Player1` / `Player2` / `AdminUser` / `PlayerSuspect`, generic coords. No real-log content reaches the index.
- **End-to-end tests** validating each Log subclass's parser, each analyser's insight emission, and the Detective's dispatch behaviour against the synthetic fixtures. Final count: **195 tests, 412 assertions**.
- **Project documentation**: `CLAUDE.md` with framework architecture, pitfalls, and workflow conventions; `README.md` with worked Project Zomboid example and per-game support table; design specs and as-built plans for Phase B.1 / B.2 / B.3 plus a deferred-status spec for the codex `Redactor` utility, all under `docs/superpowers/`.

### Changed

- **Layout: components-outer with game suffix.** Every game's code lives at `IndifferentKetchup\CodexPz\<Component>\<Game>\*` for the existing components (`Analyser`, `Analysis`, `Detective`, `Log`, `Parser`, `Pattern`). This is option 1 from the Phase A Step 2 layout decision; option 3 (a flat `IndifferentKetchup\CodexPz\Games\<Game>\*` tree) was originally proposed and was **not** selected.
- **`LICENSE`** retains the original `Copyright (c) 2019-2026 Aternos GmbH` line per MIT requirements; the LICENSE file is byte-for-byte unchanged from the upstream import.
- **`composer.json`** rewritten in `aae016d`: package name `indifferentketchup/codex-pz`, MIT license, generic-framework description, single author entry, PSR-4 autoload roots set to `IndifferentKetchup\CodexPz\` and the test-fixture / test-suite namespaces, PHP `>=8.4` require constraint, PHPUnit `^12` dev dependency.
- **`tests.yaml`** uses the modern `$GITHUB_OUTPUT` workflow command instead of the deprecated `::set-output` (commit `60f12bc`). CI matrix runs PHP 8.4 and 8.5.
- **`.gitignore`** excludes `Logs.zip` (real production log fixtures) and `.scratch/` (extracted reference logs), plus `.claude/` and `.claude.local.md` for personal Claude Code artefacts.

### Deferred

- **Other game implementations** — `Minecraft`, `Hytale`, and `SevenDaysToDie` are detective-stub-only. Each has a TODO `<Game>Detective` extending base `Detective`; their per-component subdirectories under `Analyser`, `Log`, `Parser`, and `Pattern` contain only `.gitkeep` placeholders. Real implementations land if and when fixtures and demand exist.
- **Packagist publication** — v0.1.0 is consumable via Composer's `vcs` repository entry pointing at the Gitea remote. Pushing to Packagist is a separate decision and is not in scope for this release.

[0.6.0]: https://git.indifferentketchup.com/indifferentketchup/ik-codex-pz/releases/tag/v0.6.0
[0.5.0]: https://git.indifferentketchup.com/indifferentketchup/ik-codex-pz/releases/tag/v0.5.0
[0.4.0]: https://git.indifferentketchup.com/indifferentketchup/ik-codex-pz/releases/tag/v0.4.0
[0.3.0]: https://git.indifferentketchup.com/indifferentketchup/ik-codex-pz/releases/tag/v0.3.0
[0.2.0]: https://git.indifferentketchup.com/indifferentketchup/ik-codex-pz/releases/tag/v0.2.0
[0.1.0]: https://git.indifferentketchup.com/indifferentketchup/ik-codex-pz/releases/tag/v0.1.0
