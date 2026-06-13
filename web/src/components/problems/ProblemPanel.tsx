import type { ProblemData } from "@/lib/types";

interface ProblemPanelProps {
  problems: ProblemData[];
  noiseCount: number;
  logId: string;
}

export function ProblemPanel({ problems, noiseCount, logId }: ProblemPanelProps) {
  if (problems.length === 0) return null;

  const visibleProblems = problems.filter((p) => !p.is_noise);

  return (
    <div className="border-t border-[var(--border)] pt-[clamp(0.75rem,2vw,1rem)] mt-[clamp(0.75rem,2vw,1rem)]">
      <div className="overflow-hidden rounded-[8px] border border-[var(--border)] bg-[var(--surface)]">
        <div className="flex items-center gap-[clamp(0.5rem,1.5vw,0.6rem)] border-b border-[var(--border)] bg-[var(--surface)] p-[clamp(0.6rem,2vw,0.75rem)_clamp(0.85rem,2.5vw,1rem)]">
          <span
            className="inline-flex min-w-[clamp(1.25rem,2.5vw,1.4rem)] items-center justify-center rounded-[4px] bg-[var(--accent)] px-2 py-0.5 text-[clamp(0.75rem,1.8vw,0.8rem)] font-semibold text-[var(--bg)]"
          >
            {visibleProblems.length}
          </span>
          <span className="text-[clamp(0.9rem,2vw,1rem)] font-semibold text-[var(--text)]">
            {visibleProblems.length === 1 ? "Problem" : "Problems"} detected
            {noiseCount > 0 && (
              <span className="ml-1 font-medium text-[var(--text-muted)]">
                ({noiseCount} noise hidden)
              </span>
            )}
          </span>
        </div>
        <div className="flex flex-col">
          {visibleProblems.map((problem, i) => (
            <div
              key={i}
              className={`flex flex-col gap-[clamp(0.4rem,1vw,0.5rem)] border-b border-[var(--border)] p-[clamp(0.75rem,2vw,1rem)_clamp(0.85rem,2.5vw,1rem)] last:border-b-0`}
            >
              <a
                href={problem.entry_line ? `/${logId}#L${problem.entry_line}` : undefined}
                className={`flex overflow-hidden rounded-[5px] border text-[clamp(0.85rem,2vw,0.9rem)] transition-colors duration-150 hover:border-[var(--error)] ${problem.entry_line ? "cursor-pointer" : ""}`}
                style={{
                  backgroundColor: "transparent",
                  borderColor: `color-mix(in srgb, var(--error) 40%, transparent)`,
                }}
              >
                <span
                  className="flex items-center gap-1.5 whitespace-nowrap px-[clamp(0.55rem,1.5vw,0.65rem)] py-[clamp(0.3rem,1vw,0.4rem)] text-[clamp(0.75rem,1.8vw,0.8rem)] font-semibold"
                  style={{ backgroundColor: "var(--error)", color: "#fff" }}
                >
                  <SeverityIcon severity={problem.severity} />
                  {problem.severity}
                </span>
                <span className="flex flex-1 items-center px-[clamp(0.55rem,1.5vw,0.65rem)] py-[clamp(0.3rem,1vw,0.4rem)] font-medium text-[var(--text)] [word-break:break-word]">
                  {problem.message}
                </span>
                {problem.entry_line && (
                  <span className="my-[clamp(0.25rem,0.8vw,0.35rem)] mx-[clamp(0.55rem,1.5vw,0.65rem)] inline-flex items-center whitespace-nowrap rounded-[4px] border border-[var(--border)] bg-[var(--surface)] px-2 py-0.5 font-[var(--font-mono)] text-[clamp(0.7rem,1.6vw,0.75rem)] font-medium text-[var(--text-muted)]">
                    Line {problem.entry_line}
                  </span>
                )}
                {problem.count > 1 && (
                  <span className="ml-auto mr-[clamp(0.55rem,1.5vw,0.65rem)] inline-flex items-center font-[var(--font-mono)] text-[clamp(0.7rem,1.6vw,0.75rem)] font-semibold text-[var(--text-muted)] [font-variant-numeric:tabular-nums] whitespace-nowrap">
                    x{problem.count.toLocaleString()}
                  </span>
                )}
              </a>

              {problem.mod && (
                <a
                  href={
                    problem.mod.workshop_id
                      ? `https://steamcommunity.com/sharedfiles/filedetails/?id=${problem.mod.workshop_id}`
                      : undefined
                  }
                  target={problem.mod.workshop_id ? "_blank" : undefined}
                  rel="noopener noreferrer"
                  className={`self-start inline-flex items-center gap-1.5 rounded-[999px] border border-[var(--border)] bg-[var(--surface)] px-[clamp(0.5rem,1.4vw,0.6rem)] py-[clamp(0.25rem,0.7vw,0.3rem)] text-[clamp(0.72rem,1.6vw,0.78rem)] font-medium text-[var(--text-muted)] transition-colors duration-150 hover:border-[var(--accent-border)] hover:bg-[var(--accent-bg)] hover:text-[var(--text)] ${!problem.mod.is_direct ? "border-dashed" : ""}`}
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                  {problem.mod.name}
                </a>
              )}

              {problem.stack_trace && (
                <details className="group">
                  <summary className="flex w-fit cursor-pointer items-center gap-1.5 text-[clamp(0.75rem,1.8vw,0.8rem)] font-semibold text-[var(--text-muted)] transition-colors hover:text-[var(--text)] list-none">
                    <span className="inline-block w-2 h-2 border-r-2 border-b-2 border-current transition-transform duration-200 rotate-[-45deg] group-open:rotate-[45deg]" />
                    Stack trace
                  </summary>
                  <pre className="mt-[clamp(0.4rem,1vw,0.5rem)] overflow-x-auto whitespace-pre rounded-[5px] border border-[var(--border)] bg-[var(--bg-inset)] p-[clamp(0.5rem,1.5vw,0.65rem)] font-[var(--font-mono)] text-[clamp(0.75rem,1.7vw,0.8rem)] leading-relaxed text-[var(--text)]">
                    {problem.stack_trace}
                  </pre>
                </details>
              )}

              {problem.solutions.length > 0 && (
                <details className="group">
                  <summary className="flex w-fit cursor-pointer items-center gap-1.5 text-[clamp(0.75rem,1.8vw,0.8rem)] font-semibold text-[var(--text-muted)] transition-colors hover:text-[var(--text)] list-none">
                    <span className="inline-block w-2 h-2 border-r-2 border-b-2 border-current transition-transform duration-200 rotate-[-45deg] group-open:rotate-[45deg]" />
                    {problem.solutions.length === 1 ? "Solution" : "Solutions"}
                  </summary>
                  {problem.solutions.map((sol, j) => (
                    <div key={j} className="mt-[clamp(0.3rem,0.8vw,0.4rem)] flex items-baseline gap-[clamp(0.4rem,1vw,0.5rem)] text-[clamp(0.8rem,1.8vw,0.85rem)]">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="shrink-0 text-[var(--accent)]"><path d="M9 18l6-6-6-6"/></svg>
                      <span className="text-[var(--text)]">{sol.message}</span>
                    </div>
                  ))}
                </details>
              )}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function SeverityIcon({ severity }: { severity: string }) {
  return (
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      {severity === "Critical" ? (
        <path d="M12 2L2 7v10l10 5 10-5V7L12 2zM12 22V12M12 8v0" />
      ) : severity === "High" ? (
        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17v0" />
      ) : severity === "Medium" ? (
        <path d="M12 2a10 10 0 1010 10A10 10 0 0012 2zM12 16v-4M12 8v0" />
      ) : severity === "Low" ? (
        <circle cx="12" cy="12" r="10" />
      ) : (
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" strokeLinecap="square" />
      )}
    </svg>
  );
}
