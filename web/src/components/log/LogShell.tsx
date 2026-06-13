"use client";

import Link from "next/link";
import { LogView } from "./LogView";
import { LogActions } from "./LogActions";
import { LogSettings } from "./LogSettings";
import { CopyUrlButton } from "./CopyUrlButton";
import { ProblemPanel } from "@/components/problems/ProblemPanel";
import { useLogSettings } from "./LogSettingsContext";
import { severityRank } from "@/lib/severity";
import type {
  InfoItem,
  LogEntry,
  MetadataItem,
  ProblemData,
} from "@/lib/types";

interface LogShellProps {
  id: string;
  title: string;
  detected?: string;
  createdLabel: string;
  source?: string;
  metadata: MetadataItem[];
  information: InfoItem[];
  content: string;
  lineCount: number;
  bytes: number;
  problems: ProblemData[];
  entries: LogEntry[];
  noiseCount: number;
  canDelete: boolean;
}

function prettyGame(detected?: string): string | null {
  if (!detected || detected === "Generic") return null;
  return detected.replace(/([a-z])([A-Z])/g, "$1 $2").toLowerCase();
}

export function LogShell({
  id,
  title,
  detected,
  createdLabel,
  source,
  metadata,
  information,
  content,
  lineCount,
  bytes,
  problems,
  entries,
  noiseCount,
  canDelete,
}: LogShellProps) {
  const { settings } = useLogSettings();

  const widthClass = settings.fullWidth
    ? "w-full"
    : "mx-auto w-full max-w-[1400px]";

  const visibleProblems = settings.hideEngineNoise
    ? problems.filter((p) => !p.is_noise)
    : problems;

  const problemLines = visibleProblems
    .filter((p) => p.entry_line != null)
    .map((p) => p.entry_line as number);

  const severityByLine: Record<number, string> = {};
  for (const p of visibleProblems) {
    if (p.entry_line == null) continue;
    const cur = severityByLine[p.entry_line];
    if (!cur || severityRank(p.severity) < severityRank(cur)) {
      severityByLine[p.entry_line] = p.severity;
    }
  }

  const game = prettyGame(detected);

  return (
    <main className={`relative z-10 px-[var(--page-padding)] py-[clamp(0.85rem,2.5vw,1.5rem)] ${widthClass}`}>
      <div className="rounded-[var(--radius-panel)] bg-[var(--bg-surface)] shadow-[var(--shadow-panel)]">
        {/* diagnostic head */}
        <div className="border-b border-[var(--border)] p-[clamp(1rem,3vw,1.5rem)]">
          <div className="flex flex-wrap items-start justify-between gap-4 max-[700px]:flex-col">
            <div className="min-w-0 flex-1 basis-[280px] max-[700px]:flex-none max-[700px]:basis-auto">
              <h1 className="font-[var(--font-sans)] text-[clamp(1.05rem,3vw,1.3rem)] font-semibold tracking-[-0.01em] text-[var(--text)] [text-wrap:balance] [word-break:break-word]">
                {title}
              </h1>
              <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2">
                <CopyUrlButton id={id} />
                {game && (
                  <span className="inline-flex items-center rounded-[var(--radius-sm)] bg-[var(--info-bg)] px-2 py-0.5 font-[var(--font-mono)] text-[0.72rem] text-[var(--info)]">
                    {game}
                  </span>
                )}
                <span className="inline-flex items-center gap-1.5 font-[var(--font-mono)] text-[0.72rem] text-[var(--text-muted)]">
                  <svg className="opacity-70" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                  {createdLabel}
                </span>
                {metadata.map((m, i) => (
                  <span key={`m-${i}`} className="font-[var(--font-mono)] text-[0.72rem] text-[var(--text-muted)]">
                    {m.label}: <span className="text-[var(--text)]">{m.value}</span>
                  </span>
                ))}
                {information.map((info, i) => (
                  <span key={`i-${i}`} className="font-[var(--font-mono)] text-[0.72rem] text-[var(--text-muted)]">
                    {info.label}: <span className="text-[var(--text)]">{info.value}</span>
                  </span>
                ))}
              </div>
            </div>
            <LogActions
              logId={id}
              lines={lineCount}
              bytes={bytes}
              canDelete={canDelete}
            />
          </div>

          {problems.length > 0 && (
            <ProblemPanel
              problems={problems}
              noiseCount={noiseCount}
              logId={id}
              hideEngineNoise={settings.hideEngineNoise}
            />
          )}
        </div>

        {/* console screen */}
        <LogView
          content={content}
          entries={entries}
          smartFold={!settings.showAllEntries}
          noWrap={settings.noWrap}
          overflow={settings.overflow}
          floatingScrollbar={settings.floatingScrollbar}
          problemLines={problemLines}
          severityByLine={severityByLine}
        />

        {/* foot bar */}
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-[var(--border)] px-[clamp(1rem,3vw,1.5rem)] py-[clamp(0.7rem,2vw,0.9rem)] font-[var(--font-mono)] text-[0.72rem] text-[var(--text-muted)]">
          <div className="flex items-center gap-3">
            <LogSettings />
            {source && (
              <span className="inline-flex items-center gap-1.5">
                <svg className="opacity-70" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                via {source}
              </span>
            )}
          </div>
          <div className="flex items-center gap-4">
            <span className="max-[480px]:hidden">kept 90 days from last view</span>
            <Link href="/" className="transition-colors duration-150 hover:text-[var(--accent)]">
              paste a new log
            </Link>
          </div>
        </div>
      </div>
    </main>
  );
}
