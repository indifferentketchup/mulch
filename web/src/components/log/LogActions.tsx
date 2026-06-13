"use client";

import { useState } from "react";
import Link from "next/link";

interface LogActionsProps {
  logId: string;
  lines: number;
  bytes: number;
  canDelete: boolean;
}

const ghost =
  "inline-flex h-8 items-center gap-1.5 rounded-[var(--radius-md)] border border-[var(--border)] bg-[var(--bg-elevated)] px-3 font-[var(--font-mono)] text-[0.75rem] text-[var(--text-muted)] transition-colors duration-150 hover:border-[var(--text-muted)] hover:text-[var(--text)]";

export function LogActions({ logId, lines, bytes, canDelete }: LogActionsProps) {
  const [showDelete, setShowDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const formatBytes = (b: number) => {
    if (b < 1024) return `${b} B`;
    if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
    return `${(b / 1024 / 1024).toFixed(1)} MB`;
  };

  const handleDelete = async () => {
    setDeleting(true);
    setDeleteError(null);
    try {
      const res = await fetch(`/api/${logId}`, { method: "DELETE" });
      if (!res.ok) {
        setDeleteError(`${res.status} ${res.statusText}`);
        setDeleting(false);
        return;
      }
      window.location.href = "/";
    } catch {
      setDeleteError("Network error");
      setDeleting(false);
    }
  };

  return (
    <div className="flex shrink-0 flex-wrap items-center gap-2">
      <button
        type="button"
        className={`${ghost} tabular-nums`}
        title="Jump to end of log"
        onClick={() => window.scrollTo({ top: document.body.scrollHeight, behavior: "smooth" })}
      >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M12 5v14M19 12l-7 7-7-7" /></svg>
        {lines.toLocaleString()} lines &middot; {formatBytes(bytes)}
      </button>
      <Link href={`/${logId}/raw`} target="_blank" className={ghost}>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>
        raw
      </Link>
      {canDelete && (
        <div className="relative">
          <button
            type="button"
            className="inline-flex h-8 items-center gap-1.5 rounded-[var(--radius-md)] border border-[var(--accent-border)] bg-transparent px-3 font-[var(--font-mono)] text-[0.75rem] font-medium text-[var(--accent)] transition-colors duration-150 hover:bg-[var(--accent)] hover:text-[var(--brand-ink)]"
            onClick={() => setShowDelete(!showDelete)}
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2" /></svg>
            delete
          </button>
          {showDelete && (
            <div className="absolute right-0 top-full z-20 mt-2 min-w-[260px] rounded-[var(--radius-md)] border border-[var(--border)] bg-[var(--bg-elevated)] p-4 shadow-[var(--shadow-panel)]">
              <p className="mb-3 font-[var(--font-sans)] text-[0.88rem] text-[var(--text)]">
                Delete this log permanently?
              </p>
              {deleteError && (
                <div className="mb-3 rounded-[var(--radius-sm)] border border-[var(--error-border)] bg-[var(--error-bg)] p-2 font-[var(--font-mono)] text-[0.75rem] text-[var(--error)]">
                  {deleteError}
                </div>
              )}
              <div className="flex gap-2">
                <button
                  type="button"
                  className="flex-1 rounded-[var(--radius-md)] border border-[var(--border)] bg-transparent px-3 py-1.5 font-[var(--font-mono)] text-[0.78rem] text-[var(--text)] transition-colors duration-150 hover:bg-[var(--bg-surface)]"
                  onClick={() => setShowDelete(false)}
                >
                  cancel
                </button>
                <button
                  type="button"
                  disabled={deleting}
                  className="flex-1 rounded-[var(--radius-md)] bg-[var(--accent)] px-3 py-1.5 font-[var(--font-mono)] text-[0.78rem] font-semibold text-[var(--brand-ink)] transition-colors duration-150 hover:bg-[var(--accent-hover)] disabled:cursor-not-allowed disabled:opacity-50"
                  onClick={handleDelete}
                >
                  {deleting ? "deleting…" : "delete"}
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
