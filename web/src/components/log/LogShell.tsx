"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { LogView } from "./LogView";
import { LogActions } from "./LogActions";
import { LogSettings } from "./LogSettings";
import { LogSearch } from "./LogSearch";
import { CopyUrlButton } from "./CopyUrlButton";
import { ProblemPanel } from "@/components/problems/ProblemPanel";
import { InfoRows } from "./InfoRows";
import { useLogSettings } from "./LogSettingsContext";
import { severityRank } from "@/lib/severity";
import { groupInformation, assetGroupsToProblems } from "@/lib/log-info";
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
  errors: number;
  problems: ProblemData[];
  entries: LogEntry[];
  canDelete: boolean;
  createdMs: number;
  displayUrl: string;
  retentionLabel: string;
  abuseEmail: string | null;
}

function prettyGame(detected?: string): string | null {
  if (!detected || detected === "Generic") return null;
  return detected.replace(/([a-z])([A-Z])/g, "$1 $2").toLowerCase();
}

function parseHashLine(hash: string): number | null {
  const match = hash.match(/^#L(\d+)$/);
  if (!match) {
    return null;
  }
  const line = Number.parseInt(match[1], 10);
  return Number.isNaN(line) ? null : line;
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
  errors,
  problems,
  entries,
  canDelete,
  createdMs,
  retentionLabel,
  abuseEmail,
}: LogShellProps) {
  const { settings } = useLogSettings();

  // Active log line lives here so deep links (#L<n>), gutter clicks, and the
  // problem panel all drive the same highlight (PHP updateLineNumber parity).
  const [activeLine, setActiveLine] = useState<number | null>(() =>
    typeof window === "undefined" ? null : parseHashLine(window.location.hash)
  );

  // When activeLine changes (deep link, gutter click, problem-chip jump), make
  // sure the log container itself is on screen. The virtualizer inside LogView
  // already scrolls the INNER scroll container, but on a deep link with lots
  // of header content (problems panel, info rows) the page may still be
  // scrolled at the top, leaving the log container off-screen. We give the
  // browser a frame so the inner scroll can settle, then bring the active
  // line into view. Avoids the "you loaded #L1614 but only see the problem
  // panel" surprise.
  useEffect(() => {
    if (activeLine == null) return;
    const id = window.requestAnimationFrame(() => {
      const target = document.getElementById(`L${activeLine}`);
      if (target) {
        target.scrollIntoView({ block: "center", behavior: "auto" });
      }
    });
    return () => window.cancelAnimationFrame(id);
  }, [activeLine]);

  const jumpToLine = useCallback((line: number) => {
    setActiveLine(line);
    history.replaceState(null, "", `#L${line}`);
    document.getElementById(`L${line}`)?.scrollIntoView({ block: "center" });
  }, []);

  // Render the upload time in the visitor's locale after hydration (the server
  // string would otherwise pin everyone to the server locale).
  const clientDate = useMemo(() => new Date(createdMs).toLocaleString(), [createdMs]);

  // Fold asset warnings (Missing icon/sound/sprite) into grouped problem rows and
  // pull the build hash out of the engine-version line, mirroring the PHP frontend.
  const { buildHash, assetGroups } = groupInformation(information);
  const allProblems = [...problems, ...assetGroupsToProblems(assetGroups)].sort(
    (a, b) => b.rank - a.rank
  );
  const allNoiseCount = allProblems.filter((p) => p.gated).length;

  const widthClass = settings.fullWidth
    ? "w-full"
    : "mx-auto w-full max-w-[1400px]";

  const visibleProblems = settings.hideEngineNoise
    ? allProblems.filter((p) => !p.is_noise)
    : allProblems;

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

  // In-log search. The query lives here so the search input, the count, and
  // the virtualizer can all read it. The matches themselves are computed
  // inside LogView (which has the line array); LogView reports back the
  // current match's line so we can render "1/12" in the search input and
  // drive the virtualizer's scroll.
  const [searchQuery, setSearchQuery] = useState("");
  const [searchState, setSearchState] = useState<{
    count: number;
    currentIndex: number;
    currentLine: number | null;
  }>({ count: 0, currentIndex: -1, currentLine: null });

  // Bumping this number is the "Next"/"Prev" signal: LogView watches it and
  // advances the current match index by ±1 (clamped, wrapping). Using a
  // counter (not a setter) keeps prev/next independent of the async data
  // round-trip and lets us expose simple buttons.
  const [searchStep, setSearchStep] = useState(0);
  const nextMatch = useCallback(() => setSearchStep((s) => s + 1), []);
  const prevMatch = useCallback(() => setSearchStep((s) => s - 1), []);
  const clearSearch = useCallback(() => setSearchQuery(""), []);

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
                  <span suppressHydrationWarning>{clientDate ?? createdLabel}</span>
                </span>
              </div>
            </div>
            <LogActions
              logId={id}
              lines={lineCount}
              bytes={bytes}
              errors={errors}
              buildHash={buildHash}
              canDelete={canDelete}
            />
          </div>

          <InfoRows metadata={metadata} information={information} />

          {visibleProblems.length > 0 && (
            <ProblemPanel
              problems={allProblems}
              noiseCount={allNoiseCount}
              logId={id}
              hideEngineNoise={settings.hideEngineNoise}
              onJumpToLine={jumpToLine}
            />
          )}
        </div>

        {/* console screen */}
        <LogView
          content={content}
          entries={entries}
          noWrap={settings.noWrap}
          floatingScrollbar={settings.floatingScrollbar}
          problemLines={problemLines}
          severityByLine={severityByLine}
          activeLine={activeLine}
          onSetActiveLine={setActiveLine}
          searchQuery={searchQuery}
          searchStep={searchStep}
          onSearchState={setSearchState}
        />

        {/* foot bar */}
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-[var(--border)] px-[clamp(1rem,3vw,1.5rem)] py-[clamp(0.7rem,2vw,0.9rem)] font-[var(--font-mono)] text-[0.72rem] text-[var(--text-muted)]">
          <div className="flex items-center gap-3">
            <LogSearch
              query={searchQuery}
              onQueryChange={setSearchQuery}
              matchCount={searchState.count}
              currentMatch={searchState.currentIndex}
              onNext={nextMatch}
              onPrev={prevMatch}
              onClear={clearSearch}
            />
            <LogSettings />
            {source && (
              <span className="inline-flex items-center gap-1.5">
                <svg className="opacity-70" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                via {source}
              </span>
            )}
          </div>
          <div className="flex items-center gap-4">
            <button
              type="button"
              onClick={() => window.scrollTo({ top: 0, behavior: "smooth" })}
              title="Scroll to top"
              className="inline-flex items-center gap-1.5 transition-colors duration-150 hover:text-[var(--accent)]"
            >
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7" /></svg>
              top
            </button>
            <span className="max-[480px]:hidden">{retentionLabel}</span>
            {abuseEmail && (
              <a
                href={`mailto:${abuseEmail}?subject=${encodeURIComponent(`Report ${id}`)}`}
                className="inline-flex items-center gap-1.5 transition-colors duration-150 hover:text-[var(--accent)]"
              >
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M4 21V4h11l1 2h4v9h-9l-1-2H6v8z" /></svg>
                report abuse
              </a>
            )}
            <Link href="/" className="transition-colors duration-150 hover:text-[var(--accent)]">
              paste a new log
            </Link>
          </div>
        </div>
      </div>
    </main>
  );
}
