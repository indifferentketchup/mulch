# Log-view UX overhaul — plan

**Date:** 2026-05-07
**Branch:** `log-view-overhaul-bootstrap` (off `main`)
**Owner:** indifferentketchup
**Backup tag:** `backup/pre-log-view-overhaul` (lightweight, local)

User intent: when viewing a PZ (or any) log, the page should foreground errors and stack traces. Warnings should be summarised, not inline noise. The mod-loading firehose (`Loading: steamapps/...`) shouldn't drown the screen. The fold-bar drag handle should feel like a real interactive control rather than decorative chrome. And the level-color palette should match WARNING=yellow / stack-trace ERROR=orange / single-line ERROR=red.

This plan lands the work on a single feature branch as three commits, each independently reviewable.

## Task A — Implement the May-4 fold-defer + level-color spec

**Spec:** `docs/superpowers/specs/2026-05-04-log-view-fold-defer-and-level-colors-design.md` (committed earlier in this branch).

Implement that spec exactly as written. Summary:

- `web/public/js/log.js` line 68: replace synchronous `applySmartFold()` with the two-`requestAnimationFrame` deferred dispatch + `is-folding` body class toggle (pattern given in spec §1).
- `web/public/css/iblogs.css`:
  - Change `.level-warning` color from `#FF6625` to `#facc15` (Tailwind yellow-400).
  - Add `.entry-error:has(.multiline) .level { color: #fb923c; }` rule (orange-400) **after** the `.level-error` block so it wins by source order.
  - Add `body.is-folding .log-inner` opacity-dimming rule and `body.is-folding::after` "Folding…" pill (full CSS in spec §2).
- `example.config.json`: change `frontend.color.error` default from `#f62451` to `#ef4444` (Tailwind red-500). Production deploys overriding `IBLOGS_FRONTEND_COLOR_ERROR` are unaffected.

No PHP changes. No test changes (no test suite). Verification: manual smoke-test per spec §"Verification".

**Commit message:** `feat: defer smart-fold off initial paint + retune level palette`

## Task B — Level-aware default view (errors foregrounded; warnings summarised; mod-load noise hidden)

The current smart-fold (`applySmartFold` in `log.js`) hides only entries that are **outside ±25 of an ERROR**, regardless of level. So a 50-line ERROR-free run of `Loading: steamapps/.../mods/...` lines is hidden, but a single `Loading:` line within ±25 of an ERROR stays visible. Likewise warnings are treated identically to mod-load noise — never summarised, only proximity-folded.

Replace the proximity-only model with **level-aware proximity folding**:

### Rules

1. **`Loading:` mod-load lines** — entries whose first body line matches `^Loading:\s+steamapps/` are *always* hidden (proximity-window-independent). They participate in fold-bar counts as `mod_load_lines`.
2. **INFO / LOG-level entries** — hidden by default, even within the ±25 error proximity window. They count toward the fold-bar as `info_lines`.
3. **WARN-level entries:**
   - **Within ±5 entries of an ERROR**: visible (kept as tight context). The window is intentionally tighter than the ±25 used for ERROR proximity to avoid drowning the error in warning noise.
   - **Outside ±5 of any ERROR**: hidden, counted as `warning_lines`.
4. **ERROR-level entries** — always visible, with all their multiline continuations (existing behavior).
5. **Logs with no errors** — fall through to existing behavior: nothing folded.

### Fold-bar label format

Today: `<grip> 47 lines hidden · 12345-12391 · drag to reveal <grip>`.

After: include a level breakdown when the run is mixed:

```
<grip> 47 hidden · 5 ⚠ warnings · 30 info · 12 mod-loads · 12345-12391 · drag to reveal <grip>
```

Levels with zero count are omitted. Use the existing `.level-warning`, `.level-info` Tailwind colors for the breakdown spans (so the warning count reads yellow). The line-range and "drag to reveal" segments remain unchanged. The `<i class="fa-solid fa-grip-lines">` grip icons remain.

### Settings affordance

Add a **single new setting checkbox** in the existing `.setting-checkbox` infrastructure (scope: per-browser localStorage like the others), labelled "Show all entries". When checked, `applySmartFold()` becomes a no-op (existing fold bars are removed and their hidden entries shown). When unchecked (default), the new level-aware fold runs.

Wire it through the existing `getCurrentSettings()` / `applySetting()` / BroadcastChannel pipeline already in `log.js`. The setting key is `showAllEntries` (camelCase to match the existing keys' convention).

### Files changed

- `web/public/js/log.js`:
  - In `applySmartFold()`: replace the "must show within ±25" loop with the level-aware rules above. Compute per-entry visibility class (`always_visible` / `proximity_warning` / `hidden_warning` / `hidden_info` / `hidden_mod_load`) before doing the hide/fold pass.
  - Update `createFoldBar` / `renderFoldLabel` to accept and render the level breakdown counts.
  - Wire the `showAllEntries` setting: add to `getCurrentSettings()`, `applySetting()`, and re-run / undo the fold pass when it toggles. Removing folds when toggling on means iterating any `.collapsed-lines-foldable` and calling `applyFoldReveal(bar, bar._hiddenEntries.length)` then `bar.remove()`.
- `web/public/css/iblogs.css`: add color spans for the level-breakdown segments inside the fold-bar count if the existing `.level-warning` / `.level-info` colors don't apply correctly through the nested span (verify in browser; might be free).
- `web/frontend/parts/header.php` (or wherever the existing setting checkboxes are rendered): add the "Show all entries" checkbox row matching the existing pattern.

### Verification

1. Open a PZ log with mixed ERROR/WARN/INFO/Loading entries (any of the recent files in `.scratch/pz2/Logs2/` once redacted).
2. **Expected default:** ERRORs and their continuations visible; warnings within ±5 of an error visible; everything else collapsed under fold bars; `Loading: steamapps/...` lines never visible (count toward "mod-loads" in fold-bar label).
3. Fold-bar text shows level breakdown: e.g. "47 hidden · 5 ⚠ warnings · 30 info · 12 mod-loads · …".
4. Toggle "Show all entries" — folds expand and remain expanded across reload (localStorage).
5. Re-load with toggle off — folds re-apply on next page load.

**Commit message:** `feat: level-aware default view with mod-load filter`

## Task C — Make the fold-bar slider feel like an actual control

Today the fold bar is a 100%-width hatched strip with a hidden-line count and `cursor: ns-resize`. There is no visible drag handle, no progress indicator, no "expand all" affordance. Drag direction is fixed to "reveal from top of hidden range" — counterintuitive when the fold sits above an error (the user expects revealing to bring lines *toward* the error).

### Changes

1. **Direction-aware reveal.** Determine, for each fold bar, the position of the nearest non-hidden ERROR entry:
   - Nearest ERROR is *below* the bar → reveal from **bottom** of hidden range (lines appear adjacent to the bar, growing downward toward the error). This is the current behavior conceptually but with the array order reversed.
   - Nearest ERROR is *above* the bar → reveal from **top** of hidden range (current behavior).
   - No ERROR adjacent → top reveal (default).

   Implementation: `applyFoldReveal(bar, n)` already iterates the hidden entries; add a `bar._revealDirection` ("top" | "bottom") set in `createFoldBar` based on the position of the surrounding errors, and flip the iteration order when `bottom`.

2. **Explicit "Expand all" affordance.** Add a small `<button>` to the right side of the fold-bar count. Icon: `fa-solid fa-angles-down` (or `fa-up-right-and-down-left-from-center`). Click reveals every remaining entry and removes the bar (same as `applyFoldReveal(bar, entries.length)`). Stop pointer-down propagation so the click doesn't trigger drag start.

3. **Reveal-progress indicator.** Update `renderFoldLabel` to prepend the revealed count when nonzero: `12 of 47 revealed · 35 hidden · …`. The trailing "drag to reveal" cue stays — the new visible grip (item 4) is the primary affordance, but the textual cue continues to back it up for keyboard / accessibility-first readers.

4. **Visible thumb during drag.** Add a CSS pseudo-element (`.collapsed-lines-foldable::before`) anchored to the vertical center of the bar, styled as a small grip badge (≈ 20px wide, semi-transparent). Increase opacity / size on `:hover` and `.collapsed-lines-dragging`. Pure CSS, no JS change.

5. **Click affordance.** Keep current click-reveals-25 default. No cycling.

### Files changed

- `web/public/js/log.js`:
  - `createFoldBar`: compute `_revealDirection` from surrounding error positions; append the "Expand all" button.
  - `applyFoldReveal`: respect `_revealDirection` when iterating.
  - `renderFoldLabel`: emit the revealed/hidden split.
- `web/public/css/iblogs.css`:
  - `.collapsed-lines-foldable` extensions: add `::before` grip badge, hover/drag-state opacity rules.
  - `.collapsed-lines-expand-all` button styling (small, low-emphasis, transparent border, accent on hover).

### Verification

1. Take a log with errors **above and below** large folded runs (mid-log error preceded and followed by long quiet stretches).
2. Drag the upper fold's bar — lines reveal from the **bottom** of the hidden range (closer to the error below). Drag the lower fold's bar — lines reveal from the **top** (closer to the error above). 
3. Click the "Expand all" button — the entire run reveals and the bar disappears in one action.
4. Hover the bar — visible grip badge appears, hatched background intensifies.
5. Reveal-progress label updates correctly across drag, click, and Expand-all interactions.

**Commit message:** `feat: direction-aware fold reveal + expand-all + visible grip`

## Out of scope

- Wiring Minecraft / Hytale / SevenDaysToDie detectives. Codex scaffolds for those games are empty stubs (per ik-codex CLAUDE.md). They're a separate, larger workstream.
- Rewriting `applySmartFold` to chunk across frames (`requestIdleCallback`). Deferring its **start** is the cheap win Task A delivers; further slicing is deferred to a follow-up if the worst-case logs still freeze.
- Touching `Printer.php` or any codex public API — these UI-only changes don't trigger the cross-repo sync rule (per iblogs CLAUDE.md).
- Reskinning `.level-critical` / `.level-emergency` / dead `.level-stacktrace`. Task A leaves them on `var(--error)`; the new orange rule is layered via `:has()`.

## Sequencing

A → B → C, sequentially (same files, would conflict in parallel). Each lands as one commit. Two-stage review (spec compliance → code quality) per task. Final whole-feature review before the user decides on merge / PR.
