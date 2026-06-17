"use client";

import { useId, useMemo, useState } from "react";
import type { ProblemData } from "@/lib/types";
import {
  severityVar,
  severityBgVar,
  severityBorderVar,
  severityRank,
} from "@/lib/severity";

interface ProblemPanelProps {
  problems: ProblemData[];
  noiseCount: number;
  logId: string;
  hideEngineNoise: boolean;
  onJumpToLine?: (line: number) => void;
}

type ProblemListItem =
  | { type: "problem"; problem: ProblemData }
  | {
      type: "group";
      key: string;
      title: string;
      severity: string;
      totalRows: number;
      totalOccurrences: number;
      problems: ProblemData[];
    };

function collapsedGroupKey(problem: ProblemData): string | null {
  const message = problem.message.trim();
  if (/^missing /i.test(message) || /^invalid sprite/i.test(message)) {
    return "missing-assets";
  }
  if (/^unknown item param/i.test(message)) {
    return "unknown-item-params";
  }
  if ((problem.gated || problem.severity === "Low" || problem.severity === "Noise") && problem.count >= 25) {
    return "repeated-warning";
  }
  return null;
}

function collapsedGroupTitle(key: string): string {
  switch (key) {
    case "missing-assets":
      return "Missing assets";
    case "unknown-item-params":
      return "Unknown item params";
    case "repeated-warning":
      return "Repeated warnings";
    default:
      return "Collapsed warnings";
  }
}

function buildProblemList(problems: ProblemData[]): ProblemListItem[] {
  const items: ProblemListItem[] = [];
  const grouped = new Map<string, ProblemData[]>();

  for (const problem of problems) {
    const key = collapsedGroupKey(problem);
    if (!key) {
      items.push({ type: "problem", problem });
      continue;
    }
    const group = grouped.get(key) ?? [];
    group.push(problem);
    grouped.set(key, group);
  }

  for (const [key, groupedProblems] of grouped) {
    const severity = groupedProblems.reduce(
      (acc, problem) =>
        severityRank(problem.severity) < severityRank(acc) ? problem.severity : acc,
      groupedProblems[0]?.severity ?? "Low"
    );
    const totalOccurrences = groupedProblems.reduce(
      (sum, problem) => sum + Math.max(problem.count, 1),
      0
    );
    items.push({
      type: "group",
      key,
      title: collapsedGroupTitle(key),
      severity,
      totalRows: groupedProblems.length,
      totalOccurrences,
      problems: groupedProblems,
    });
  }

  return items.sort((a, b) => {
    const aSeverity = a.type === "problem" ? a.problem.severity : a.severity;
    const bSeverity = b.type === "problem" ? b.problem.severity : b.severity;
    const bySeverity = severityRank(aSeverity) - severityRank(bSeverity);
    if (bySeverity !== 0) {
      return bySeverity;
    }
    const aRank = a.type === "problem" ? a.problem.rank : Math.min(...a.problems.map((p) => p.rank));
    const bRank = b.type === "problem" ? b.problem.rank : Math.min(...b.problems.map((p) => p.rank));
    return aRank - bRank;
  });
}

export function ProblemPanel({
  problems,
  noiseCount,
  logId,
  hideEngineNoise,
  onJumpToLine,
}: ProblemPanelProps) {
  const visibleProblems = hideEngineNoise
    ? problems.filter((p) => !p.gated)
    : problems;
  const hiddenCount = hideEngineNoise ? noiseCount : 0;
  const items = useMemo(() => buildProblemList(visibleProblems), [visibleProblems]);

  if (problems.length === 0) return null;

  // Worst severity present drives the count badge color (critical = red top).
  const worst = visibleProblems.reduce(
    (acc, p) => (severityRank(p.severity) < severityRank(acc) ? p.severity : acc),
    "Low"
  );

  return (
    <div className="mt-[clamp(0.85rem,2vw,1.1rem)] border-t border-[var(--border)] pt-[clamp(0.85rem,2vw,1.1rem)]">
      <div className="mb-3 flex items-center gap-2.5">
        <span
          className="inline-flex min-w-[1.5rem] items-center justify-center rounded-[var(--radius-xs)] px-1.5 py-0.5 font-[var(--font-mono)] text-[0.8rem] font-semibold tabular-nums"
          style={{ backgroundColor: severityBgVar(worst), color: severityVar(worst) }}
        >
          {visibleProblems.length}
        </span>
        <span className="font-[var(--font-mono)] text-[clamp(0.85rem,2vw,0.92rem)] font-medium text-[var(--text)]">
          {visibleProblems.length === 1 ? "problem detected" : "problems detected"}
          {hiddenCount > 0 && (
            <span className="ml-1.5 text-[var(--text-muted)]">
              ({hiddenCount} noise hidden)
            </span>
          )}
        </span>
      </div>

      <div className="flex flex-col gap-2.5">
        {items.map((item, i) =>
          item.type === "problem" ? (
            <ProblemRow
              key={`problem-${i}`}
              problem={item.problem}
              logId={logId}
              onJumpToLine={onJumpToLine}
            />
          ) : (
            <CollapsedProblemGroup
              key={`group-${item.key}`}
              group={item}
              logId={logId}
              onJumpToLine={onJumpToLine}
            />
          )
        )}
      </div>
    </div>
  );
}

/** Split a solution message string on single-quoted tokens and bold the quoted words. */
function BoldQuoted({ text }: { text: string }) {
  const parts = text.split(/('(?:[^']+)')/g);
  return (
    <>
      {parts.map((part, i) => {
        if (/^'[^']+'$/.test(part)) {
          return (
            <strong key={i} className="font-semibold text-[var(--text)]">
              {part}
            </strong>
          );
        }
        return <span key={i}>{part}</span>;
      })}
    </>
  );
}

function ProblemRow({
  problem,
  logId,
  onJumpToLine,
}: {
  problem: ProblemData;
  logId: string;
  onJumpToLine?: (line: number) => void;
}) {
  const [open, setOpen] = useState(false);
  const detailId = useId();

  const color = severityVar(problem.severity);
  const bg = severityBgVar(problem.severity);
  const border = severityBorderVar(problem.severity);

  const hasDetail =
    !!problem.stack_trace ||
    problem.solutions.length > 0 ||
    !!problem.mod ||
    problem.message.length > 88;

  function handleLineChipClick(
    e: React.MouseEvent,
    line: number
  ) {
    e.preventDefault();
    e.stopPropagation();
    if (onJumpToLine) {
      onJumpToLine(line);
    } else {
      window.location.assign(`/${logId}#L${line}`);
    }
  }

  return (
    <div
      className="overflow-hidden rounded-[var(--radius-md)] border transition-colors duration-150"
      style={{
        borderColor: open ? color : border,
        // Faint severity wash so a HIGH reads orange at a glance without becoming a solid alarm bar.
        backgroundColor: `color-mix(in srgb, ${bg} 55%, var(--bg-elevated))`,
      }}
    >
      {/* Row header: severity pill + message + count + line chip + chevron.
          The line chip is a sibling button (not nested) so we use a flex row wrapper. */}
      <div className="flex items-stretch">
        <button
          type="button"
          disabled={!hasDetail}
          aria-expanded={hasDetail ? open : undefined}
          aria-controls={hasDetail ? detailId : undefined}
          onClick={() => hasDetail && setOpen((v) => !v)}
          className={`group flex min-w-0 flex-1 items-stretch text-left outline-none focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[var(--info)] ${
            hasDetail ? "cursor-pointer" : "cursor-default"
          }`}
        >
          <span
            className="flex shrink-0 items-center gap-1.5 self-stretch whitespace-nowrap px-[clamp(0.6rem,1.5vw,0.75rem)] py-[clamp(0.5rem,1.3vw,0.6rem)] font-[var(--font-mono)] text-[0.72rem] font-semibold uppercase tracking-[0.04em]"
            style={{ backgroundColor: bg, color }}
          >
            <SeverityIcon severity={problem.severity} isNoise={problem.gated} />
            {problem.severity}
          </span>
          <span
            className={`flex flex-1 items-center px-[clamp(0.65rem,1.5vw,0.85rem)] py-[clamp(0.5rem,1.3vw,0.6rem)] font-[var(--font-sans)] text-[clamp(0.85rem,2vw,0.9rem)] text-[var(--text)] [word-break:break-word] ${
              open ? "" : "line-clamp-2"
            }`}
          >
            {problem.message}
          </span>
          {problem.count > 1 && (
            <span className="flex items-center whitespace-nowrap px-2 font-[var(--font-mono)] text-[0.72rem] font-semibold tabular-nums text-[var(--text)]">
              &times;{problem.count.toLocaleString()}
            </span>
          )}
          {hasDetail && (
            <span className="flex items-center pr-3 pl-1 text-[var(--text-muted)]">
              <svg
                width="13"
                height="13"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                aria-hidden="true"
                className={`transition-transform duration-200 ${open ? "rotate-180" : ""}`}
              >
                <path d="M6 9l6 6 6-6" />
              </svg>
            </span>
          )}
        </button>
        {problem.entry_line != null && (
          <button
            type="button"
            onClick={(e) => handleLineChipClick(e, problem.entry_line as number)}
            title={`Jump to line ${problem.entry_line}`}
            className="flex items-center whitespace-nowrap self-stretch border-l border-[var(--border)] px-[clamp(0.6rem,1.5vw,0.8rem)] font-[var(--font-mono)] text-[0.72rem] font-medium tabular-nums text-[var(--text-muted)] transition-colors hover:bg-[var(--surface)] hover:text-[var(--text)] max-[800px]:hidden"
          >
            L{problem.entry_line}
          </button>
        )}
      </div>

      {hasDetail && open && (
        <div
          id={detailId}
          className="flex flex-col gap-3 border-t px-[clamp(0.7rem,1.6vw,0.95rem)] py-[clamp(0.6rem,1.4vw,0.8rem)] motion-safe:animate-[fadeUp_0.25s_var(--ease-out)_both]"
          style={{ borderColor: border }}
        >
          <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
            {problem.mod && (
              <a
                href={
                  problem.mod.workshop_id
                    ? `https://steamcommunity.com/sharedfiles/filedetails/?id=${problem.mod.workshop_id}`
                    : undefined
                }
                target={problem.mod.workshop_id ? "_blank" : undefined}
                rel="noopener noreferrer"
                className={`inline-flex items-center gap-1.5 rounded-[var(--radius-pill)] border bg-[var(--bg-elevated)] px-2.5 py-1 font-[var(--font-mono)] text-[0.72rem] font-medium text-[var(--text-muted)] transition-colors duration-150 hover:border-[var(--info)] hover:text-[var(--text)] ${
                  problem.mod.is_direct
                    ? "border-[var(--border)]"
                    : "border-dashed border-[var(--border)]"
                }`}
                title={
                  problem.mod.is_direct
                    ? "Directly attributed mod"
                    : "Inferred mod (lower confidence)"
                }
              >
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
                {problem.mod.name}
                {!problem.mod.is_direct && (
                  <span className="text-[var(--text-muted)]">inferred</span>
                )}
              </a>
            )}
            {problem.entry_line != null && (
              <button
                type="button"
                onClick={(e) => handleLineChipClick(e, problem.entry_line as number)}
                className="inline-flex items-center gap-1.5 font-[var(--font-mono)] text-[0.74rem] font-medium text-[var(--text-muted)] transition-colors hover:text-[var(--info)]"
              >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M12 5v14M19 12l-7 7-7-7" /></svg>
                jump to line {problem.entry_line}
              </button>
            )}
          </div>

          {problem.message.length > 88 && (
            <p className="font-[var(--font-sans)] text-[clamp(0.85rem,2vw,0.9rem)] leading-relaxed text-[var(--text)] [word-break:break-word]">
              {problem.message}
            </p>
          )}

          {problem.stack_trace && (
            <pre className="overflow-x-auto whitespace-pre rounded-[var(--radius-sm)] border border-[var(--border)] bg-[var(--bg-inset)] p-3 font-[var(--font-mono)] text-[0.76rem] leading-relaxed text-[var(--text)]">
              {problem.stack_trace}
            </pre>
          )}

          {problem.solutions.length > 0 && (
            <div className="flex flex-col gap-1.5">
              <span className="font-[var(--font-mono)] text-[0.72rem] font-semibold uppercase tracking-[0.04em] text-[var(--text-muted)]">
                {problem.solutions.length === 1 ? "Solution" : "Solutions"}
              </span>
              {problem.solutions.map((sol, j) => (
                <div key={j} className="flex items-baseline gap-2 font-[var(--font-sans)] text-[clamp(0.82rem,1.8vw,0.88rem)]">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" className="mt-0.5 shrink-0 text-[var(--info)]" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                  <span className="text-[var(--text)]">
                    <BoldQuoted text={sol.message} />
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function CollapsedProblemGroup({
  group,
  logId,
  onJumpToLine,
}: {
  group: Extract<ProblemListItem, { type: "group" }>;
  logId: string;
  onJumpToLine?: (line: number) => void;
}) {
  const [open, setOpen] = useState(false);
  const detailId = useId();
  const color = severityVar(group.severity);
  const bg = severityBgVar(group.severity);
  const border = severityBorderVar(group.severity);

  return (
    <div
      className="overflow-hidden rounded-[var(--radius-md)] border transition-colors duration-150"
      style={{
        borderColor: open ? color : border,
        backgroundColor: `color-mix(in srgb, ${bg} 40%, var(--bg-elevated))`,
      }}
    >
      <button
        type="button"
        aria-expanded={open}
        aria-controls={detailId}
        onClick={() => setOpen((value) => !value)}
        className="flex w-full items-stretch text-left outline-none transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[var(--info)]"
      >
        <span
          className="flex shrink-0 items-center gap-1.5 self-stretch whitespace-nowrap px-[clamp(0.6rem,1.5vw,0.75rem)] py-[clamp(0.5rem,1.3vw,0.6rem)] font-[var(--font-mono)] text-[0.72rem] font-semibold uppercase tracking-[0.04em]"
          style={{ backgroundColor: bg, color }}
        >
          <SeverityIcon severity={group.severity} isNoise={false} />
          {group.severity}
        </span>
        <span className="flex min-w-0 flex-1 items-center gap-3 px-[clamp(0.65rem,1.5vw,0.85rem)] py-[clamp(0.5rem,1.3vw,0.6rem)]">
          <span className="min-w-0 flex-1 font-[var(--font-sans)] text-[clamp(0.85rem,2vw,0.9rem)] text-[var(--text)]">
            {group.title}
          </span>
          <span className="whitespace-nowrap font-[var(--font-mono)] text-[0.72rem] text-[var(--text-muted)]">
            {group.totalRows.toLocaleString()} rows
          </span>
          <span className="whitespace-nowrap font-[var(--font-mono)] text-[0.72rem] font-semibold tabular-nums text-[var(--text)]">
            &times;{group.totalOccurrences.toLocaleString()}
          </span>
          <span className="flex items-center text-[var(--text-muted)]">
            <svg
              width="13"
              height="13"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2.5"
              aria-hidden="true"
              className={`transition-transform duration-200 ${open ? "rotate-180" : ""}`}
            >
              <path d="M6 9l6 6 6-6" />
            </svg>
          </span>
        </span>
      </button>

      {open && (
        <div
          id={detailId}
          className="border-t px-[clamp(0.55rem,1.2vw,0.7rem)] py-[clamp(0.55rem,1.2vw,0.7rem)]"
          style={{ borderColor: border }}
        >
          <div className="flex flex-col gap-2">
            {group.problems.map((problem, index) => (
              <ProblemRow
                key={`${group.key}-${index}`}
                problem={problem}
                logId={logId}
                onJumpToLine={onJumpToLine}
              />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function SeverityIcon({ severity, isNoise }: { severity: string; isNoise?: boolean }) {
  if (isNoise) {
    // Speaker mute icon for noise items
    return (
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M11 5L6 9H2v6h4l5 4V5z" />
        <line x1="23" y1="9" x2="17" y2="15" />
        <line x1="17" y1="9" x2="23" y2="15" />
      </svg>
    );
  }

  return (
    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {severity === "Critical" ? (
        <>
          <path d="M12 2L2 7v10l10 5 10-5V7L12 2z" />
          <path d="M12 8v4M12 16h.01" />
        </>
      ) : severity === "High" ? (
        <>
          <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
          <path d="M12 9v4M12 17h.01" />
        </>
      ) : severity === "Medium" ? (
        <>
          <circle cx="12" cy="12" r="10" />
          <path d="M12 8v4M12 16h.01" />
        </>
      ) : severity === "Low" ? (
        <>
          <circle cx="12" cy="12" r="10" />
          <path d="M12 16v-4M12 8h.01" />
        </>
      ) : (
        <rect x="3" y="3" width="18" height="18" rx="2" />
      )}
    </svg>
  );
}
