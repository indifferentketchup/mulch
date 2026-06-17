"use client";

import { useEffect, useId, useRef } from "react";

interface LogSearchProps {
  query: string;
  onQueryChange: (q: string) => void;
  matchCount: number;
  currentMatch: number; // 0-based index of the active match (or -1 when no matches)
  onNext: () => void;
  onPrev: () => void;
  onClear: () => void;
}

/**
 * Cmd/Ctrl-F-style in-log search input. Scoped to one log (no page search);
 * the user-facing pitch is "find the line that broke things" in a 30k-row
 * virtualized log without leaving the page.
 *
 * Keyboard: Enter / Shift+Enter / Down / Up navigate matches, Esc clears.
 * When focused, the input swallows the keys so they do not also scroll the
 * log container.
 */
export function LogSearch({
  query,
  onQueryChange,
  matchCount,
  currentMatch,
  onNext,
  onPrev,
  onClear,
}: LogSearchProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const labelId = useId();

  // Cmd/Ctrl-F focuses the search box from anywhere on the page. The browser's
  // native Find UI is not blocked (we do not call preventDefault on the global
  // keydown), so the user keeps both options. The shortcut only fires when the
  // input is not already focused.
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (!(e.metaKey || e.ctrlKey) || e.key.toLowerCase() !== "f") return;
      if (document.activeElement === inputRef.current) return;
      e.preventDefault();
      inputRef.current?.focus();
      inputRef.current?.select();
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const hasQuery = query.length > 0;
  const hasMatches = matchCount > 0;
  // Display label: "1/12" when there are matches and a current index; "12" for
  // the count alone when matches exist but the index has not been resolved
  // (which happens on first render after typing); "no matches" when the
  // query is non-empty but nothing hit.
  const countLabel = !hasQuery
    ? null
    : hasMatches
      ? `${currentMatch + 1}/${matchCount}`
      : "no matches";

  return (
    <div
      role="search"
      aria-labelledby={labelId}
      className="flex h-8 items-center gap-1 rounded-[var(--radius-md)] border border-[var(--border)] bg-[var(--bg-elevated)] pl-2 pr-1 transition-colors focus-within:border-[var(--text-muted)]"
    >
      <label htmlFor={labelId} className="sr-only">
        Search this log
      </label>
      <svg
        width="12"
        height="12"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        aria-hidden="true"
        className="shrink-0 text-[var(--text-muted)]"
      >
        <circle cx="11" cy="11" r="7" />
        <path d="M21 21l-4.3-4.3" />
      </svg>
      <input
        ref={inputRef}
        id={labelId}
        type="text"
        inputMode="search"
        spellCheck={false}
        autoComplete="off"
        value={query}
        onChange={(e) => onQueryChange(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === "Escape") {
            e.preventDefault();
            onClear();
            inputRef.current?.blur();
            return;
          }
          if (!hasMatches) return;
          if (e.key === "Enter") {
            e.preventDefault();
            if (e.shiftKey) onPrev();
            else onNext();
          } else if (e.key === "ArrowDown") {
            e.preventDefault();
            onNext();
          } else if (e.key === "ArrowUp") {
            e.preventDefault();
            onPrev();
          }
        }}
        placeholder="search this log…"
        className="h-full w-[clamp(8rem,18vw,14rem)] bg-transparent font-[var(--font-mono)] text-[0.78rem] text-[var(--text)] outline-none placeholder:text-[var(--text-muted)] placeholder:opacity-60"
        aria-describedby={`${labelId}-count`}
      />
      <span
        id={`${labelId}-count`}
        aria-live="polite"
        className="select-none whitespace-nowrap px-1 font-[var(--font-mono)] text-[0.7rem] tabular-nums text-[var(--text-muted)]"
      >
        {countLabel ?? "\u00a0"}
      </span>
      <div className="flex items-center gap-0.5">
        <button
          type="button"
          onClick={onPrev}
          disabled={!hasMatches}
          title="Previous match (Shift+Enter)"
          aria-label="Previous match"
          className="inline-flex h-6 w-6 items-center justify-center rounded-[var(--radius-xs)] text-[var(--text-muted)] transition-colors hover:bg-[var(--bg-surface)] hover:text-[var(--text)] disabled:cursor-not-allowed disabled:opacity-40"
        >
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden="true"><path d="M18 15l-6-6-6 6" /></svg>
        </button>
        <button
          type="button"
          onClick={onNext}
          disabled={!hasMatches}
          title="Next match (Enter)"
          aria-label="Next match"
          className="inline-flex h-6 w-6 items-center justify-center rounded-[var(--radius-xs)] text-[var(--text-muted)] transition-colors hover:bg-[var(--bg-surface)] hover:text-[var(--text)] disabled:cursor-not-allowed disabled:opacity-40"
        >
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
        </button>
        <button
          type="button"
          onClick={onClear}
          disabled={!hasQuery}
          title="Clear search (Esc)"
          aria-label="Clear search"
          className="inline-flex h-6 w-6 items-center justify-center rounded-[var(--radius-xs)] text-[var(--text-muted)] transition-colors hover:bg-[var(--bg-surface)] hover:text-[var(--text)] disabled:cursor-not-allowed disabled:opacity-40"
        >
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden="true"><path d="M6 6l12 12M18 6l-12 12" /></svg>
        </button>
      </div>
    </div>
  );
}
