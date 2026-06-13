---
name: iblogs
description: Paste, share & analyse game-server logs. A terminal with an attitude problem.
colors:
  signal-red: "#FF3838"
  monitor-black: "#1a1a1a"
  screen-white: "#e8e8e8"
  severity-orange: "#ff8c42"
  severity-amber: "#fbbf24"
typography:
  display:
    fontFamily: "'Plus Jakarta Sans', system-ui, sans-serif"
    fontWeight: 600
    fontSize: "clamp(1.75rem, 3vw, 2rem)"
    letterSpacing: "normal"
  body:
    fontFamily: "'Plus Jakarta Sans', system-ui, sans-serif"
    fontWeight: 400
    fontSize: "clamp(0.85rem, 2vw, 0.9rem)"
    lineHeight: 1.5
  label:
    fontFamily: "'Plus Jakarta Sans', system-ui, sans-serif"
    fontWeight: 500
    fontSize: "clamp(0.7rem, 1.8vw, 0.8rem)"
  mono-body:
    fontFamily: "'JetBrains Mono', 'Fira Code', monospace"
    fontWeight: 400
    fontSize: "clamp(0.75rem, 2vw, 0.9rem)"
    lineHeight: 1.6
  mono-label:
    fontFamily: "'JetBrains Mono', 'Fira Code', monospace"
    fontWeight: 500
    fontSize: "clamp(0.65rem, 1.8vw, 0.8rem)"
rounded:
  panel: "12px"
  md: "8px"
  sm: "6px"
  xs: "4px"
  pill: "999px"
spacing:
  page-padding: "clamp(1rem, 2.5vw, 1.25rem)"
components:
  button-primary:
    backgroundColor: "{colors.signal-red}"
    textColor: "{colors.monitor-black}"
    rounded: "{rounded.md}"
    padding: "clamp(0.6rem, 2vw, 0.7rem) clamp(1.2rem, 3vw, 1.5rem)"
  button-ghost:
    backgroundColor: transparent
    textColor: "{colors.signal-red}"
    rounded: "{rounded.md}"
    padding: "clamp(0.35rem, 1.5vw, 0.4rem) clamp(0.85rem, 2.5vw, 1rem)"
  button-danger:
    backgroundColor: "{colors.signal-red}"
    textColor: "{colors.screen-white}"
    rounded: "{rounded.md}"
    padding: "clamp(0.6rem, 2vw, 0.7rem) clamp(1.2rem, 3vw, 1.5rem)"
---

# Design System: iblogs

## 1. Overview

**Creative North Star: "The Signal Trace"**

iblogs is a terminal with an attitude problem. You paste a broken log and it surfaces the signal — syntax-highlighted, line-numbered, problem-tagged — with the irreverent confidence of someone who's debugged a hundred crash loops at 2 AM. The interface is a conduit: input at the top, the log below, problems flagged in the panel. Every pixel that isn't doing analytic work had better be funny.

The dark canvas isn't a trend choice — it's the practical reality of reading thousands of log lines in a dark room mid-incident. The red accent is the one action color: the save button, the active line, the brand signal. Everything else recedes into monochromatic tonal layers. Mono type dominates because every character is either a log line, a file path, a Steam ID, or a severity count — exactly what you'd see in a terminal, but with taste.

This system explicitly rejects: AI-generated SaaS landing pages (gradient text, glassmorphism, eyebrow kickers, hero metrics), Hollywood hacker cliches (matrix-green-on-black, "cyber" fonts, retro terminal pastiche), game-themed interfaces (Minecraft pixel art, fantasy UI chrome), and over-designed dashboards (widgets, sparklines, heavy chrome).

**Key Characteristics:**

- **Red as signal, not decoration.** The accent covers one action per surface — the save button, the active line, the logo. Its rarity is the point.
- **Mono-forward data surfaces.** Log content, line numbers, metadata labels, mod names — all JetBrains Mono. Plus Jakarta Sans only appears where a human is speaking a sentence.
- **Personality in copy, not visuals.** Humor lives in taglines, empty states, error messages. No illustrations, no decorative gradients, no animated mascots.
- **Tonal, not border-defined.** Containers are separated by background luminance steps, not hard 1px borders. Borders exist only where structural separation is needed.
- **Grid as canvas.** The page sits on a subtle dot-grid pattern — a nod to terminal grids and engineering paper. Atmosphere, not ornament.

## 2. Colors

A cold-dark canvas with a single warm-red action signal. Everything else is monochromatic whites and derived surface tints. The entire palette flows from four base tokens via CSS `color-mix()`.

### Primary

- **Signal Red** (#FF3838): The one action color. Used for the primary save button, the header tagline verb, the active log line highlight, scrollbar thumb, toggle switches in checked state, and the logo icon fill. Appears on at most 10% of any viewport — its rarity is the point. Hover darkens via `color-mix(in srgb, var(--accent) 78%, var(--bg) 22%)`. Background tint for accent containers uses `color-mix(in srgb, var(--accent) 12%, transparent)`.

### Neutral

- **Monitor Black** (#1a1a1a): The page canvas. Body background, footer background, severity pill low/noise backgrounds (recessed chips). Near-black with no chroma cast — intentionally neutral so Signal Red pops without competing undertones.
- **Screen White** (#e8e8e8): Primary body and heading text. Full clarity against the dark canvas (~14:1 contrast). Used for all content text, button text on accent backgrounds, severity pill text.
- **Surface Layer** (derived): `color-mix(in srgb, var(--bg) 92%, var(--text) 8%)`. Main container, card, and panel backgrounds. One half-step above the canvas.
- **Elevated Layer** (derived): `color-mix(in srgb, var(--bg) 95%, var(--text) 5%)`. Hover-active surfaces, log viewer background, API docs header fills.
- **Inset Layer** (derived): Same as Surface — used for code blocks, API endpoint backgrounds, table fills where a recessed feel is needed.
- **Muted Text** (derived): `color-mix(in srgb, var(--text) 55%, var(--bg) 45%)`. Secondary and tertiary text — labels, metadata, placeholders, footer copy, info row headers. Verified ≥4.5:1 against the canvas for WCAG AA.
- **Translucent Border**: `rgba(255, 255, 255, 0.08)`. Thin separators — header/footer borders, info row dividers, log entry separators, table borders. White at 8% opacity reads as a dark grey line over the canvas.
- **Translucent Surface**: `rgba(255, 255, 255, 0.04)`. Button backgrounds for secondary/ghost variants, info row fills, collapsed-line row fills. Ultra-low opacity — reads as a barely-there lift.

### Semantic Severity

The five-tier severity scale maps problems from "crash imminent" to "probably nothing." Each tier has a text color for the pill and a tinted background:

- **Critical** (derived from `--error`): `color-mix(in srgb, var(--error) 70%, var(--text) 30%)`. Bright red — used for server crashes, fatal errors. Since `--error` is the same Signal Red hue, critical reads as a lighter-tint warning signal.
- **High** (#ff8c42): Orange. Serious but non-fatal issues — missing dependencies, broken configs.
- **Medium** (#fbbf24): Amber. Moderate warnings — deprecations, soft errors, version mismatches.
- **Low** (derived from `--text-muted`): Muted grey. Minor issues. Background is recessed (canvas color) rather than a surface tint to maintain AA contrast.
- **Noise** (derived): `color-mix(in srgb, var(--text) 52%, var(--bg) 48%)`. Engine noise, debug spam. Also recessed to the canvas. Hidden by default via the `setting-hide-engine-noise` body class.

### Legacy Format Colors

Minecraft-format color codes (the §-prefixed chat colors) use a fixed 16-color palette of hex values in the legacy sRGB space. These are used exclusively for syntax-highlighting inside log content — they do not participate in the design system's `color-mix()` derivation and are never used on UI chrome.

### Named Rules

**The Rarity Rule.** Signal Red covers one action per surface. In the paste view, it's the save button. In the log viewer, it's the active line highlight. If red appears in more than one unrelated place on the same viewport, one of them is wrong.

**The Derivation Rule.** Every surface color flows from four configurable base tokens (`--bg`, `--text`, `--accent`, `--error`) via CSS `color-mix()`. Changing those four tokens re-themes the entire site. No hardcoded derived hex values exist outside the legacy format colors and the fixed severity orange/amber.

**The Surface Rule.** Backgrounds are distinguished by luminance steps (canvas → surface → elevated), not borders. Never use a 1px border to define a container when a background shift will do. Translucent borders exist for structural dividers only.

**The Error-Accent Unity Rule.** Error states and the brand accent share the same red hue family. Context differentiates them: a red button is an action, a red log line is a problem, a red severity pill is a severity level. If two red elements on the same viewport could be confused, one of them needs a different treatment (glyph, label, container, or animation).

## 3. Typography

**UI Font:** Plus Jakarta Sans (with system-ui, sans-serif fallback)
**Data/Mono Font:** JetBrains Mono (with Fira Code, monospace fallback)

**Character:** A geometric sans (Plus Jakarta Sans) for human-readable UI surfaces paired with a coding mono (JetBrains Mono) for all data. The sans handles headings, buttons, labels, and prose — clean, modern, slightly warm. The mono handles every character that matters: log lines, line numbers, Steam IDs, mod names, file paths, severity counts. The system reads like a terminal where someone actually cared about the fonts.

### Hierarchy

- **Display** (Plus Jakarta Sans 600, clamp(1.75rem, 3vw, 2rem)): The logo wordmark in the header. Appears exactly once per page. Paired with an inline SVG logo icon.
- **Headline** (Plus Jakarta Sans 400, clamp(1rem, 3vw, 1.5rem) / 600 for the animated verb): The tagline — "Paste your logs." / "Share your logs." / "Analyse your logs." The verb word cycles through an animated typewriter effect.
- **Title** (Plus Jakarta Sans 600, clamp(1.1rem, 3vw, 1.25rem), 1.3 line-height): Log titles, API docs headings. Used in log headers for the filename/ID display.
- **Body** (Plus Jakarta Sans 400, inherits from body, 1.5 line-height): UI body text — empty states, error descriptions, API documentation prose. Capped at 65-75ch.
- **Label** (Plus Jakarta Sans 500, clamp(0.7rem, 1.8vw, 0.8rem)): Info row headers, setting labels, problem panel metadata. Sometimes 600 weight for emphasis.
- **Mono Body** (JetBrains Mono 400, clamp(0.75rem, 2vw, 0.9rem), 1.6 line-height): The dominant text surface — log content, paste textarea, code blocks, API response examples. This is what users actually read.
- **Mono Label** (JetBrains Mono 500, clamp(0.65rem, 1.8vw, 0.8rem)): Line numbers, log URL buttons, mod names, build hashes. Tabular-nums variant for alignment-sensitive data.

### Named Rules

**The No-Prose Rule.** If you're writing a sentence, use Plus Jakarta Sans. If you're naming a thing — a file, an ID, a count, a line — use JetBrains Mono. Button labels and headings are the exception: they may use either, matching the dominant content they label.

**The Line-Length Rule.** Log content and API prose each cap at their container width. No line ever wraps because of CSS width limits — only because of content length. The paste textarea and log viewer scroll horizontally when content exceeds the viewport.

## 4. Elevation

Depth is conveyed through tonal background layering, not shadows. The system has three tonal steps — canvas (Monitor Black), surface (bg + 8% text), and elevated (bg + 5% text). A container on the canvas is one step up. Headers and footers sit on the canvas with border separators. The only shadow in the system is on the save button's active state — a soft pulse animation that signals the primary action. Panels never cast shadows.

The dot-grid background pattern (`body::before`) adds a subtle spatial anchor — a fixed-position grid of tiny dots at 40px intervals — that reads as engineering graph paper. It's atmosphere, not functionality: the grid is pointer-events disabled and sits at z-index 0 behind all interactive content.

Both the grid background and the paste-area bottom fade gradient use opacity-based layering. These are visual atmosphere elements, not structural elevation.

### Shadow Vocabulary

- **Button Active** (`0 4px 12px rgba(0, 0, 0, 0.15)` + animated pulse ring): Applied to the save button in its active state. The pulse ring expands from 80% opacity accent to 0% at 12px radius over 1.5s — signals "ready to save." Not used elsewhere.
- **Popover** (`0 4px 20px rgba(0, 0, 0, 0.3)`): The settings and delete-confirmation popovers. Fixed-position with an arrow pseudo-element. The only UI element that casts a shadow — justified by the overlay context.

### Named Rules

**The Flat-At-Rest Rule.** Containers are flat at rest. Shadows appear only on two elements: the primary save button (to signal affordance) and the popover overlay (to lift it above the page). Never animate a shadow. Never add a shadow to define a container boundary — use a background shift instead.

**The Atmosphere Layer Rule.** The dot grid and fades are atmosphere (z-index 0, pointer-events: none). Never use them for functional layering. If a user should click it, it's structural, not atmosphere.

## 5. Components

### Buttons

- **Shape:** 8px radius (rounded.md). Uniform across all variants. Full-height with generous horizontal padding.
- **Primary (Save):** Signal Red fill, Monitor Black text, 600 weight sans-serif. Active press scales to 0.97. Hover deepens to a darker red via `color-mix()`. When active (log has content), gains a pulsing shadow ring. When disabled, drops to 0.5 opacity with `cursor: not-allowed`. Border: 2px transparent (for layout consistency).
- **Ghost / Transparent:** Transparent fill, Signal Red text, no border. Used for the paste-trigger buttons ("Paste", "Browse"). Hover applies a subtle dark overlay via `linear-gradient(#00000014, #00000014)`.
- **Danger (Delete):** Signal Red fill, Screen White text. Used in the delete-confirmation popover. Same shape as primary, distinct semantic role.
- **Dark / Secondary:** Translucent surface fill, Screen White text, 1px translucent border. Used for non-primary actions in the log footer toolbar. Hover fills to the accent background tint.
- **Small (Mini):** 60-70% of the default button scale. Used for log URL copy buttons, info row actions. Reduced padding: `clamp(0.35rem, 1.5vw, 0.4rem) clamp(0.85rem, 2.5vw, 1rem)`.

### Toggle Switches

- **Style:** Custom checkbox styled as a pill-shaped toggle. 2.5rem wide, 1.4rem tall, fully rounded (pill). Surface fill at rest, accent fill when checked.
- **Thumb:** White circle, 1rem diameter, positioned 0.2rem from the edge. Slides left-to-right on check. Unchecked thumb color is muted text.
- **Label:** Sans-serif body text to the left. Entire row (label + toggle) is a clickable setting with hover accent tint.
- **Transition:** `background-color 0.15s ease`, thumb `left 0.15s ease`. No transform, no shadow. Straightforward state toggle.

### Severity Pills

- **Shape:** 999px radius (fully rounded pill). Inline-flex, tight padding, uppercase tracked text.
- **Critical:** Signal Red tint background + Signal Red text (brightened via text mix). Used for server crashes.
- **High:** Orange tint background (#ff8c42) + orange text. Used for serious non-fatal issues.
- **Medium:** Amber tint background (#fbbf24) + amber text. Used for warnings, deprecation notices.
- **Low:** Recessed canvas background + muted text. Used for minor issues. Canvas-background ensures AA contrast.
- **Noise:** Recessed canvas background + ultra-muted text. Used for debug spam, engine chatter. Hidden by default via body class toggle.
- **Glyphs:** Each pill includes a text label (CRITICAL, HIGH, MEDIUM, LOW, NOISE) in 600-weight mono at 0.75rem, tracked 0.04em.
- **Layout in problem panel:** Each severity tier sits on a parent `.problem-item` with a tier-specific border color. The pill reads as a distinct chip, not blending into the entry background.

### Log Viewer Grid

The log viewer is the core component. It renders log entries as a two-column CSS grid:

- **Column 1 (line numbers):** `grid-template-columns: auto 1fr`. Line numbers are right-aligned, mono 500, muted text, in a bordered cell (1px translucent border on the right). Min-width 2.75rem. User-select: none.
- **Column 2 (content):** Left-padded, word-break enabled, Screen White text, mono 400. Contains the parsed log line with inline syntax-highlighting spans.
- **Entry rows:** `display: contents` — each entry contributes its cells but produces no wrapper box. Grid items are the individual line-number and content cells.
- **Active line highlight:** When a line is targeted via URL fragment, both cells gain a 15% accent tint background. The line number cell's number shifts to accent fill with bg-colored text.
- **Error lines:** Both cells gain a 10% error tint background. Active + error combines layers (25% error tint).
- **Virtualization:** Line-number and content cells use `content-visibility: auto` with `contain-intrinsic-size: auto 1.5em`. Off-screen cells skip layout/paint — essential for logs with 25,000+ entries. The browser remembers measured heights after first paint.
- **Firefox fallback:** Firefox doesn't support `content-visibility`, so the entire grid falls back to a `display: table` layout with fixed table-layout. Detected via `@supports (-moz-appearance: none)`.
- **Collapsed lines:** Folded entry groups display as a horizontal bar with a grip handle, line count label, and expand-all button. The count cell sits on a translucent surface fill with horizontal hatching (repeating linear gradient). On hover, the hatch and borders shift to accent color. The bar is draggable (ns-resize cursor) to reveal or re-hide lines from the top of the hidden range.
- **Multi-line entries:** Stack traces and continuations indent 64px. On mobile (≤800px), indent collapses to 0.

### Problem Panel

- **Structure:** A bordered container (1px translucent border, 8px radius, surface fill) with a header bar and a stacked list of problem entries.
- **Header:** Surface fill, border-bottom separator. Contains an accent-filled count badge (min-width 1.4rem, rounded at 4px, bg-colored text in 600 weight) and a title ("Problems" in sans 600).
- **Problem Entry:** A bordered row (1px border) with background tint colored per severity tier. Each entry contains:
  - **Entry link:** A clickable row (flex, border-radius 5px) with the severity pill, problem text, and a counter badge. Tier-specific border color. Hover deepens to full tier color.
  - **Stack trace:** A native `<details>` disclosure with a custom chevron (2px rotated border pseudo-element). Expands to reveal a mono pre block (inset bg, 1px border, 5px radius).
  - **Solutions:** Another `<details>` disclosure listing actionable solution items (icon + text, accent-colored icon).
  - **Mod tag:** An inline pill (999px radius, surface fill, border, muted text) linking to the relevant mod's Workshop page. Dashed border for inferred attributions. Hover shifts to accent tint.
- **Noise filter:** When `setting-hide-engine-noise` is active on body, noise-level entries are `display: none`. The panel header shows a count of hidden rows.

### Popover Menus

- **Style:** Fixed-position overlays using CSS anchor positioning. Surface-fill background, 1px translucent border, 8px radius. Soft shadow: `0 4px 20px rgba(0,0,0,0.3)`.
- **Arrow:** Pseudo-element triangle (10px, 45deg rotation) positioned at bottom-right, matching the surface fill and border.
- **Content:** Flex column with 0.25rem gap, 0.5rem padding. Settings items, action buttons, or delete-confirmation content.
- **Backdrop:** Transparent — clicking outside dismisses natively via the popover API.
- **Danger variant:** Red border, centered layout, larger padding. Used for the delete-confirmation popover with a message + two-button action row.
- **Error state:** Inline error banner (error tint bg, border, red text) that appears inside the popover on API failure.
- **Trigger:** Settings gear icon (anchor-positioned), delete trash icon (anchor-positioned). Active state rotates the gear icon or changes the trigger tint.

### Fold Bars

- **Collapsed (default):** A horizontal row (display: contents → grid cells) with two cells — a surface-tinted spacer cell (with 1px right border matching the line-number column) and a count/label cell (surface fill, mono 500, centered text like "… 247 lines …" with an expand icon).
- **Collapsed Hover:** The count cell shifts to accent tint background with accent-colored text. The icon color intensifies.
- **Foldable variant:** For the smart "errors + context" fold, the bar gains a draggable handle (ns-resize cursor). The count cell adds horizontal hatching (repeating linear-gradient) and a visible grip badge (24px × 10px, border, hatch pattern). Hover/drag states shift hatch, border, and grip to accent colors. Drag state adds an inset accent border + soft shadow (`0 6px 16px`).
- **Grip badge:** A small rectangle on the left of the count cell — anchored left, vertically centered. Visual affordance: "grab here." Opacity 0.3 at rest, 0.8 on hover/drag.
- **Expand-all button:** A small transparent button (1.75rem × 1.5rem, muted text, expand icon) on the right of the count cell. Hover fills with 15% accent tint, accent-colored icon, 50% accent border.
- **Interaction:** Vertical pointer-drag on the foldable bar reveals lines from the top of the hidden range. The bar serves as both indicator and control — resting state shows "X lines hidden", dragging progressively reveals them.

### Mod Attribution Badges

- **Style:** Inline blocked element. 18% accent tint background, accent-colored text, 500 weight, 3px radius. Links to Workshop pages append an "↗" indicator. Hover deepens tint to 32%.
- **Context:** Appears inline within log content lines — directly in the parsed HTML output of the codex printer. Not a separate UI component but worth noting as a signature visual element.

## 6. Do's and Don'ts

### Do:

- **Do** use Signal Red for exactly one primary action per surface. The save button, the active line, the logo. Let its rarity be the point.
- **Do** use JetBrains Mono for all data surfaces — log content, line numbers, IDs, severity counts, mod names. Plus Jakarta Sans is for prose and UI chrome only.
- **Do** define containers with tonal background steps (canvas → surface → elevated → inset). Use 1px translucent borders only where structural separation is needed.
- **Do** use the dot-grid pseudo-element background (`body::before`) as atmosphere — pointer-events: none, z-index 0. Never make it interactive.
- **Do** pair every severity color with a text label and a distinct background tint. Pill + glyph + label = three channels of meaning. Color is reinforcement, never the sole carrier.
- **Do** use `content-visibility: auto` for large log viewers with 25,000+ entries. The one-line `contain-intrinsic-size` fallback keeps scrollbar accuracy acceptable pre-measurement.
- **Do** provide Firefox fallbacks for CSS features that don't degrade gracefully (Grid → Table, content-visibility → eager render).
- **Do** respect `prefers-reduced-motion` by collapsing all transitions and animations to near-instant. The typewriter tagline effect, the pulse ring, the checkbox toggle — all collapse to instant swaps.
- **Do** use `:focus-visible` for keyboard-only focus rings (2px Signal Red outline, 2px offset). Pointer users see no ring.
- **Do** keep body text at ≥4.5:1 contrast against the canvas. Muted text, labels, and placeholder text included — never drop below AA.
- **Do** put personality in copy, not decoration. The tagline, the empty state, the error messages — that's where the brand voice lives. Never in a gradient, an illustration, or an animation.

### Don't:

- **Don't** use gradient text, `background-clip: text`, or any decorative gradient effects. Solid colors only. Emphasis via weight, size, or spacing — never via a rainbow.
- **Don't** use glassmorphism, backdrop-filter blur cards, or translucent floating containers as a default aesthetic. Popovers are the only translucent overlay — and they're functional, not decorative.
- **Don't** add tiny uppercase tracked eyebrows ("PASTE" / "ANALYZE" / "SHARE") above every section. The tagline's typewriter verb is the one deliberate voice moment; repeating the pattern is AI grammar.
- **Don't** use numbered section markers (01 / 02 / 03) as decorative scaffolding. The log viewer is a continuous stream, not a step-by-step flow.
- **Don't** create card grids (icon + heading + text, repeated identically). Card-like containers are single-use — the log panel, the problem panel, the paste area. Never nest cards inside cards.
- **Don't** use `border-left` or `border-right` greater than 1px as a colored accent stripe on any element. Full borders, background tints, or leading icons are the alternatives.
- **Don't** use a radius larger than 12px on any container, panel, button, or input. Larger radii read as decorative puff. Pills (severity, mod tags) are the exception — 999px is correct for inline chip elements.
- **Don't** use matrix-green-on-black, "cyber" fonts, CRT scanline effects, or any Hollywood hacker aesthetic. The terminal influence is typographic (mono, grid), not cinematic.
- **Don't** use Minecraft pixel art, fantasy UI chrome, block textures, or any game-themed visual language. The tool analyzes game logs; it is not the game.
- **Don't** use arbitrary z-index values (999, 9999). The scale is: dropdown/popover (10), save button pulse (10), folding indicator (1000), paste error (1000). Each number is deliberate.
- **Don't** animate CSS layout properties (`width`, `height`, `top`, `left`, `margin`, `padding`). Transform + opacity only. The checkbox toggle's `left` property is the one legacy exception — it predates this rule.
- **Don't** use marketing buzzwords ("streamline", "empower", "leverage", "seamless") in the interface. The copy is technical and self-deprecating or it's nothing.
- **Don't** pair a 1px solid border with a box-shadow blur ≥ 16px on the same element. Pick one boundary treatment. Popover shadows are the one exception — they need both for the overlay context.
