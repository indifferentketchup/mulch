import type { ProblemData } from "@/lib/types";
import { severityVar, severityBgVar, severityRank } from "@/lib/severity";

interface ProblemPanelProps {
  problems: ProblemData[];
  noiseCount: number;
  logId: string;
  hideEngineNoise: boolean;
}

export function ProblemPanel({ problems, noiseCount, logId, hideEngineNoise }: ProblemPanelProps) {
  if (problems.length === 0) return null;

  const visibleProblems = hideEngineNoise
    ? problems.filter((p) => !p.is_noise)
    : problems;
  const hiddenCount = hideEngineNoise ? noiseCount : 0;

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
        {visibleProblems.map((problem, i) => {
          const color = severityVar(problem.severity);
          const bg = severityBgVar(problem.severity);
          return (
            <div
              key={i}
              className="flex flex-col gap-2 motion-safe:animate-[fadeUp_0.35s_var(--ease-out)_both]"
              style={{ animationDelay: `${Math.min(i, 8) * 55}ms` }}
            >
              <a
                href={problem.entry_line ? `/${logId}#L${problem.entry_line}` : undefined}
                className={`group flex items-stretch overflow-hidden rounded-[var(--radius-md)] bg-[var(--bg-elevated)] transition-[outline-color] duration-150 outline outline-1 outline-transparent ${
                  problem.entry_line ? "cursor-pointer hover:outline-[var(--border)]" : ""
                }`}
              >
                <span
                  className="flex items-center gap-1.5 whitespace-nowrap px-[clamp(0.6rem,1.5vw,0.75rem)] py-[clamp(0.45rem,1.2vw,0.55rem)] font-[var(--font-mono)] text-[0.72rem] font-semibold uppercase tracking-[0.04em]"
                  style={{ backgroundColor: bg, color }}
                >
                  <SeverityIcon severity={problem.severity} />
                  {problem.severity}
                </span>
                <span className="flex max-w-[72ch] flex-1 items-center px-[clamp(0.65rem,1.5vw,0.85rem)] py-[clamp(0.45rem,1.2vw,0.55rem)] font-[var(--font-sans)] text-[clamp(0.85rem,2vw,0.9rem)] text-[var(--text)] [text-wrap:pretty] [word-break:break-word]">
                  {problem.message}
                </span>
                {problem.count > 1 && (
                  <span className="flex items-center whitespace-nowrap px-2 font-[var(--font-mono)] text-[0.72rem] font-semibold tabular-nums text-[var(--text-muted)]">
                    &times;{problem.count.toLocaleString()}
                  </span>
                )}
                {problem.entry_line && (
                  <span className="flex items-center whitespace-nowrap border-l border-[var(--border)] px-[clamp(0.6rem,1.5vw,0.8rem)] font-[var(--font-mono)] text-[0.72rem] font-medium tabular-nums text-[var(--text-muted)]">
                    L{problem.entry_line}
                  </span>
                )}
              </a>

              <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 pl-0.5">
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
                      problem.mod.is_direct ? "border-[var(--border)]" : "border-dashed border-[var(--border)]"
                    }`}
                    title={problem.mod.is_direct ? "Directly attributed mod" : "Inferred mod (lower confidence)"}
                  >
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
                    {problem.mod.name}
                  </a>
                )}

                {problem.stack_trace && (
                  <details className="group w-full">
                    <summary className="flex w-fit cursor-pointer list-none items-center gap-1.5 font-[var(--font-mono)] text-[0.74rem] font-medium text-[var(--text-muted)] transition-colors hover:text-[var(--text)]">
                      <span className="inline-block h-1.5 w-1.5 rotate-[-45deg] border-b-2 border-r-2 border-current transition-transform duration-200 group-open:rotate-[45deg]" />
                      stack trace
                    </summary>
                    <pre className="mt-2 overflow-x-auto whitespace-pre rounded-[var(--radius-sm)] border border-[var(--border)] bg-[var(--bg-inset)] p-3 font-[var(--font-mono)] text-[0.76rem] leading-relaxed text-[var(--text)]">
                      {problem.stack_trace}
                    </pre>
                  </details>
                )}

                {problem.solutions.length > 0 && (
                  <details className="group w-full">
                    <summary className="flex w-fit cursor-pointer list-none items-center gap-1.5 font-[var(--font-mono)] text-[0.74rem] font-medium text-[var(--text-muted)] transition-colors hover:text-[var(--text)]">
                      <span className="inline-block h-1.5 w-1.5 rotate-[-45deg] border-b-2 border-r-2 border-current transition-transform duration-200 group-open:rotate-[45deg]" />
                      {problem.solutions.length === 1 ? "solution" : `solutions (${problem.solutions.length})`}
                    </summary>
                    <div className="mt-2 flex flex-col gap-1.5">
                      {problem.solutions.map((sol, j) => (
                        <div key={j} className="flex items-baseline gap-2 font-[var(--font-sans)] text-[clamp(0.82rem,1.8vw,0.88rem)]">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" className="mt-0.5 shrink-0 text-[var(--info)]" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                          <span className="text-[var(--text)]">{sol.message}</span>
                        </div>
                      ))}
                    </div>
                  </details>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function SeverityIcon({ severity }: { severity: string }) {
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
