"use client";

export function CopyUrlButton({ id }: { id: string }) {
  return (
    <button
      className="inline-flex items-center gap-1.5 rounded-[6px] bg-[var(--surface)] border border-[var(--border)] px-[clamp(0.4rem,1.5vw,0.5rem)] py-[clamp(0.2rem,1vw,0.25rem)] font-[var(--font-mono)] text-[clamp(0.7rem,1.8vw,0.75rem)] text-[var(--text-muted)] transition-colors hover:border-[var(--accent)] hover:bg-[var(--accent-bg)] hover:text-[var(--text)]"
      onClick={() => navigator.clipboard.writeText(window.location.href)}
    >
      <span className="font-[var(--font-mono)]">{id}</span>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="opacity-50 text-[var(--accent)]">
        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
      </svg>
    </button>
  );
}
