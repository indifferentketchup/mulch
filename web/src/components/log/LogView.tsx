"use client";

import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import type { LogEntry } from "@/lib/types";
import { severityVar, severityBgVar } from "@/lib/severity";

interface LogViewProps {
  content: string;
  entries?: LogEntry[];
  smartFold: boolean;
  noWrap: boolean;
  overflow: boolean;
  floatingScrollbar: boolean;
  problemLines?: number[];
  severityByLine?: Record<number, string>;
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
  severityByLine = {},
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
    <div className="bg-[var(--bg-inset)]">
      <div
        ref={scrollRef}
        onScroll={floatingScrollbar ? onContentScroll : undefined}
        className={`relative grid overflow-y-hidden py-2 ${
          overflow ? "overflow-x-visible" : "overflow-x-auto"
        }`}
        style={{
          gridTemplateColumns: noWrap ? "auto max-content" : "auto minmax(0, 1fr)",
          fontFamily: "var(--font-mono)",
          fontSize: "clamp(0.75rem, 2vw, 0.875rem)",
          lineHeight: 1.7,
          contain: "layout style paint",
        }}
      >
        {items.map((item, idx) =>
          item.kind === "line" ? (
            <LineCells
              key={`l-${item.line.number}-${idx}`}
              line={item.line}
              noWrap={noWrap}
              severity={severityByLine[item.line.number]}
              onClick={handleLineClick}
            />
          ) : (
            <FoldRegion
              key={`f-${item.id}`}
              lines={item.lines}
              noWrap={noWrap}
              severityByLine={severityByLine}
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
          className="sticky bottom-0 z-10 overflow-x-auto overflow-y-hidden border-t border-[var(--border)] bg-[var(--bg-inset)]"
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
  severity,
  onClick,
}: {
  line: LineRow;
  noWrap: boolean;
  severity?: string;
  onClick: (n: number) => void;
}) {
  const isProblem = !!severity;
  return (
    <>
      <div
        id={`L${line.number}`}
        onClick={() => onClick(line.number)}
        title={isProblem ? `${severity} on line ${line.number}` : undefined}
        className={`min-w-[3rem] scroll-mt-24 select-none border-r border-[var(--border)] px-[0.55rem] text-right text-[clamp(0.65rem,1.8vw,0.78rem)] tabular-nums cursor-pointer transition-colors target:bg-[var(--info-bg)] hover:text-[var(--text)] ${
          isProblem ? "font-semibold" : "font-medium text-[var(--text-muted)]"
        }`}
        style={
          isProblem
            ? { backgroundColor: severityBgVar(severity), color: severityVar(severity) }
            : undefined
        }
      >
        {line.number}
      </div>
      <div
        className={`px-[clamp(0.55rem,1.2vw,1rem)] text-[var(--text)] ${
          noWrap ? "whitespace-pre" : "whitespace-pre-wrap break-words"
        }`}
      >
        {line.text || " "}
      </div>
    </>
  );
}

function FoldRegion({
  lines,
  noWrap,
  severityByLine,
  onLineClick,
}: {
  lines: LineRow[];
  noWrap: boolean;
  severityByLine: Record<number, string>;
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
            severity={severityByLine[line.number]}
            onClick={onLineClick}
          />
        ))}
        <button
          type="button"
          onClick={() => setOpen(false)}
          style={{ gridColumn: "1 / -1" }}
          className="my-1 flex w-full items-center justify-center gap-2 border-y border-dashed border-[var(--border)] bg-[var(--bg-surface)] py-1 font-[var(--font-mono)] text-[clamp(0.68rem,1.6vw,0.74rem)] font-medium text-[var(--text-muted)] transition-colors hover:text-[var(--info)] focus-visible:outline-2 focus-visible:outline-[var(--info)] focus-visible:outline-offset-[-2px]"
        >
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M18 15l-6-6-6 6" /></svg>
          collapse {lines.length.toLocaleString()} {lines.length === 1 ? "line" : "lines"}
        </button>
      </>
    );
  }

  return (
    <button
      type="button"
      onClick={() => setOpen(true)}
      style={{ gridColumn: "1 / -1" }}
      className="my-1 flex w-full items-center justify-center gap-2 border-y border-dashed border-[var(--border)] bg-[var(--bg-surface)] py-1 font-[var(--font-mono)] text-[clamp(0.68rem,1.6vw,0.74rem)] font-medium text-[var(--text-muted)] transition-colors hover:text-[var(--info)] focus-visible:outline-2 focus-visible:outline-[var(--info)] focus-visible:outline-offset-[-2px]"
    >
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M6 9l6 6 6-6" /></svg>
      {lines.length.toLocaleString()} {lines.length === 1 ? "line" : "lines"} hidden
    </button>
  );
}
