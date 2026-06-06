# PZ enrichment (Phase 2-A) — plan

**Date:** 2026-05-07
**Branch:** `pz-enrichment-bootstrap` (off `multi-game-port-bootstrap`, ik-codex)
**Companion branch in iblogs:** TBD at iblogs-rewire time, name `pz-enrichment-iblogs-bootstrap`
**Backup tag:** `backup/pre-pz-enrichment` (lightweight, local)

## Why

Phase 1 shipped abstract `StackTraceEnricherInterface` with no concrete implementations — Phase 2 fills that in for Project Zomboid. Real PZ debug logs contain Lua stack traces with mod attribution baked into every frame:

```
Lua((MOD:Spongie's Clothing)).customGetVal(SpongieCopy_BodyLocationsTweaker.lua:12)
Lua((MOD:Spongie's Clothing)).RemoveSweaterHide.lua(RemoveSweaterHide.lua:2)
```

The mod name is right there. We can resolve it to a Workshop ID (we have ~4 known mappings already, harvested from `Loading: steamapps/workshop/content/108600/<id>/mods/<name>/` paths in the same logs) and turn the raw frame text into actionable HTML pointing at the Steam Workshop entry. This addresses the "I don't need every mod loading line, but the errors should be actionable" UX gap from earlier in the project.

Out of scope for this slice: PZ chat-channel coloring, the upstream Minecraft variant ports (Fabric/Forge/Bukkit/etc.), Sherlock-port. Those are separate Phase 2 slices.

## Cross-repo impact

Adds new public API: `ProjectZomboidModAttributor` under `src/Util/ProjectZomboid/`. New class — no breaking changes — so this is a minor bump on the ik-codex side (will become 0.5.0 when both Phase 1 and Phase 2-A are bundled into one tag, OR 0.4.1 if shipped as a patch).

iblogs side wires the new Modification into `Printer::setLog` alongside the Phase 1 `MinecraftInlineFormat` dispatch.

## Architecture

**`ProjectZomboidModAttributor`** is a single class doing two jobs through complementary interfaces:

- `extends Modification` — slot it into the iblogs Printer's modification pipeline (string-in / string-out per entry body line).
- `implements StackTraceEnricherInterface` — keep the typed marker shape for any future stack-trace-only consumer.

Both `modify($text)` and `enrich($trace)` route to the same private `decorate()` method. Idempotence: re-running the regex over already-decorated text produces no double-wrapping (the regex anchors on `MOD:` followed by a closing `)` that's not yet wrapped in an `</span>`).

**Workshop ID resolution** is a static `MOD_NAME_TO_WORKSHOP_ID` array. Initially seeded with the four mappings we directly mined from `.scratch/pz2/Logs2/`'s `Loading: steamapps/.../108600/<id>/mods/<name>/` paths:

| Mod name (as it appears in `MOD:<name>`) | Workshop ID |
|---|---|
| GaelGunStore-Firearms | 3616176188 |
| ImmersiveSolarArrays | 2857548524 |
| WaterGoesBad | 2849467715 |
| WaterPipes | 3118159023 |

Mods not in the table render with the mod name in the same span but no `data-workshop-id` attribute (graceful degradation; iblogs CSS / JS treats them as un-linkable styling-only). The map is tightly seeded; future Phase 2 work can broaden it from a JSON file or live mining.

**Output shape** for a matched mod:

```html
Lua((MOD:<span class="mod-attribution" data-workshop-id="2857548524">ImmersiveSolarArrays</span>)).foo(bar.lua:12)
```

For an unknown mod:

```html
Lua((MOD:<span class="mod-attribution">SomeMod</span>)).foo(bar.lua:12)
```

The class `mod-attribution` is what iblogs CSS / JS keys off to (a) style the badge and (b) attach the click-through to the Steam Workshop URL `https://steamcommunity.com/sharedfiles/filedetails/?id=<id>` when `data-workshop-id` is present. **Codex emits semantic HTML; iblogs supplies the link behavior.**

## Tasks

Sequential. One commit per task. No remote pushes until the iblogs side is ready (see Task 3).

### Task 1 — `ProjectZomboidModAttributor` class

Files:

- `src/Util/ProjectZomboid/ProjectZomboidModAttributor.php` — concrete class extending `Modification` and implementing `StackTraceEnricherInterface`. Carries the `MOD_NAME_TO_WORKSHOP_ID` typed class constant. Both `modify($text): string` and `enrich($trace): string` call `decorate($text)`. The decorate regex matches `MOD:<name>)` (the `)` anchors the end of the upstream `Lua((MOD:NAME))` shape) and wraps it in the span.
- `test/tests/Util/ProjectZomboid/ProjectZomboidModAttributorTest.php` — unit tests covering: known mod gets workshop-id attr; unknown mod gets span without attr; multiple frames in one input; plain text without `MOD:` passthrough; idempotence (running modify twice yields the same output); HTML safety (mod names containing `<` or `&` are escaped before wrapping — pulled through `htmlentities`); apostrophes in mod names (`Spongie's Clothing`) survive the regex.

**Commit message:** `feat(pz): ProjectZomboidModAttributor for Lua stack-frame mod resolution`.

### Task 2 — Bump CHANGELOG and CLAUDE.md

The Phase-1 `0.4.0` CHANGELOG entry is still on the previous branch (not yet pushed). Two paths:

- **(A) Append `[0.5.0]` section** above `[0.4.0]`, dating today; document the ModAttributor as a new public-API addition.
- **(B) Fold into `[0.4.0]`** by editing the existing entry to include the new class.

Pick **(A)**. The Phase-1 v0.4.0 tag is already created locally and we don't want to break that. Phase 2-A becomes 0.5.0.

CLAUDE.md gets a short addendum under "Pitfalls" noting the static-map seeding strategy and that unknown mods degrade gracefully.

**Commit message:** `docs: cut v0.5.0 in CHANGELOG; CLAUDE.md note on ModAttributor`.

After review approval: backup tag `backup/pre-v0.5.0`, then annotated `v0.5.0`. Don't push until iblogs is ready.

### Task 3 — iblogs rewire (next session OR same if context allows)

Files in iblogs:

- `composer.json` — bump `indifferentketchup/codex` from `^0.4.0` to `^0.5.0`.
- `src/Printer/Printer.php` — extend the existing `setLog` override (added in Phase 1's iblogs commit) so that when the log is `instanceof ProjectZomboidLog`, the printer also adds a `ProjectZomboidModAttributor` as a separate Modification (use `parent::addModification(...)` or whatever the existing pluralization path is — read the existing wiring). When the log is `MinecraftLog`-derived, only the FormatModification delegate is set (existing behavior). PZ logs additionally get the ModAttributor.
- `web/public/css/iblogs.css` — add a small `.mod-attribution` styling block: subtle background tint, monospace, small Workshop-icon glyph if `data-workshop-id` is present (use `[data-workshop-id]:after { content: "↗"; opacity: 0.6 }` or similar — single line CSS, no new JS).
- `web/public/js/log.js` — add ~5 lines of click handler: when a `.mod-attribution[data-workshop-id]` is clicked, open `https://steamcommunity.com/sharedfiles/filedetails/?id=<value>` in a new tab.
- `src/Data/Deobfuscator.php` — leave alone (Sherlock port still Phase 2-B).

**Commit message in iblogs:** `feat(pz): mod attribution badges on Lua stack frames via ik-codex 0.5.0`.

## Phase 2-B onward (not in this plan)

- **PZ chat-channel inline formatter** — `ProjectZomboidInlineFormat extends InlineFormatModification`. Wraps `chat=<channel>` tokens in spans with `format-channel-<name>` classes for per-channel coloring.
- **Minecraft variant ports** — Fabric, Quilt, Forge, NeoForge, Bukkit/Spigot/Paper/Purpur/Folia, Mohist/Magma/Arclight, BungeeCord/Velocity/Geyser/Waterfall, Bedrock, Pocketmine, plus crash reports + launcher client logs.
- **Sherlock port** — Mojang/Yarn deobfuscator as `MinecraftDeobfuscator implements StackTraceEnricherInterface`.
- **Minecraft `Analyser`/`Analysis` ports** for the actual error classification per server flavor.
- **Broaden the `MOD_NAME_TO_WORKSHOP_ID` map** — either by extending the static seed, or by replacing it with a JSON file loaded at runtime, or by mining `Loading:` paths from the same log being analyzed (live extraction).
