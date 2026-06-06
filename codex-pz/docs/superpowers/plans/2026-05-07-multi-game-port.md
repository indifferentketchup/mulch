# Multi-game port + auto-detect — plan

**Date:** 2026-05-07
**Branch:** `multi-game-port-bootstrap` (off `master`, ik-codex)
**Backup tag:** `backup/pre-multi-game-port` (lightweight, local)
**Companion branch in iblogs:** TBD at Phase-1 land time, name `multi-game-restoration-bootstrap`

## Why

iblogs originally supported Minecraft + Hytale via `aternos/codex-minecraft` and `aternos/codex-hytale` packages. The May-1 swap to `indifferentketchup/codex` (ik-codex commit `94326d5` in iblogs) explicitly stubbed Minecraft/Hytale because ik-codex's per-game subtrees were empty `.gitkeep`s. The user wants those features restored, plus equivalent infrastructure for Project Zomboid (mod-name attribution as the analogue of Mojang/Yarn deobfuscation; channel-prefix coloring as the analogue of `§`-code translation), plus auto-detection from log content so iblogs picks the right game without metadata.

## Cross-repo sync

Per the codex CLAUDE.md cross-repo rule, Phase 1 lands in **two coordinated pushes**:

1. ik-codex: branch → tag `v0.4.0` (minor bump because new public-API surface is added).
2. iblogs: branch that bumps its `composer.json` constraint from `^0.3.0` → `^0.4.0`, rewires `Detective.php`, restores per-game format dispatch in `FormatModification.php`, leaves `Deobfuscator.php` as a no-op shell (Sherlock port deferred to Phase 2).

Push both branches in the same operation. Don't push the ik-codex tag until iblogs's bump is staged.

## Reference material

`/home/samkintop/opt/ik-codex/.scratch/upstream-reference/` (gitignored, just a porting reference):

- `aternos-codex-base/` — the framework iblogs originally extended
- `codex-minecraft/` — Minecraft Log + Detective + Analyser tree (huge — Vanilla, Fabric, Quilt, Forge, NeoForge, Bukkit/Spigot/Paper/Purpur/Folia, Mohist/Magma/Arclight, BungeeCord/Velocity/Geyser/Waterfall, Bedrock, Pocketmine, plus crash reports + launcher client logs). Phase 1 ports only `VanillaServerLog`. Other variants come in Phase 2.
- `codex-hytale/` — small, ports cleanly. Phase 1 ports the whole thing.
- `sherlock/` — Mojang/Yarn obfuscation map fetcher. Phase 2.

## Architecture decisions

1. **Auto-detect** uses the existing `Detective`/`Detector` scoring pipeline. Each game's `Detective` subclass pre-registers its possible log classes with first-line-only `Detector`s. A new `FirstLinesPatternDetector` (inspecting only the first N lines instead of the whole content) keeps detection cheap on multi-MB logs. The base `Detective` already returns the highest-scoring candidate, so multi-game ranking is just `iblogs::Detective::__construct` registering all three game detectives.

2. **Stack-trace enrichment** is abstracted as a `StackTraceEnricherInterface` in `src/Util/`. Each game implements it differently:
   - `MinecraftDeobfuscator implements StackTraceEnricherInterface` — looks up obfuscated names in Mojang/Yarn maps (Phase 2, mirrors `aternos/sherlock`).
   - `ProjectZomboidModAttributor implements StackTraceEnricherInterface` — parses `Lua((MOD:NAME)).func(file:N)` frames, attaches mod metadata + Workshop ID (Phase 2).
   - The interface is added in Phase 1 even though its impls land in Phase 2; this lets the iblogs printer call `enricher->enrich($trace)` from day 1 with a no-op fallback.

3. **Inline format translation** is abstracted as `InlineFormatModification` (extends the existing codex `Modification` printer extension). Each game implements it:
   - `MinecraftInlineFormat extends InlineFormatModification` — translates `§a`–`§r` codes to `<span class="format-...">` (Phase 1; mirrors the existing upstream `Aternos\Codex\Minecraft\Printer\FormatModification`).
   - `ProjectZomboidInlineFormat extends InlineFormatModification` — wraps chat-channel prefixes (`[General]`, `[Whisper]`, etc.) in `.format-channel-*` spans (Phase 2).

## Tasks

Sequential. Each is one commit on `multi-game-port-bootstrap` (per CLAUDE.md "one commit per concrete log type"). No commits push to remote until Phase 1 is fully assembled.

### Task 1 — Foundation abstractions (this session)

Add three building blocks under existing top-level src/ directories:

- `src/Detective/FirstLinesPatternDetector.php` — extends `Detector` (existing base class). Constructor takes `string $pattern`, optional `int $lineCount = 50` and `float $weight = 1.0`. `detect(LogFileInterface)` reads `$logFile->getContent()`, splits on `\n`, takes the first `$lineCount` lines, runs `preg_match($pattern, $first)`, returns `$weight` on match or `0.0`. PSR-4 namespace `IndifferentKetchup\Codex\Detective`.
- `src/Util/StackTraceEnricherInterface.php` — single method `enrich(string $rawTrace): string` returning a render-time-decorated trace (e.g. with workshop links, deobfuscated symbols, etc.). Interface only — no impls in Phase 1.
- `src/Printer/InlineFormatModification.php` — abstract class extending the existing `Modification` printer-extension base. Single abstract method `formatLine(string $rawText): string` returning HTML-safe text with format spans inlined. Subclasses define the per-game translation rules. Placed at the flat `src/Printer/` level to match the existing `Modification.php` / `PatternModification.php` layout, not in a `Modification/` subdirectory.

No changes to existing classes. No iblogs changes. No CHANGELOG entry yet. **Commit message:** `feat: foundation for multi-game support (StackTraceEnricher, InlineFormat, FirstLinesPatternDetector)`.

### Task 2 — Hytale port (this session)

Port `aternos/codex-hytale` into ik-codex's namespace and layout. Reference is at `.scratch/upstream-reference/codex-hytale/`.

- `src/Log/Hytale/HytaleLog.php` — abstract base; mirror upstream `HytaleLog`. PSR-4 namespace `IndifferentKetchup\Codex\Log\Hytale`.
- `src/Log/Hytale/HytaleServerLog.php` — concrete (server). Mirror upstream `HytaleServerLog`. Carry over its parser pattern + default analyser.
- `src/Log/Hytale/HytaleClientLog.php` — concrete (client). Mirror upstream `HytaleClientLog`.
- `src/Pattern/Hytale/HytaleServerPattern.php` and `HytaleClientPattern.php` — extract regex constants from upstream `getDefaultParser()` calls into Pattern classes per the ik-codex convention (PZ side does this; codex-hytale upstream embeds the regex inline).
- `src/Detective/Hytale/HytaleDetective.php` — pre-registers `HytaleServerLog` + `HytaleClientLog`. Each log class's `getDetectors()` returns one or more `FirstLinesPatternDetector`s matching the upstream Hytale log header signature.
- `test/tests/Games/Hytale/HytaleDetectiveTest.php` — synthetic-fixture dispatch tests, mirroring the PZ test pattern.
- `test/src/Games/Hytale/fixtures/server-minimal.txt`, `client-minimal.txt` — synthetic fixtures with placeholder identifiers.

Skip the upstream `Analyser/` and `Analysis/` ports for Phase 1 — `HytaleServerLog::getDefaultAnalyser()` returns an empty `PatternAnalyser`, just like the PZ stubs do for un-analysed log types. **Commit message:** `feat(hytale): port HytaleLog/HytaleServerLog/HytaleClientLog + Detective`.

### Task 3 — Minecraft port (Vanilla server only, this session)

Subset port. Phase 2 will add the other 30+ variants.

- `src/Log/Minecraft/MinecraftLog.php` — abstract base.
- `src/Log/Minecraft/Vanilla/VanillaServerLog.php` — concrete. Mirror upstream `Aternos\Codex\Minecraft\Log\Minecraft\Vanilla\VanillaServerLog`.
- `src/Pattern/Minecraft/VanillaServerPattern.php` — extracted regex.
- `src/Detective/Minecraft/MinecraftDetective.php` — pre-registers `VanillaServerLog`. Add a TODO comment listing the deferred variants.
- `test/tests/Games/Minecraft/MinecraftDetectiveTest.php` + `test/src/Games/Minecraft/fixtures/vanilla-server-minimal.txt`.

Skip Analyser/Analysis ports. **Commit message:** `feat(minecraft): port MinecraftLog/VanillaServerLog + Detective (Vanilla server only)`.

### Task 4 — Minecraft section-sign format translation (this session)

Port the Minecraft `§`-code translator as `MinecraftInlineFormat extends InlineFormatModification` (the abstract base from Task 1).

- `src/Printer/Minecraft/MinecraftInlineFormat.php` — `formatLine` translates `§0`–`§9`, `§a`–`§f` (color codes), `§k` `§l` `§m` `§n` `§o` (style), `§r` (reset), with the same CSS class names the iblogs `.format-*` rules already cover. Reference: upstream `Aternos\Codex\Minecraft\Printer\FormatModification` at `.scratch/upstream-reference/codex-minecraft/src/Printer/FormatModification.php`.
- `test/tests/Games/Minecraft/MinecraftInlineFormatTest.php` — unit tests covering each color + each style + the reset code + a complex mixed case.

iblogs CSS already has the `.format-*` classes (kept around the May-1 swap), so no iblogs change in this task. **Commit message:** `feat(minecraft): inline §-code format translation`.

### Task 5 — Bump CHANGELOG, prepare ik-codex v0.4.0 (this session)

- Update `CHANGELOG.md` with the v0.4.0 section listing all four Tasks 1-4 changes plus the new public-API surface. Move existing `[Unreleased]` content under `[0.4.0] — 2026-05-07`. Add fresh empty `[Unreleased]` at top.
- Add a CLAUDE.md entry under "Game subtrees" enumerating the new Hytale + Minecraft scaffolds.

**Commit message:** `docs: cut v0.4.0 in CHANGELOG and update CLAUDE.md`.

After review approval: lightweight `backup/pre-v0.4.0` tag, then annotated `git tag -a v0.4.0`. Don't push the tag yet — iblogs side has to be ready first.

### Task 6 — iblogs rewire (this session if context allows; otherwise next session)

Branch `multi-game-restoration-bootstrap` off `main` in `/opt/iblogs`.

- `composer.json`: bump `indifferentketchup/codex` constraint from `^0.3.0` to `^0.4.0`.
- `src/Detective.php`: register all three detectives:
  ```php
  $this->addDetective(new MinecraftDetective())
       ->addDetective(new HytaleDetective())
       ->addDetective(new ProjectZomboidDetective());
  ```
- `src/Printer/FormatModification.php`: dispatch to per-game InlineFormat when the printed log's class matches Minecraft/Hytale/PZ. For Minecraft, instantiate `MinecraftInlineFormat`. For others, pass-through.
- `src/Data/Deobfuscator.php`: leave as no-op shell. Sherlock port deferred.

**Commit message in iblogs:** `feat: restore Minecraft + Hytale support via ik-codex 0.4.0`.

After approval and smoke-test: push the ik-codex `v0.4.0` tag and the iblogs branch in the same operation per cross-repo sync rule.

## Phase 2 (DEFERRED — not executed in this session)

Documented here for completeness. Each is a separate plan/branch later.

- **All other Minecraft variants** (Fabric, Quilt, Forge, NeoForge, Bukkit/Spigot/Paper/Purpur/Folia, Mohist/Magma/Arclight, BungeeCord/Velocity/Geyser/Waterfall, Bedrock, Pocketmine, plus crash reports + launcher client logs). Each gets its own `Log` + `Pattern` + Detective registration + fixture + test commit.
- **Sherlock port** as `MinecraftDeobfuscator implements StackTraceEnricherInterface`. Mojang/Yarn map fetch, mapped-data structures, ObfuscatedString resolution.
- **PZ ModAttributor** as `ProjectZomboidModAttributor implements StackTraceEnricherInterface`. Parse `Lua((MOD:NAME)).func(file:N)` frames; resolve mod names against a small static lookup table seeded with the Workshop IDs from the recent log analysis (`.scratch/pz/analysis.json` etc.).
- **PZ chat-channel formatter** as `ProjectZomboidInlineFormat extends InlineFormatModification`.
- **Minecraft Analyser/Insight ports** for actionable error classification (mod conflicts, OOM, crash report parsing).
- **iblogs `Deobfuscator.php` real implementation** wiring through to the per-game StackTraceEnricher.

## Out of scope (forever, unless re-prioritised)

- SevenDaysToDie. The codex stub stays empty.
- The upstream `Analyser/Report/` crash-report tree at depth — Phase 1 brings only Vanilla server log; crash reports are Phase 2.
- Translator-based i18n. Upstream's `Translator/Translator.php` is a language-string lookup, not the format formatter — orthogonal feature, not part of this plan.
