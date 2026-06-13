"use client";

import { useRef, useMemo, useCallback } from "react";

interface LogViewProps {
  content: string;
}

export function LogView({ content }: LogViewProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const lines = useMemo(() => content.split("\n"), [content]);

  const handleLineClick = useCallback((lineNum: number) => {
    window.location.hash = `L${lineNum}`;
  }, []);

  return (
    <div className="border-b border-[var(--border)] bg-[var(--bg-elevated)]">
      <div
        ref={containerRef}
        className="relative grid overflow-x-auto overflow-y-hidden py-2"
        style={{
          gridTemplateColumns: "auto 1fr",
          fontFamily: "var(--font-mono)",
          fontSize: "clamp(0.75rem, 2vw, 0.9rem)",
          lineHeight: 1.6,
          contain: "layout style paint",
        }}
      >
        {lines.map((line, i) => (
          <div key={i} className="contents" id={`L${i + 1}`}>
            <div
              className="min-w-[2.75rem] select-none border-r border-[var(--border)] px-[0.4rem] text-right text-[clamp(0.65rem,1.8vw,0.8rem)] font-medium text-[var(--text-muted)] cursor-pointer hover:text-[var(--text)]"
              onClick={() => handleLineClick(i + 1)}
            >
              {i + 1}
            </div>
            <div className="break-words px-[clamp(0.4rem,1vw,0.9rem)] text-[var(--text)] whitespace-pre-wrap">
              {line || "\u00A0"}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
