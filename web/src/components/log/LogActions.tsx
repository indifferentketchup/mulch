"use client";

import { useState } from "react";
import Link from "next/link";

interface LogActionsProps {
  logId: string;
  lines: number;
  bytes: number;
  canDelete: boolean;
}

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
    <div className="flex flex-wrap items-center gap-2 shrink-0">
      <button
        className="inline-flex items-center gap-1.5 rounded-[8px] bg-[var(--surface)] border border-[var(--border)] px-[clamp(0.35rem,1.5vw,0.4rem)] py-[clamp(0.35rem,1.5vw,0.4rem)] font-semibold text-[clamp(0.75rem,1.8vw,0.8rem)] text-[var(--text)] transition-colors hover:bg-[var(--accent-bg)]"
        onClick={() => window.scrollTo({ top: document.body.scrollHeight, behavior: "smooth" })}
      >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
        {lines.toLocaleString()} lines &middot; {formatBytes(bytes)}
      </button>
      <Link
        href={`/${logId}/raw`}
        target="_blank"
        className="inline-flex items-center gap-1 rounded-[8px] bg-[var(--surface)] border border-[var(--border)] px-[clamp(0.35rem,1.5vw,0.4rem)] py-[clamp(0.35rem,1.5vw,0.4rem)] font-semibold text-[clamp(0.75rem,1.8vw,0.8rem)] text-[var(--text)] transition-colors hover:bg-[var(--accent-bg)]"
      >
        Raw
      </Link>
      {canDelete && (
      <div className="relative">
        <button
          className="inline-flex items-center gap-1 rounded-[8px] bg-[var(--accent)] px-[clamp(0.35rem,1.5vw,0.4rem)] py-[clamp(0.35rem,1.5vw,0.4rem)] font-semibold text-[clamp(0.75rem,1.8vw,0.8rem)] text-[var(--bg)] transition-colors hover:bg-[color-mix(in_srgb,var(--accent)_78%,var(--bg)_22%)]"
          onClick={() => setShowDelete(!showDelete)}
        >
          Delete
        </button>
        {showDelete && (
          <div className="absolute right-0 bottom-full mb-2 z-20 min-w-[250px] rounded-[8px] bg-[var(--bg-surface)] border border-[var(--border)] p-4 shadow-[0_4px_20px_rgba(0,0,0,0.3)]">
            <p className="mb-2 font-medium text-[var(--text)]">Delete this log permanently?</p>
            {deleteError && (
              <div className="mb-2 rounded-[8px] bg-[var(--error-bg)] border border-[var(--error-border)] p-2 text-sm text-[var(--text)]">
                {deleteError}
              </div>
            )}
            <div className="flex gap-2">
              <button
                className="flex-1 rounded-[8px] bg-[var(--bg)] border border-[var(--border)] px-3 py-1.5 text-sm text-[var(--text)] transition-colors hover:bg-[var(--surface)]"
                onClick={() => setShowDelete(false)}
              >
                Cancel
              </button>
              <button
                disabled={deleting}
                className="flex-1 rounded-[8px] bg-[var(--accent)] px-3 py-1.5 text-sm font-semibold text-[var(--bg)] transition-colors hover:bg-[color-mix(in_srgb,var(--accent)_78%,var(--bg)_22%)] disabled:opacity-50"
                onClick={handleDelete}
              >
                {deleting ? "Deleting..." : "Delete"}
              </button>
            </div>
          </div>
        )}
      </div>
      )}
    </div>
  );
}
