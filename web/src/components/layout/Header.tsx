import Link from "next/link";

export function Header() {
  return (
    <header className="relative z-10 mx-auto flex w-full max-w-[1400px] flex-wrap items-center justify-between px-[--page-padding] py-[clamp(1rem,3vw,2rem)] transition-[max-width] duration-250">
      <Link href="/" className="flex items-center gap-[0.9rem] transition-transform duration-150 ease-out active:scale-90">
        <svg
          className="h-[clamp(1.5rem,3vw,2rem)] w-auto text-[var(--accent)]"
          width="41"
          height="42"
          viewBox="0 0 41 42"
          fill="none"
          aria-hidden="true"
        >
          <rect width="41" height="5" rx="2" fill="currentColor" />
          <rect y="9.25" width="33" height="5" rx="2" fill="currentColor" />
          <rect y="18.5" width="19" height="5" rx="2" fill="currentColor" />
          <rect y="27.75" width="33" height="5" rx="2" fill="currentColor" />
          <rect y="37" width="41" height="5" rx="2" fill="currentColor" />
        </svg>
        <span className="text-[clamp(1.75rem,3vw,2rem)] font-semibold text-[var(--text)]">
          iblogs
        </span>
      </Link>
      <div className="flex flex-col gap-1 text-right">
        <h1 className="text-[clamp(1rem,3vw,1.5rem)] font-normal text-[var(--text)]">
          <TypewriterVerb />
        </h1>
        <div className="text-[clamp(0.75rem,2vw,1rem)] text-[var(--text-muted)]">
          Built for game-server logs
        </div>
      </div>
    </header>
  );
}

function TypewriterVerb() {
  return (
    <span>
      <span className="font-semibold text-[var(--accent)]">Paste</span> your logs.
    </span>
  );
}
