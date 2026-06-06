# Log-view fold defer + level-color overhaul — design

**Date:** 2026-05-04
**Status:** Draft, pending review
**Owner:** indifferentketchup

## Problem

After saving a log, the browser redirects to `/<id>` (the log view). On a multi-thousand-line log, the page locks up for a noticeable interval before becoming interactive. The cause is `web/public/js/log.js` calling `applySmartFold()` synchronously at module top-level — it walks every `.entry`, mutates `style.display` on hidden runs, and inserts fold-bar elements, all on the main thread before the browser gets a chance to paint the parsed HTML. From the user's perspective, "the computer freezes when the log loads then tries to trim it down and fold things."

Separately, the level-color palette no longer matches user intent:

- `.level-warning` is currently `#FF6625` (orange-red) — should read as a clear yellow.
- ERROR entries with stack traces (multiple lines) and single-line errors look identical (both red). The user wants stack-trace-bearing entries to read as orange, leaving single-line errors red.

## Goals

1. The log-view page paints the rendered log before the smart-fold pass runs, so the window never appears frozen on initial load.
2. Provide a visible "still working" indicator while the fold pass executes, so the brief delay reads as deliberate work rather than a hang.
3. Update the three level-text colors to match user intent, using Tailwind palette values per the project's color-selection rule:
   - WARNING → `yellow-400` (`#facc15`)
   - Stack-trace ERROR (an ERROR entry containing continuation lines) → `orange-400` (`#fb923c`) across the whole entry (header + continuation)
   - Single-line ERROR → `red-500` (`#ef4444`) as the codebase default; remains overridable via `IBLOGS_FRONTEND_COLOR_ERROR` env var

## Non-goals

- Chunking `applySmartFold` itself across frames (`requestIdleCallback` slicing). The current implementation completes within ~one frame's budget on the largest expected logs once it isn't competing with initial paint; deferring the start is the cheap win and we accept this. If post-defer timing is still poor on real-world worst-case logs, slicing becomes a follow-up.
- Server-side / `Printer.php` changes. The stack-trace detection is done in CSS via `:has()` against the existing `.multiline` class — no PHP change needed.
- Reworking the configurable `--error` CSS variable mechanism. We only update its hardcoded *default* in `example.config.json`; deployed instances overriding the env var are unaffected.
- Reskinning `.level-critical` / `.level-emergency` / dead `.level-stacktrace`. Those continue to share the configurable `var(--error)`; only the new orange rule is layered on top via `:has()`.

## Design

### 1. Defer smart fold off the initial paint

`web/public/js/log.js` currently runs `applySmartFold();` at line 68 — synchronously, at module top-level, before the browser has had a chance to paint the parsed log HTML.

Replace with a deferred dispatch:

```js
document.body.classList.add('is-folding');
requestAnimationFrame(() => requestAnimationFrame(() => {
    applySmartFold();
    document.body.classList.remove('is-folding');
}));
```

**Why two `requestAnimationFrame` calls.** The first `rAF` callback fires *before* the next paint. Work scheduled in it still blocks paint. The second nested `rAF` fires *after* the next paint commits, guaranteeing the un-folded log has rendered to the screen before fold work begins. This is the standard "wait for one paint" pattern; `setTimeout(..., 0)` is the alternative but doesn't carry the same paint guarantee on all browsers.

**`is-folding` body class.** Used to drive the loading-state CSS (next section). Added before scheduling, removed inside the deferred callback once `applySmartFold()` returns.

### 2. "Folding…" loading state

Add to `web/public/css/iblogs.css`:

```css
body.is-folding .log-inner {
    opacity: 0.4;
    pointer-events: none;
}

body.is-folding::after {
    content: "Folding…";
    position: fixed;
    top: 1rem;
    left: 50%;
    transform: translateX(-50%);
    padding: 0.5rem 1rem;
    background: var(--bg);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 4px;
    font-size: 0.9rem;
    z-index: 1000;
    pointer-events: none;
}
```

Effect: while folding, the rendered log dims to 40% opacity and a small "Folding…" pill anchors near the top of the viewport. Both vanish the moment the fold pass completes and `is-folding` is removed.

No JS-side spinner library; pure CSS pseudo-element.

### 3. Level-color updates

Edit `web/public/css/iblogs.css` lines 1227–1236:

```css
/* before */
.level-warning {
    color: #FF6625;
}

.level-error,
.level-critical,
.level-emergency,
.level-stacktrace {
    color: var(--error);
}
```

```css
/* after */
.level-warning {
    color: #facc15;  /* tailwind yellow-400 */
}

.level-error,
.level-critical,
.level-emergency,
.level-stacktrace {
    color: var(--error);
}

/* Stack-trace ERROR entries (those with continuation lines) override to orange across the whole entry */
.entry-error:has(.multiline) .level {
    color: #fb923c;  /* tailwind orange-400 */
}
```

The new `:has()` rule sits **after** the `.level-error` block so it wins by source order. It targets `.level` (the wrapping span emitted by `Printer::printEntry()`) inside any `.entry-error` containing a `.multiline` descendant, which colors both the header line and every continuation line orange. Single-line ERROR entries (no `.multiline` child) fall through to the prior `.level-error` rule and stay red.

### 4. Default error color → Tailwind red-500

Edit `example.config.json`:

```json
"error": "#ef4444"
```

(was `#f62451`)

This applies only to the documented default. Production deploys setting `IBLOGS_FRONTEND_COLOR_ERROR` are unaffected.

## Compatibility

- **`:has()` CSS selector.** Supported in Chrome/Edge ≥ 105 (Aug 2022), Safari ≥ 15.4 (Mar 2022), Firefox ≥ 121 (Dec 2023). Acceptable for a self-hosted internal tool; no IE/legacy support required.
- **`requestAnimationFrame`.** Universal (>10 years).
- **Fallback if `:has()` is unsupported.** All ERROR entries (including stack-trace ones) revert to red — i.e. the pre-change behavior, no breakage.

## Files changed

- `web/public/js/log.js` — replace synchronous `applySmartFold()` call with deferred dispatch (two-`rAF` pattern + body-class toggle). ~7 lines changed near line 68.
- `web/public/css/iblogs.css` — change `.level-warning` color, add `.entry-error:has(.multiline) .level` rule, add `body.is-folding` rules. ~25 lines added/changed.
- `example.config.json` — bump `frontend.color.error` default. 1 line changed.

No PHP changes. No new files. No test changes (no test suite in iblogs).

## Verification

Manual smoke-test (no automated tests exist for the view-page UX):

1. Start the dev stack (`cd dev && docker compose up`).
2. Paste a multi-thousand-line log containing both single-line ERROR entries and ERROR entries with stack-trace continuation lines (e.g. one of the production Project Zomboid server logs from `Logs.zip`).
3. Click Save; observe redirect to `/<id>`.
4. **Expected:** The log content paints visible-but-dimmed; a "Folding…" pill briefly appears near the top; pill and dimming disappear once folding completes; window remains responsive (scrolling, button clicks) throughout. No long freeze.
5. Inspect a stack-trace ERROR entry — header line and continuation lines should all be orange (`#fb923c`).
6. Inspect a single-line ERROR entry — should remain red (`var(--error)` = `#ef4444`).
7. Inspect a WARNING entry — should be yellow (`#facc15`).

## Out of scope / follow-ups

- Slicing `applySmartFold` itself if the deferred-but-still-synchronous pass turns out to be the long-pole on the worst real-world logs.
- Re-evaluating other top-level synchronous initialization in `log.js` (timestamp conversion loop at line 202, settings setup, copy-button wiring, scrollbar logic) for similar deferral if measurements show further main-thread contention.
- Removing the dead `.level-stacktrace` CSS class once we're confident no downstream consumer relies on it.
