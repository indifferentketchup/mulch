/* line numbers */
updateLineNumber(location.hash);

for (let line of document.querySelectorAll('.line-number')) {
    line.addEventListener("click", () =>
        updateLineNumber(line.attributes.getNamedItem("id").value));
}

function updateLineNumber(id) {
    if (id && id.startsWith('#')) {
        id = id.substring(1);
    }

    if (!id) {
        return;
    }

    let element = document.getElementById(id);
    if (element.classList.contains("line-number")) {
        for (const line of document.querySelectorAll(".line-active")) {
            line.classList.remove("line-active");
        }
        element.closest('.entry').classList.add('line-active');
    }
}

/* Scroll to top/bottom buttons */
const downButton = document.getElementById("down-button");
if (downButton) {
    downButton.addEventListener("click", () => scrollToHeight(document.body.scrollHeight));
}

const upButton = document.getElementById("up-button");
if (upButton) {
    upButton.addEventListener("click", () => scrollToHeight(0));
}

/**
 * Scroll to a specific height
 * Disable smooth scrolling for large pages
 * @param {number} top height to scroll to
 * @param {number} [smoothScrollLimit] only use smooth scrolling if the distance is less than this value
 */
function scrollToHeight(top, smoothScrollLimit = 10000) {
    const distance = Math.abs(document.documentElement.scrollTop - top);
    const behavior = (distance < smoothScrollLimit) ? "smooth" : "instant";
    window.scrollTo({left: 0, top, behavior});
}

/*
 * Level-aware smart fold around errors.
 *
 * Visibility rules (highest precedence first):
 *   1. Mod-load lines (`Loading: steamapps/...`) are always hidden.
 *   2. INFO-tier entries (info / notice / debug / log) are always hidden.
 *   3. WARNING entries are visible only within ±WARNING_PROXIMITY of any
 *      ERROR; otherwise hidden.
 *   4. ERROR entries (entry-error class — covers error, critical, alert,
 *      emergency) are always visible, including all multiline continuations.
 *   5. Logs with no errors fall through unchanged: nothing folded.
 *
 * Runs of consecutive hidden entries collapse into a draggable fold bar.
 * Vertical drag on the bar progressively reveals or re-hides lines from
 * either end of the hidden range, depending on `bar._revealDirection`
 * (computed in createFoldBar): if the nearest visible ERROR is below the
 * bar, lines reveal from the bottom (toward the error); if above, from the
 * top. A click without drag reveals the next DEFAULT_CLICK_REVEAL lines in
 * the same direction. An explicit "Expand all" button on the right edge
 * reveals every remaining entry at once. The bar's label includes a level
 * breakdown (warnings / info / mod-loads) when the corresponding count is
 * nonzero, plus an "X of Y revealed" prefix once partially expanded.
 *
 * The "Show all entries" setting (data-key="showAllEntries") bypasses this
 * pipeline — when checked, applySmartFold is a no-op and any existing fold
 * bars are unfolded. Toggling it off re-runs the fold against the current
 * DOM. The error-count chip in the header is purely informational.
 */
const ERROR_WINDOW_SIZE = 25;       // legacy constant, kept in case future rules reuse it
const WARNING_PROXIMITY = 5;        // ±entries from any ERROR within which WARNINGs stay visible
const PIXELS_PER_LINE_DRAG = 6;
const DEFAULT_CLICK_REVEAL = 25;
const DRAG_PIXEL_THRESHOLD = 3;
const MOD_LOAD_REGEX = /^Loading:\s+steamapps\//;

document.body.classList.add('is-folding');
// Outer rAF paints `is-folding`; inner runs after that first frame commits,
// so the un-folded log paints visibly before the synchronous fold work begins.
requestAnimationFrame(() => requestAnimationFrame(() => {
    try {
        if (!isShowAllEntriesEnabled()) {
            applySmartFold();
        }
    } finally {
        document.body.classList.remove('is-folding');
    }
}));

/**
 * Return the level token (info / notice / warning / error / ...) for an entry
 * by inspecting its inner `.level` span. The Printer emits `level level-X` so
 * we look for any `level-<token>` class. Returns null if none is found.
 *
 * @param {Element} entry .entry div
 * @returns {string|null}
 */
function entryLevel(entry) {
    const level = entry.querySelector(".line-content > .level");
    if (!level) return null;
    for (const cls of level.classList) {
        if (cls.startsWith("level-") && cls !== "level-prefix" && cls !== "level-title" && cls !== "level-comment") {
            return cls.slice("level-".length);
        }
    }
    return null;
}

/**
 * @param {Element} entry .entry div
 * @returns {string} text of the line content (used for the mod-load regex)
 */
function entryText(entry) {
    const lc = entry.querySelector(".line-content");
    return lc ? lc.textContent : "";
}

function applySmartFold() {
    const lines = Array.from(document.querySelectorAll('.log-inner > .entry'));
    const total = lines.length;
    if (!total) return;

    // Pass 1: classify each entry by level + mod-load match. The Printer puts
    // `entry-error` on every line of an ERROR entry (header + multiline
    // continuations) so a simple per-entry classlist check is sufficient —
    // we never need to "extend visibility" forward from a header.
    const isError = new Array(total);
    const isWarning = new Array(total);
    const isModLoad = new Array(total);
    let sawError = false;
    for (let i = 0; i < total; i++) {
        const e = lines[i];
        isError[i] = e.classList.contains("entry-error");
        isModLoad[i] = MOD_LOAD_REGEX.test(entryText(e).trimStart());
        isWarning[i] = !isError[i] && entryLevel(e) === "warning";
        if (isError[i]) sawError = true;
    }
    if (!sawError) return;

    // Pass 2: compute mustShow with rule precedence. Mod-loads and INFO-tier
    // entries are unconditionally hidden; warnings are visible only within
    // ±WARNING_PROXIMITY of any error; errors are always visible.
    const mustShow = new Array(total).fill(false);
    for (let i = 0; i < total; i++) {
        if (isModLoad[i]) continue;          // rule 1
        if (isError[i]) {                    // rule 4
            mustShow[i] = true;
            continue;
        }
        if (isWarning[i]) {                  // rule 3
            const lo = Math.max(0, i - WARNING_PROXIMITY);
            const hi = Math.min(total - 1, i + WARNING_PROXIMITY);
            for (let j = lo; j <= hi; j++) {
                if (isError[j]) { mustShow[i] = true; break; }
            }
        }
        // else: INFO-tier (rule 2) — leave hidden
    }

    // Pass 3: hide unmarked entries; emit a fold bar at the START of each run.
    // Per-run breakdown counts are not tracked here — renderFoldLabel calls
    // recomputeBreakdown over the still-hidden subset on every render.
    let runEntries = [];
    const flushRun = () => {
        if (!runEntries.length) return;
        const bar = createFoldBar(runEntries);
        runEntries[0].insertAdjacentElement("beforebegin", bar);
        runEntries = [];
    };
    for (let i = 0; i < total; i++) {
        if (!mustShow[i]) {
            lines[i].style.display = "none";
            runEntries.push(lines[i]);
        } else {
            flushRun();
        }
    }
    flushRun();
}

function createFoldBar(entries) {
    const bar = document.createElement("div");
    bar.classList.add("collapsed-lines", "collapsed-lines-foldable");
    bar.appendChild(document.createElement("div")); // line-number column slot

    const count = document.createElement("div");
    count.classList.add("collapsed-lines-count");
    bar.appendChild(count);

    // Visible grip "thumb" — a real DOM element styled in CSS as a small
    // rounded rectangle with horizontal lines, so the bar reads as something
    // grabbable rather than decorative chrome.
    const gripBadge = document.createElement("span");
    gripBadge.className = "collapsed-lines-grip";
    gripBadge.setAttribute("aria-hidden", "true");
    count.appendChild(gripBadge);

    // Wrapper holding the textual label. renderFoldLabel only touches this
    // element, so the persistent grip + expand-all stay across re-renders.
    const labelText = document.createElement("span");
    labelText.className = "collapsed-lines-label";
    count.appendChild(labelText);

    // "Expand all" button — chevrons-double-down icon on the right edge.
    // Stops propagation so dragging on the button doesn't initiate a
    // fold-bar drag, and the click reveals every remaining entry.
    const expandAll = document.createElement("button");
    expandAll.type = "button";
    expandAll.className = "collapsed-lines-expand-all";
    expandAll.setAttribute("aria-label", "Expand all hidden entries");
    expandAll.title = "Expand all";
    const expandIcon = document.createElement("i");
    expandIcon.className = "fa-solid fa-angles-down";
    expandAll.appendChild(expandIcon);
    expandAll.addEventListener("click", (e) => {
        e.stopPropagation();
        applyFoldReveal(bar, bar._hiddenEntries.length);
        bar.remove();
    });
    expandAll.addEventListener("pointerdown", (e) => e.stopPropagation());
    count.appendChild(expandAll);

    bar._hiddenEntries = entries;
    bar._revealedCount = 0;
    // _revealDirection is fixed at fold-bar creation; do not recompute per-drag (would be O(N) DOM walk).
    bar._revealDirection = computeRevealDirection(entries);
    bar._labelText = labelText;

    renderFoldLabel(bar);
    attachFoldDragHandler(bar);
    return bar;
}

/**
 * Decide whether a fold bar should reveal hidden entries from the top or the
 * bottom of its hidden range. The goal is for revealed lines to appear
 * adjacent to the nearest visible ERROR — if the error is below the fold,
 * lines should grow upward from the bottom; if above, downward from the top.
 *
 * @param {Element[]} entries hidden entries (in DOM order)
 * @returns {"top" | "bottom"}
 */
function computeRevealDirection(entries) {
    if (!entries.length) return "top";
    const first = entries[0];
    const last = entries[entries.length - 1];

    // Walk forward from the last hidden entry until we hit a visible
    // .entry-error or run out of siblings. Forward distance is the number of
    // sibling steps walked. null distance means no error in that direction.
    let nextDistance = null;
    {
        let n = last.nextElementSibling;
        let steps = 1;
        while (n) {
            if (n.classList && n.classList.contains("entry") &&
                !isHiddenEntry(n) &&
                n.classList.contains("entry-error")) {
                nextDistance = steps;
                break;
            }
            n = n.nextElementSibling;
            steps++;
        }
    }

    let prevDistance = null;
    {
        let p = first.previousElementSibling;
        let steps = 1;
        while (p) {
            if (p.classList && p.classList.contains("entry") &&
                !isHiddenEntry(p) &&
                p.classList.contains("entry-error")) {
                prevDistance = steps;
                break;
            }
            p = p.previousElementSibling;
            steps++;
        }
    }

    if (nextDistance !== null && (prevDistance === null || nextDistance < prevDistance)) {
        return "bottom";
    }
    return "top";
}

function isHiddenEntry(el) {
    return el.style && el.style.display === "none";
}

function renderFoldLabel(bar) {
    const entries = bar._hiddenEntries;
    const total = entries.length;
    const revealed = bar._revealedCount;
    const remaining = total - revealed;
    const label = bar._labelText || bar.querySelector(".collapsed-lines-label");
    if (!label) return;
    label.replaceChildren();

    if (remaining <= 0) return;

    // The hidden subset depends on direction: top-reveal hides entries
    // [revealed..total-1]; bottom-reveal hides entries [0..total-revealed-1].
    // Pick the still-hidden range so the line-range label tracks the
    // currently-collapsed run rather than the original full range.
    const direction = bar._revealDirection || "top";
    const hiddenStart = direction === "bottom" ? 0 : revealed;
    const hiddenEnd = direction === "bottom" ? total - revealed - 1 : total - 1;
    const firstHiddenLine = parseInt(entries[hiddenStart].querySelector(".line-number").textContent.trim(), 10);
    const lastHiddenLine = parseInt(entries[hiddenEnd].querySelector(".line-number").textContent.trim(), 10);

    // Recompute breakdown counts over the still-hidden subset so the
    // label deflates as lines are revealed.
    const counts = recomputeBreakdown(entries, hiddenStart, hiddenEnd);

    const grip = document.createElement("i");
    grip.className = "fa-solid fa-grip-lines";
    label.appendChild(grip);

    if (revealed > 0) {
        label.append(` ${revealed} of ${total} revealed · `);
    } else {
        label.append(" ");
    }
    label.append(`${remaining} hidden`);

    if (counts.warnings > 0) {
        label.append(" · ");
        const warn = document.createElement("span");
        warn.className = "level-warning";
        warn.textContent = `${counts.warnings} ⚠ warning${counts.warnings === 1 ? "" : "s"}`;
        label.appendChild(warn);
    }
    if (counts.info > 0) {
        label.append(` · ${counts.info} info`);
    }
    if (counts.mod_loads > 0) {
        label.append(` · ${counts.mod_loads} mod-load${counts.mod_loads === 1 ? "" : "s"}`);
    }
    label.append(` · ${firstHiddenLine}–${lastHiddenLine} · drag to reveal `);
    label.appendChild(grip.cloneNode());
}

/**
 * Re-derive the breakdown counts (warnings / info / mod_loads) over the
 * subset of `entries` from `lo..hi` inclusive, so the fold-bar label can
 * shrink as lines are revealed.
 *
 * @param {Element[]} entries
 * @param {number} lo
 * @param {number} hi
 */
function recomputeBreakdown(entries, lo, hi) {
    const out = { warnings: 0, info: 0, mod_loads: 0 };
    for (let i = lo; i <= hi; i++) {
        const e = entries[i];
        if (!e) continue;
        if (MOD_LOAD_REGEX.test(entryText(e).trimStart())) {
            out.mod_loads++;
        } else if (entryLevel(e) === "warning") {
            out.warnings++;
        } else {
            out.info++;
        }
    }
    return out;
}

/**
 * Reveal every entry under every fold bar and remove the bars. Used when
 * the "Show all entries" setting flips on at runtime.
 */
function unfoldAll() {
    for (const bar of document.querySelectorAll(".collapsed-lines-foldable")) {
        if (bar._hiddenEntries) {
            applyFoldReveal(bar, bar._hiddenEntries.length);
        }
        bar.remove();
    }
}

function isShowAllEntriesEnabled() {
    const cb = document.querySelector('.setting-checkbox[data-key="showAllEntries"]');
    return !!(cb && cb.checked);
}

function applyFoldReveal(bar, n) {
    const entries = bar._hiddenEntries;
    n = Math.max(0, Math.min(entries.length, n));
    if (n === bar._revealedCount) return;
    const direction = bar._revealDirection || "top";
    if (direction === "bottom") {
        // Reveal from the bottom: entries[length-n..length-1] visible,
        // entries[..length-n-1] stay hidden.
        const cutoff = entries.length - n;
        for (let i = 0; i < entries.length; i++) {
            if (i >= cutoff) entries[i].style.removeProperty("display");
            else entries[i].style.display = "none";
        }
    } else {
        // Reveal from the top: entries[0..n-1] visible, entries[n..] hidden.
        for (let i = 0; i < entries.length; i++) {
            if (i < n) entries[i].style.removeProperty("display");
            else entries[i].style.display = "none";
        }
    }
    bar._revealedCount = n;
    renderFoldLabel(bar);
}

function attachFoldDragHandler(bar) {
    let dragging = false;
    let dragStartY = 0;
    let dragStartReveal = 0;
    let didDrag = false;

    bar.addEventListener("pointerdown", (e) => {
        if (e.button !== 0) return;
        dragging = true;
        didDrag = false;
        dragStartY = e.clientY;
        dragStartReveal = bar._revealedCount;
        bar.setPointerCapture(e.pointerId);
        bar.classList.add("collapsed-lines-dragging");
        e.preventDefault();
    });

    bar.addEventListener("pointermove", (e) => {
        if (!dragging) return;
        if (!bar.isConnected) { dragging = false; return; }
        const dy = e.clientY - dragStartY;
        if (Math.abs(dy) >= DRAG_PIXEL_THRESHOLD) didDrag = true;
        const target = dragStartReveal + Math.round(dy / PIXELS_PER_LINE_DRAG);
        applyFoldReveal(bar, target);
    });

    const finish = (e) => {
        if (!dragging) return;
        if (!bar.isConnected) { dragging = false; return; }
        dragging = false;
        try { bar.releasePointerCapture(e.pointerId); } catch {}
        bar.classList.remove("collapsed-lines-dragging");

        if (!didDrag) {
            // Plain click: reveal the next chunk of lines.
            applyFoldReveal(bar, bar._revealedCount + DEFAULT_CLICK_REVEAL);
        }
        if (bar._revealedCount >= bar._hiddenEntries.length) {
            bar.remove();
        }
    };
    bar.addEventListener("pointerup", finish);
    bar.addEventListener("pointercancel", finish);
}

/* convert timestamps */
let timeElements = document.querySelectorAll('[data-time]');
for (const element of timeElements) {
    const timestamp = parseInt(element.dataset.time);
    if (isNaN(timestamp)) {
        continue;
    }
    const date = new Date(timestamp * 1000);
    element.innerHTML = date.toLocaleString();
}

/* settings */
const settingCheckboxes = document.querySelectorAll(".setting-checkbox");
settingCheckboxes.forEach(checkbox => checkbox.addEventListener("change", handleSettingChange));

let settingsChannel = null;
if (typeof BroadcastChannel !== "undefined") {
    settingsChannel = new BroadcastChannel("mc-logs-settings");
    settingsChannel.onmessage = (e) => {
        if (e.data.type === "settings-updated") {
            for (const checkbox of settingCheckboxes) {
                checkbox.checked = !!e.data.settings[checkbox.dataset.key];
                applySetting(checkbox);
            }
        }
    };
}

function handleSettingChange(e) {
    let checkbox = e.target;
    applySetting(checkbox);
    saveSettings();
    if (settingsChannel) {
        settingsChannel.postMessage({
            type: "settings-updated",
            settings: getCurrentSettings()
        });
    }
}

function applySetting(checkbox) {
    let bodyClass = checkbox.dataset.bodyClass;
    if (bodyClass) {
        if (checkbox.checked) {
            document.body.classList.add(bodyClass);
        } else {
            document.body.classList.remove(bodyClass);
        }
    }
    switch (checkbox.dataset.key) {
        case "floatingScrollbar":
            initFloatingScrollbar();
            break;
        case "showAllEntries":
            if (checkbox.checked) {
                unfoldAll();
            } else {
                unfoldAll();
                applySmartFold();
            }
            break;
    }
}

function getCurrentSettings() {
    const data = {};
    for (const checkbox of settingCheckboxes) {
        data[checkbox.dataset.key] = checkbox.checked;
    }
    return data;
}

function saveSettings() {
    const data = {};
    for (const checkbox of settingCheckboxes) {
        data[checkbox.dataset.key] = checkbox.checked;
    }
    document.cookie = "IBLOGS_SETTINGS=" + encodeURIComponent(JSON.stringify(data)) + ";path=/;expires=" + new Date(new Date().getTime() + 100 * 365 * 24 * 60 * 60 * 1000).toUTCString();
}

/* copy to clipboard */
const copyButtons = document.querySelectorAll("[data-clipboard]");
copyButtons.forEach(button => button.addEventListener("click", handleCopyButtonClick));
const doneClassName = "fa-solid fa-check";

async function handleCopyButtonClick(e) {
    const button = e.currentTarget;
    const data = button.dataset.clipboard;
    await navigator.clipboard.writeText(data);

    const iconElement = button.querySelector("i");
    if (!iconElement) {
        return;
    }
    const originalClassName = iconElement.className;
    if (originalClassName === doneClassName) {
        return;
    }
    iconElement.className = doneClassName;
    setTimeout(() => {
        iconElement.className = originalClassName;
    }, 2000);
}

/* delete button */
const deleteButton = document.querySelector(".delete-log-button");
const deleteErrorElement = document.querySelector(".delete-overlay .popover-error");
if (deleteButton) {
    deleteButton.addEventListener("click", handleDeleteButtonClick);
}

async function handleDeleteButtonClick() {
    deleteErrorElement.style.display = "none";
    const response = await fetch(window.location.href, {
        method: "DELETE",
        credentials: "include"
    });
    if (!response.ok) {
        deleteErrorElement.style.display = "block";
        deleteErrorElement.textContent = `${response.status} (${response.statusText})`;
        return;
    }
    window.location.href = "/";
}

/* floating scroll bar */
const browser = getComputedStyle(document.body)
    .getPropertyValue("--browser")
    .replaceAll(/['"]/g, '')
    .trim()
    .toLowerCase();
const floatingScrollbar = document.querySelector(".floating-scrollbar");
let logContainer = null;
if (browser === "firefox") {
    logContainer = document.querySelector(".log");
} else {
    logContainer = document.querySelector(".log-inner");
}

if (floatingScrollbar && logContainer) {
    updateFloatingScrollbarWidths();

    floatingScrollbar.addEventListener("scroll", () => {
        syncScroll(floatingScrollbar, logContainer);
    });

    logContainer.addEventListener("scroll", () => {
        syncScroll(logContainer, floatingScrollbar);
    });

    const observer = new ResizeObserver(() => {
        updateFloatingScrollbarWidths();
    });
    observer.observe(logContainer);
}

function syncScroll(source, target) {
    if (Math.abs(source.scrollLeft - target.scrollLeft) > 1) {
        target.scrollLeft = source.scrollLeft;
    }
}

function initFloatingScrollbar() {
    if (!floatingScrollbar || !logContainer) {
        return;
    }
    updateFloatingScrollbarWidths();
    syncScroll(logContainer, floatingScrollbar);
}

function updateFloatingScrollbarWidths() {
    floatingScrollbar.style.setProperty(
        "--floating-scrollbar-width",
        `${logContainer.clientWidth}px`
    );

    floatingScrollbar.style.setProperty(
        "--floating-scrollbar-content-width",
        `${logContainer.scrollWidth}px`
    );
}

/* Mod attribution click-through.
 *
 * Each `.mod-attribution[data-workshop-id]` span emitted by the codex
 * ProjectZomboidModAttributor is clickable; clicking opens the matching
 * Steam Workshop entry in a new tab. Spans without a workshop ID
 * (unknown mods) are styled the same but inert.
 */
document.addEventListener("click", (e) => {
    const badge = e.target.closest?.(".mod-attribution[data-workshop-id]");
    if (!badge) return;
    const id = badge.getAttribute("data-workshop-id");
    if (!id || !/^\d+$/.test(id)) return;
    window.open(
        "https://steamcommunity.com/sharedfiles/filedetails/?id=" + encodeURIComponent(id),
        "_blank",
        "noopener,noreferrer",
    );
});
