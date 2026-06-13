"use client";

import Link from "next/link";
import { LogView } from "./LogView";
import { LogActions } from "./LogActions";
import { LogSettings } from "./LogSettings";
import { CopyUrlButton } from "./CopyUrlButton";
import { ProblemPanel } from "@/components/problems/ProblemPanel";
import { useLogSettings } from "./LogSettingsContext";
import type {
  InfoItem,
  LogEntry,
  MetadataItem,
  ProblemData,
} from "@/lib/types";

interface LogShellProps {
  id: string;
  title: string;
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

export function LogShell({
  id,
  title,
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
    : "mx-auto w-full max-w-[min(100%,calc(1400px-var(--page-padding)*2))]";

  const problemLines = problems
    .filter((p) => !p.is_noise && p.entry_line != null)
    .map((p) => p.entry_line as number);

  return (
    <>
      <main className={`relative z-10 flex flex-0 flex-col overflow-hidden rounded-t-[12px] bg-[var(--bg-surface)] ${widthClass}`}>
        <div className="border-b border-[var(--border)] p-[clamp(1rem,3vw,1.5rem)]">
          <div className="flex flex-wrap items-start justify-between gap-4 max-[640px]:flex-col">
            <div className="min-w-0 flex-1 basis-[300px]">
              <div className="flex flex-wrap items-center gap-3">
                <h1 className="flex items-center gap-2 text-[clamp(1.1rem,3vw,1.25rem)] font-semibold text-[var(--text)]">
                  <svg className="text-[var(--accent)]" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8" />
                  </svg>
                  {title}
                </h1>
                <CopyUrlButton id={id} />
                <div className="flex items-center gap-1 text-[clamp(0.65rem,1.6vw,0.7rem)] text-[var(--text-muted)]">
                  <svg className="opacity-60" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                  {createdLabel}
                </div>
              </div>
              {metadata.length > 0 && (
                <div className="mt-4 flex flex-wrap gap-3">
                  {metadata.map((m, i) => (
                    <span key={i} className="text-[clamp(0.75rem,1.8vw,0.8rem)] text-[var(--text-muted)]">
                      <span className="font-medium">{m.label}:</span>{" "}
                      <span className="font-medium font-[var(--font-mono)] text-[var(--text)]">{m.value}</span>
                    </span>
                  ))}
                </div>
              )}
              {information.length > 0 && (
                <div className="mt-4 flex flex-wrap gap-3">
                  {information.map((info, i) => (
                    <span key={i} className="text-[clamp(0.75rem,1.8vw,0.8rem)] text-[var(--text-muted)]">
                      <span className="font-medium">{info.label}:</span>{" "}
                      <span className="font-medium font-[var(--font-mono)] text-[var(--text)]">{info.value}</span>
                    </span>
                  ))}
                </div>
              )}
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
      </main>

      <div className={`relative z-10 bg-[var(--bg-surface)] ${widthClass}`}>
        <LogView
          content={content}
          entries={entries}
          smartFold={!settings.showAllEntries}
          noWrap={settings.noWrap}
          overflow={settings.overflow}
          floatingScrollbar={settings.floatingScrollbar}
          problemLines={problemLines}
        />
      </div>

      <div className={`relative z-10 mb-0 rounded-b-[12px] bg-[var(--bg-surface)] px-[var(--page-padding)] ${widthClass}`}>
        <div className="flex items-center justify-between border-b border-[var(--border)] py-[clamp(0.75rem,2vw,1rem)]">
          <div className="flex items-center gap-2">
            <LogSettings />
          </div>
        </div>
        <div className="grid grid-cols-2 items-center gap-[clamp(0.75rem,2vw,1.25rem)] border-t border-[var(--border)] py-[clamp(0.75rem,2vw,1rem)] text-[clamp(0.85rem,2vw,0.9rem)] text-[var(--text-muted)] max-[640px]:grid-cols-1 max-[640px]:gap-2 max-[640px]:text-center">
          {source && (
            <div className="flex items-center gap-2">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
              {source}
            </div>
          )}
          <div className="text-center max-[640px]:text-center">
            Log saved for 90 days from last view.
          </div>
          <div className="text-right max-[640px]:text-center">
            <Link href="/" className="transition-colors hover:text-[var(--accent)]">
              Paste a new log
            </Link>
          </div>
        </div>
      </div>
    </>
  );
}
