import Link from "next/link";

export function Header() {
  return (
    <header className="relative z-10 border-b border-[var(--border)]">
      <div className="mx-auto flex w-full max-w-[1400px] flex-wrap items-center justify-between gap-x-4 gap-y-2 px-[var(--page-padding)] py-3">
        <Link
          href="/"
          className="group flex min-w-0 items-center gap-3 transition-opacity duration-150 hover:opacity-90"
        >
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src="/ketchup_bottle.png"
            alt=""
            width={40}
            height={40}
            className="h-10 w-10 shrink-0 object-contain"
          />
          <span className="flex min-w-0 flex-col leading-none">
            <span className="font-[var(--font-sans)] text-[1.35rem] font-semibold leading-none tracking-[-0.02em] text-[var(--text)]">
              mulch<span className="text-[var(--accent)]">.</span>
            </span>
            <span className="mt-1 truncate font-[var(--font-sans)] text-[0.78rem] italic leading-none text-[var(--info)]">
              logs. broken down.
            </span>
          </span>
        </Link>

        <nav className="flex items-center gap-1">
          <a
            href="https://github.com/indifferentketchup/mulch"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex h-8 items-center gap-1.5 rounded-[var(--radius-md)] px-3 font-[var(--font-mono)] text-[0.78rem] text-[var(--text-muted)] transition-colors duration-150 hover:bg-[var(--bg-elevated)] hover:text-[var(--text)]"
          >
            GitHub
          </a>
          <Link
            href="/api"
            className="inline-flex h-8 items-center gap-1.5 rounded-[var(--radius-md)] px-3 font-[var(--font-mono)] text-[0.78rem] text-[var(--text-muted)] transition-colors duration-150 hover:bg-[var(--bg-elevated)] hover:text-[var(--text)]"
          >
            API
          </Link>
        </nav>
      </div>
    </header>
  );
}
