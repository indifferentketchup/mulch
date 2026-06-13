import Link from "next/link";

export default function NotFound() {
  return (
    <main className="relative z-10 mx-auto flex w-full max-w-[1400px] flex-1 items-center justify-center px-[var(--page-padding)] py-[clamp(0.85rem,2.5vw,1.5rem)]">
      <div className="w-full max-w-[440px] overflow-hidden rounded-[var(--radius-panel)] bg-[var(--bg-surface)] shadow-[var(--shadow-panel)]">
        <div className="flex items-center gap-2 border-b border-[var(--border)] px-4 py-2.5 font-[var(--font-mono)] text-[0.75rem] text-[var(--text-muted)]">
          <span className="inline-block h-2 w-2 rounded-full bg-[var(--accent)]" aria-hidden="true" />
          404
        </div>
        <div className="flex flex-col items-center px-6 py-[clamp(2rem,6vw,3rem)] text-center">
          <p className="font-[var(--font-mono)] text-[0.8rem] text-[var(--text-muted)]">
            no such log
          </p>
          <h1 className="mt-2 font-[var(--font-sans)] text-[clamp(1.25rem,4vw,1.6rem)] font-semibold text-[var(--text)] [text-wrap:balance]">
            This log does not exist.
          </h1>
          <p className="mt-2 font-[var(--font-sans)] text-[0.9rem] text-[var(--text-muted)]">
            It may have expired, or the id is wrong.
          </p>
          <Link
            href="/"
            className="mt-7 inline-flex h-9 items-center gap-2 rounded-[var(--radius-md)] bg-[var(--accent)] px-5 font-[var(--font-sans)] text-[0.85rem] font-semibold text-[var(--brand-ink)] transition-all duration-150 hover:bg-[var(--accent-hover)] hover:shadow-[var(--shadow-card)] active:scale-[0.97]"
          >
            paste a new log
          </Link>
        </div>
      </div>
    </main>
  );
}
