import type { InfoItem, MetadataItem } from "@/lib/types";
import { groupInformation } from "@/lib/log-info";

interface InfoRowsProps {
  metadata: (MetadataItem & { key?: string })[];
  information: InfoItem[];
}

const ROW =
  "rounded-[var(--radius-sm)] bg-[var(--surface)] px-[clamp(0.6rem,2vw,0.75rem)] py-[clamp(0.4rem,1.5vw,0.5rem)]";
const ITEMS = "flex flex-wrap items-center gap-x-3 gap-y-1.5";
const HEADER =
  "flex items-center gap-1.5 border-r border-[var(--border)] pr-[clamp(0.6rem,2vw,0.75rem)] font-[var(--font-mono)] text-[clamp(0.7rem,1.8vw,0.75rem)] font-semibold tracking-[0.03em] text-[var(--text-muted)]";
const ITEM =
  "inline-flex items-center gap-1.5 font-[var(--font-mono)] text-[clamp(0.75rem,1.8vw,0.8rem)] text-[var(--text-muted)]";

export function InfoRows({ metadata, information }: InfoRowsProps) {
  const { mods, other } = groupInformation(information);

  const hasMeta = metadata.length > 0;
  const hasDetected = other.length > 0 || mods.length > 0;
  if (!hasMeta && !hasDetected) return null;

  return (
    <div className="mt-4 flex flex-col gap-2">
      {hasMeta && (
        <div className={ROW}>
          <div className={ITEMS}>
            <span className={HEADER}>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z" /><circle cx="7" cy="7" r="1.5" fill="currentColor" stroke="none" /></svg>
              <span>Metadata</span>
            </span>
            {metadata.map((m, i) => (
              <span key={`m-${i}`} className={ITEM}>
                <span className="font-medium">{(m.label ?? m.key ?? "").toString()}:</span>
                <span className="font-medium text-[var(--text)]">{m.value}</span>
              </span>
            ))}
          </div>
        </div>
      )}

      {hasDetected && (
        <div className={ROW}>
          <div className={ITEMS}>
            <span className={HEADER}>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" /><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12" /></svg>
              <span>Detected</span>
            </span>
            {other.map((info, i) => (
              <span key={`o-${i}`} className={ITEM}>
                <span className="font-medium">{info.label}:</span>
                <span className="font-medium text-[var(--text)]">{info.value}</span>
              </span>
            ))}
            {mods.length > 0 && (
              <details className="group/mods w-full">
                <summary className="flex w-fit cursor-pointer list-none items-center gap-1.5 font-[var(--font-mono)] text-[clamp(0.7rem,1.8vw,0.75rem)] font-medium text-[var(--text-muted)] transition-colors hover:text-[var(--text)]">
                  <span className="inline-block h-1.5 w-1.5 rotate-[-45deg] border-b-2 border-r-2 border-current transition-transform duration-200 group-open/mods:rotate-[45deg]" />
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M14 7h2a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2v-9a2 2 0 012-2h2M9 7V4a3 3 0 016 0v3" /></svg>
                  Mods loaded
                  <span className="text-[var(--text-muted)]">({mods.length})</span>
                </summary>
                <div className="flex flex-wrap gap-1.5 px-1 pb-1 pt-2">
                  {mods.map((mod, i) => (
                    <span
                      key={`mod-${i}`}
                      className="rounded-[var(--radius-xs)] border border-[var(--border)] bg-[var(--bg-inset)] px-1.5 py-0.5 font-[var(--font-mono)] text-[clamp(0.7rem,1.8vw,0.75rem)] text-[var(--text)]"
                    >
                      {mod.value}
                    </span>
                  ))}
                </div>
              </details>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
