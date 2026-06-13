"use client";

import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import type { LogEntry } from "@/lib/types";

interface LogViewProps {
  content: string;
  entries?: LogEntry[];
  smartFold: boolean;
  noWrap: boolean;
  overflow: boolean;
  floatingScrollbar: boolean;
  problemLines?: number[];
}

interface LineRow {
  number: number;
  text: string;
}

type RenderItem =
  | { kind: "line"; line: LineRow }
  | { kind: "fold"; lines: LineRow[]; id: number };

// Levels at or below WARNING (warning, error, critical, alert, emergency) are
// always shown. info / notice / debug runs are folded away when they sit far
// enough from anything interesting. See codex-pz Log\Level for the scale.
const SEVERE_MAX_LEVEL = 4;
// Entries of context kept on each side of an interesting entry.
const CONTEXT = 2;

function buildItems(
  content: string,
  entries: LogEntry[] | undefined,
  smartFold: boolean,
  problemLines: number[]
): RenderItem[] {
  const usable = entries && entries.length > 0;

  if (!smartFold || !usable) {
    return content.split("\n").map((text, i) => ({
      kind: "line" as const,
      line: { number: i + 1, text },
    }));
  }

  const problemSet = new Set(problemLines);
  const interesting = entries!.map(
    (e) =>
      e.level_int <= SEVERE_MAX_LEVEL ||
      e.lines.some((l) => problemSet.has(l.number))
  );
  const keep = entries!.map((_, i) => {
    for (let d = -CONTEXT; d <= CONTEXT; d++) {
      const j = i + d;
      if (j >= 0 && j < interesting.length && interesting[j]) return true;
    }
    return false;
  });

  const items: RenderItem[] = [];
  let hidden: LineRow[] = [];
  let foldId = 0;
  const flush = () => {
    if (hidden.length > 0) {
      items.push({ kind: "fold", lines: hidden, id: foldId++ });
      hidden = [];
    }
  };

  entries!.forEach((entry, i) => {
    if (keep[i]) {
      flush();
      for (const l of entry.lines) {
        items.push({ kind: "line", line: { number: l.number, text: l.text } });
      }
    } else {
      for (const l of entry.lines) hidden.push({ number: l.number, text: l.text });
    }
  });
  flush();

  return items;
}

export function LogView({
  content,
  entries,
  smartFold,
  noWrap,
  overflow,
  floatingScrollbar,
  problemLines = [],
}: LogViewProps) {
  const scrollRef = useRef<HTMLDivElement>(null);
  const barRef = useRef<HTMLDivElement>(null);
  const [scrollWidth, setScrollWidth] = useState(0);
  const [clientWidth, setClientWidth] = useState(0);

  const items = useMemo(
    () => buildItems(content, entries, smartFold, problemLines),
    [content, entries, smartFold, problemLines]
  );

  const handleLineClick = useCallback((lineNum: number) => {
    window.location.hash = `L${lineNum}`;
  }, []);

  // Keep the floating scrollbar proxy in sync with the log container width.
  const showFloatingBar =
    floatingScrollbar && noWrap && scrollWidth > clientWidth + 1;

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
  }, [items, noWrap]);

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

  return (
    <div className="border-b border-[var(--border)] bg-[var(--bg-elevated)]">
      <div
        ref={scrollRef}
        onScroll={floatingScrollbar ? onContentScroll : undefined}
        className={`relative grid overflow-y-hidden py-2 ${
          overflow ? "overflow-x-visible" : "overflow-x-auto"
        }`}
        style={{
          gridTemplateColumns: noWrap ? "auto max-content" : "auto 1fr",
          fontFamily: "var(--font-mono)",
          fontSize: "clamp(0.75rem, 2vw, 0.9rem)",
          lineHeight: 1.6,
          contain: "layout style paint",
        }}
      >
        {items.map((item, idx) =>
          item.kind === "line" ? (
            <LineCells
              key={`l-${item.line.number}-${idx}`}
              line={item.line}
              noWrap={noWrap}
              onClick={handleLineClick}
            />
          ) : (
            <FoldRegion
              key={`f-${item.id}`}
              lines={item.lines}
              noWrap={noWrap}
              onLineClick={handleLineClick}
            />
          )
        )}
      </div>

      {showFloatingBar && (
        <div
          ref={barRef}
          onScroll={onBarScroll}
          aria-hidden="true"
          className="sticky bottom-0 z-10 overflow-x-auto overflow-y-hidden border-t border-[var(--border)] bg-[var(--bg-elevated)]"
          style={{ height: "var(--scrollbar-height, 8px)" }}
        >
          <div style={{ width: scrollWidth, height: 1 }} />
        </div>
      )}
    </div>
  );
}

function LineCells({
  line,
  noWrap,
  onClick,
}: {
  line: LineRow;
  noWrap: boolean;
  onClick: (n: number) => void;
}) {
  return (
    <>
      <div
        id={`L${line.number}`}
        onClick={() => onClick(line.number)}
        className="min-w-[2.75rem] scroll-mt-24 select-none border-r border-[var(--border)] px-[0.4rem] text-right text-[clamp(0.65rem,1.8vw,0.8rem)] font-medium text-[var(--text-muted)] cursor-pointer transition-colors target:bg-[var(--accent-bg)] target:text-[var(--accent)] hover:text-[var(--text)]"
      >
        {line.number}
      </div>
      <div
        className={`px-[clamp(0.4rem,1vw,0.9rem)] text-[var(--text)] ${
          noWrap ? "whitespace-pre" : "whitespace-pre-wrap break-words"
        }`}
      >
        {line.text || " "}
      </div>
    </>
  );
}

function FoldRegion({
  lines,
  noWrap,
  onLineClick,
}: {
  lines: LineRow[];
  noWrap: boolean;
  onLineClick: (n: number) => void;
}) {
  const [open, setOpen] = useState(false);

  if (open) {
    return (
      <>
        {lines.map((line, idx) => (
          <LineCells
            key={`fl-${line.number}-${idx}`}
            line={line}
            noWrap={noWrap}
            onClick={onLineClick}
          />
        ))}
        <button
          type="button"
          onClick={() => setOpen(false)}
          style={{ gridColumn: "1 / -1" }}
          className="my-1 flex w-full items-center justify-center gap-2 border-y border-dashed border-[var(--border)] bg-[var(--surface)] py-1 text-[clamp(0.7rem,1.6vw,0.75rem)] font-medium text-[var(--text-muted)] transition-colors hover:text-[var(--accent)] focus-visible:outline-2 focus-visible:outline-[var(--accent)] focus-visible:outline-offset-[-2px]"
        >
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M18 15l-6-6-6 6" /></svg>
          Collapse {lines.length.toLocaleString()} {lines.length === 1 ? "line" : "lines"}
        </button>
      </>
    );
  }

  return (
    <button
      type="button"
      onClick={() => setOpen(true)}
      style={{ gridColumn: "1 / -1" }}
      className="my-1 flex w-full items-center justify-center gap-2 border-y border-dashed border-[var(--border)] bg-[var(--surface)] py-1 font-[var(--font-mono)] text-[clamp(0.7rem,1.6vw,0.75rem)] font-medium text-[var(--text-muted)] transition-colors hover:text-[var(--accent)] focus-visible:outline-2 focus-visible:outline-[var(--accent)] focus-visible:outline-offset-[-2px]"
    >
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M6 9l6 6 6-6" /></svg>
      {lines.length.toLocaleString()} {lines.length === 1 ? "line" : "lines"} hidden
    </button>
  );
}
