"use client";

import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { useVirtualizer } from "@tanstack/react-virtual";
import type { LogEntry } from "@/lib/types";
import { severityVar, severityBgVar } from "@/lib/severity";

interface LogViewProps {
  content: string;
  entries?: LogEntry[];
  noWrap: boolean;
  floatingScrollbar: boolean;
  problemLines?: number[];
  severityByLine?: Record<number, string>;
  // Active-line state lives in LogShell so deep links and the problem panel can
  // drive it. The whole owning entry highlights when activeLine is in its range.
  activeLine: number | null;
  onSetActiveLine: (line: number) => void;
  // Live in-log search. LogShell owns the query and the prev/next step
  // counter; this view computes the actual matches and reports the
  // current-match line back so the search box can render "1/12".
  searchQuery: string;
  searchStep: number;
  onSearchState: (state: {
    count: number;
    currentIndex: number;
    currentLine: number | null;
  }) => void;
}

// Per-line render metadata. Built once from the raw content plus the analyzer's
// compact entries (which carry only level + first/last line numbers).
interface LineMeta {
  number: number;
  text: string;
  errorEntry: boolean;
  continuation: boolean; // a non-first line of a multiline entry (stack frame)
  entryFirst: number;
  entryLast: number;
  levelInt: number;
}

// codex-pz Log\Level: EMERGENCY 0 .. ERROR 3, WARNING 4, NOTICE 5, INFO 6, DEBUG 7.
const ERROR_MAX_LEVEL = 3;
const WARNING_LEVEL = 4;
const NEUTRAL_LEVEL = 6; // info: plain body text

// Build a flat per-line model for the ENTIRE log (no folding). Each content
// line is tagged with its owning entry's level + line range so coloring and the
// entry-scoped active highlight work; lines outside any entry render neutral.
function buildLines(content: string, entries: LogEntry[] | undefined): LineMeta[] {
  const contentLines = content.split("\n");
  const n = contentLines.length;
  const out: (LineMeta | undefined)[] = new Array(n);

  if (entries && entries.length > 0) {
    for (const e of entries) {
      const legacy = e as unknown as { lines?: { number: number }[] };
      const first = e.first ?? legacy.lines?.[0]?.number ?? 0;
      const last =
        e.last ?? legacy.lines?.[legacy.lines.length - 1]?.number ?? first;
      const errorEntry = e.level_int <= ERROR_MAX_LEVEL;
      for (let num = first; num <= last; num++) {
        if (num < 1 || num > n) continue;
        out[num - 1] = {
          number: num,
          text: contentLines[num - 1] ?? "",
          errorEntry,
          continuation: num > first,
          entryFirst: first,
          entryLast: last,
          levelInt: e.level_int,
        };
      }
    }
  }

  for (let i = 0; i < n; i++) {
    if (!out[i]) {
      out[i] = {
        number: i + 1,
        text: contentLines[i] ?? "",
        errorEntry: false,
        continuation: false,
        entryFirst: i + 1,
        entryLast: i + 1,
        levelInt: NEUTRAL_LEVEL,
      };
    }
  }
  return out as LineMeta[];
}

function isEntryActive(line: LineMeta, activeLine: number | null): boolean {
  return (
    activeLine != null &&
    activeLine >= line.entryFirst &&
    activeLine <= line.entryLast
  );
}

// Body text color precedence: stack continuation -> stack tint; ERROR -> error;
// WARNING -> warning; else normal body text.
function levelTextColor(line: LineMeta): string {
  if (line.continuation) return "var(--level-stack)";
  if (line.levelInt <= ERROR_MAX_LEVEL) return "var(--level-error)";
  if (line.levelInt === WARNING_LEVEL) return "var(--level-warning)";
  return "var(--text)";
}

export function LogView({
  content,
  entries,
  noWrap,
  floatingScrollbar,
  problemLines = [],
  severityByLine = {},
  activeLine,
  onSetActiveLine,
  searchQuery,
  searchStep,
  onSearchState,
}: LogViewProps) {
  const scrollRef = useRef<HTMLDivElement>(null);
  const barRef = useRef<HTMLDivElement>(null);
  const [scrollWidth, setScrollWidth] = useState(0);
  const [clientWidth, setClientWidth] = useState(0);

  const lines = useMemo(() => buildLines(content, entries), [content, entries]);
  const problemSet = useMemo(() => new Set(problemLines), [problemLines]);

  // Live search: a flat list of {line, start, end} hits across all lines.
  // The order matches the log order so prev/next walks forward and back
  // naturally. We pre-lowercase the query once per query change. For a
  // 32k-line log this is still well under 1ms; no need to debounce.
  const trimmedQuery = searchQuery.trim();
  const matches = useMemo(() => {
    if (!trimmedQuery) return [] as { line: number; start: number; end: number }[];
    const needle = trimmedQuery.toLowerCase();
    const out: { line: number; start: number; end: number }[] = [];
    for (const l of lines) {
      const hay = l.text.toLowerCase();
      let from = 0;
      while (from <= hay.length) {
        const idx = hay.indexOf(needle, from);
        if (idx < 0) break;
        out.push({ line: l.number, start: idx, end: idx + needle.length });
        // empty-needle guard: indexOf("", from) === from, so advance by 1.
        from = needle.length === 0 ? idx + 1 : idx + Math.max(1, needle.length);
      }
    }
    return out;
  }, [lines, trimmedQuery]);

  // Map of "line -> ordered match offsets in that line" so the LineRow
  // renderer can wrap the matches in <mark> without re-scanning.
  const matchesByLine = useMemo(() => {
    const map = new Map<number, { start: number; end: number }[]>();
    for (const m of matches) {
      const arr = map.get(m.line);
      if (arr) arr.push({ start: m.start, end: m.end });
      else map.set(m.line, [{ start: m.start, end: m.end }]);
    }
    return map;
  }, [matches]);

  // The "current match" the virtualizer is centered on. searchStep is the
  // user-driven counter; we wrap it into [0, count). A searchStep change
  // is the only signal that the user pressed Next/Prev, so we resolve the
  // desired match here and report the resulting index + line to LogShell.
  // We seed the current match to the FIRST hit when the query first
  // produces matches, so the input's "1/12" is never blank right after typing.
  const resolvedCurrentIndex = useMemo(() => {
    if (matches.length === 0) return -1;
    // Normalize step into a 0..count-1 index. Using modulo lets a quick
    // burst of Next presses wrap around cleanly without negative-index bugs.
    return ((searchStep % matches.length) + matches.length) % matches.length;
  }, [matches.length, searchStep]);

  const currentMatchLine = useMemo(() => {
    if (resolvedCurrentIndex < 0) return null;
    return matches[resolvedCurrentIndex]?.line ?? null;
  }, [matches, resolvedCurrentIndex]);

  // Report state up so LogShell can render the count + "1/12" label. We
  // only emit when the relevant slice changes; deep-equal-by-primitive.
  const lastReportedRef = useRef({ count: -1, currentIndex: -1, currentLine: -1 });
  useEffect(() => {
    const last = lastReportedRef.current;
    if (
      last.count === matches.length &&
      last.currentIndex === resolvedCurrentIndex &&
      last.currentLine === (currentMatchLine ?? -1)
    ) {
      return;
    }
    lastReportedRef.current = {
      count: matches.length,
      currentIndex: resolvedCurrentIndex,
      currentLine: currentMatchLine ?? -1,
    };
    onSearchState({
      count: matches.length,
      currentIndex: resolvedCurrentIndex,
      currentLine: currentMatchLine,
    });
  }, [matches.length, resolvedCurrentIndex, currentMatchLine, onSearchState]);

  const virtualizer = useVirtualizer({
    count: lines.length,
    getScrollElement: () => scrollRef.current,
    estimateSize: () => 26,
    overscan: 14,
    // Keep a stable key per line so measurement caches survive re-renders.
    getItemKey: (i) => lines[i].number,
  });

  const handleLineClick = useCallback(
    (lineNum: number) => {
      onSetActiveLine(lineNum);
      history.replaceState(null, "", `#L${lineNum}`);
    },
    [onSetActiveLine]
  );

  // Scroll the active line into view whenever it changes (deep link on mount,
  // problem-panel jump, or gutter click). The whole log is mounted virtually,
  // so we scroll by index rather than relying on a DOM anchor that may be
  // outside the current window.
  useEffect(() => {
    if (activeLine == null) return;
    const idx = activeLine - 1;
    if (idx < 0 || idx >= lines.length) return;
    virtualizer.scrollToIndex(idx, { align: "center" });
  }, [activeLine, lines.length, virtualizer]);

  // When the current search match changes (Next/Prev pressed), scroll the
  // virtualizer to its line. We deliberately use the match line, not the
  // active line, so the two highlights stay decoupled: a search match and a
  // problem-panel jump can target different lines and both still work.
  useEffect(() => {
    if (currentMatchLine == null) return;
    const idx = currentMatchLine - 1;
    if (idx < 0 || idx >= lines.length) return;
    virtualizer.scrollToIndex(idx, { align: "center" });
  }, [currentMatchLine, lines.length, virtualizer]);

  const showFloatingBar =
    floatingScrollbar && scrollWidth > clientWidth + 1;

  useEffect(() => {
    const el = scrollRef.current;
    if (!el) return;
    const measure = () => {
      setScrollWidth(el.scrollWidth);
      setClientWidth(el.clientWidth);
    };
    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(el);
    window.addEventListener("resize", measure);
    return () => {
      ro.disconnect();
      window.removeEventListener("resize", measure);
    };
  }, [noWrap, lines.length]);

  const onContentScroll = useCallback(() => {
    if (barRef.current && scrollRef.current) {
      barRef.current.scrollLeft = scrollRef.current.scrollLeft;
    }
  }, []);
  const onBarScroll = useCallback(() => {
    if (barRef.current && scrollRef.current) {
      scrollRef.current.scrollLeft = barRef.current.scrollLeft;
    }
  }, []);

  const items = virtualizer.getVirtualItems();
  const totalSize = virtualizer.getTotalSize();
  const paddingTop = items.length > 0 ? items[0].start : 0;
  const paddingBottom =
    items.length > 0 ? totalSize - items[items.length - 1].end : 0;

  return (
    <div className="bg-[var(--bg-inset)]">
      <div
        ref={scrollRef}
        onScroll={floatingScrollbar ? onContentScroll : undefined}
        className="overflow-auto"
        style={{
          // Use min-height + max-height (not just max-height): the virtualizer
          // needs a non-zero measured scroll element to render any items, but
          // a fixed height would force very short logs into a huge empty box.
          // 18vh keeps a short log looking short while giving the virtualizer
          // something to measure on first paint.
          minHeight: "18vh",
          maxHeight: "75vh",
          fontFamily: "var(--font-mono)",
          fontSize: "clamp(0.75rem, 2vw, 0.875rem)",
          lineHeight: 1.7,
          // `layout paint` (not `strict`) avoids the size-containment that would
          // freeze the scroll element's size at 0 before the inner spacer has
          // been measured, while still limiting reflow/paint scope.
          contain: "layout paint",
        }}
      >
        <div
          style={{
            paddingTop,
            paddingBottom,
            ...(noWrap ? { width: "max-content", minWidth: "100%" } : {}),
          }}
        >
          {items.map((vi) => {
            const line = lines[vi.index];
            const isCurrentMatch = currentMatchLine === line.number;
            const lineMatches = matchesByLine.get(line.number);
            return (
              <LineRow
                key={vi.key}
                dataIndex={vi.index}
                measureRef={virtualizer.measureElement}
                line={line}
                noWrap={noWrap}
                severity={severityByLine[line.number]}
                isProblem={problemSet.has(line.number)}
                active={isEntryActive(line, activeLine)}
                isCurrentSearchMatch={isCurrentMatch}
                searchMatches={lineMatches}
                onClick={handleLineClick}
              />
            );
          })}
        </div>
      </div>

      {showFloatingBar && (
        <div
          ref={barRef}
          onScroll={onBarScroll}
          aria-hidden="true"
          className="sticky bottom-0 z-10 overflow-x-auto overflow-y-hidden border-t border-[var(--border)] bg-[var(--bg-inset)]"
          style={{ height: "var(--scrollbar-height, 10px)" }}
        >
          <div style={{ width: scrollWidth, height: 1 }} />
        </div>
      )}
    </div>
  );
}

function LineRow({
  dataIndex,
  measureRef,
  line,
  noWrap,
  severity,
  isProblem,
  active,
  isCurrentSearchMatch,
  searchMatches,
  onClick,
}: {
  dataIndex: number;
  measureRef: (el: HTMLElement | null) => void;
  line: LineMeta;
  noWrap: boolean;
  severity?: string;
  isProblem: boolean;
  active: boolean;
  isCurrentSearchMatch: boolean;
  searchMatches?: { start: number; end: number }[];
  onClick: (n: number) => void;
}) {
  // Background precedence: search match on the current match > active entry
  // highlight > problem tint > error-entry wash. The current-search-match
  // tint is a strong cyan/info so the user can find the match in a wide
  // scroll without confusing it with the entry-active accent.
  const cellBg = isCurrentSearchMatch
    ? "color-mix(in srgb, var(--info) 35%, var(--bg-inset))"
    : active
      ? line.errorEntry
        ? "color-mix(in srgb, var(--accent) 22%, var(--bg-inset))"
        : "color-mix(in srgb, var(--accent) 15%, var(--bg-inset))"
      : isProblem && severity
        ? severityBgVar(severity)
        : line.errorEntry
          ? "var(--error-bg)"
          : undefined;

  const gutterColor = isCurrentSearchMatch
    ? "var(--info)"
    : active
      ? "var(--accent)"
      : isProblem && severity
        ? severityVar(severity)
        : "var(--text-muted)";

  return (
    <div ref={measureRef} data-index={dataIndex} className="flex w-full">
      <div
        id={`L${line.number}`}
        onClick={() => onClick(line.number)}
        title={isProblem && severity ? `${severity} on line ${line.number}` : undefined}
        className={`sticky left-0 z-[1] min-w-[3.25rem] shrink-0 cursor-pointer select-none border-r border-[var(--border)] px-[0.55rem] text-right tabular-nums transition-colors text-[clamp(0.65rem,1.8vw,0.78rem)] hover:text-[var(--text)] ${
          isProblem || active || isCurrentSearchMatch ? "font-semibold" : "font-medium"
        }`}
        style={{
          backgroundColor: cellBg ?? "var(--bg-inset)",
          color: gutterColor,
        }}
      >
        {line.number}
      </div>
      <div
        onClick={() => onClick(line.number)}
        className={`flex-1 px-[clamp(0.55rem,1.2vw,1rem)] ${
          noWrap ? "whitespace-pre" : "whitespace-pre-wrap break-words"
        }`}
        style={{ backgroundColor: cellBg, color: levelTextColor(line) }}
      >
        {searchMatches && searchMatches.length > 0 ? (
          <HighlightedText text={line.text || " "} matches={searchMatches} current={isCurrentSearchMatch} />
        ) : (
          line.text || " "
        )}
      </div>
    </div>
  );
}

/**
 * Render `text` with each `{start,end}` range in `matches` wrapped in <mark>.
 * `current` flips the match's visual style so the "current" match stands out
 * from the rest. The DOM order matches `text` and the matches are pre-sorted
 * by start; this is a non-mutating split.
 */
function HighlightedText({
  text,
  matches,
  current,
}: {
  text: string;
  matches: { start: number; end: number }[];
  current: boolean;
}) {
  const out: ReactNode[] = [];
  let cursor = 0;
  for (let i = 0; i < matches.length; i++) {
    const m = matches[i];
    if (m.start > cursor) out.push(text.slice(cursor, m.start));
    out.push(
      <mark
        key={i}
        data-current={current ? "true" : undefined}
        className="rounded-[2px] bg-[color-mix(in_srgb,var(--info)_35%,transparent)] px-[1px] text-[var(--text)]"
        style={current ? { backgroundColor: "var(--info)", color: "var(--bg)" } : undefined}
      >
        {text.slice(m.start, m.end)}
      </mark>
    );
    cursor = m.end;
  }
  if (cursor < text.length) out.push(text.slice(cursor));
  return <>{out}</>;
}
