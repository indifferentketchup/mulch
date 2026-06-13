export function Footer() {
  return (
    <footer className="relative z-10 mt-auto border-t border-[var(--border)]">
      <div className="mx-auto flex w-full max-w-[1400px] flex-wrap items-center justify-between gap-x-6 gap-y-2 px-[var(--page-padding)] py-5 font-[var(--font-mono)] text-[0.78rem] text-[var(--text-muted)] max-[640px]:justify-center">
        <nav className="flex gap-5 max-[640px]:order-1 max-[640px]:w-full max-[640px]:justify-center">
          <a
            href="https://github.com/indifferentketchup/mulch"
            target="_blank"
            rel="noopener noreferrer"
            className="underline decoration-1 underline-offset-2 transition-colors duration-150 hover:text-[var(--text)]"
          >
            github
          </a>
          <a
            href="/api"
            className="underline decoration-1 underline-offset-2 transition-colors duration-150 hover:text-[var(--text)]"
          >
            api
          </a>
        </nav>
        <span className="max-[640px]:order-2">
          based on{" "}
          <a
            href="https://github.com/aternosorg/mclogs"
            target="_blank"
            rel="noopener noreferrer"
            className="text-[var(--accent)] underline decoration-1 underline-offset-2 transition-colors duration-150 hover:text-[var(--accent-hover)]"
          >
            mclogs
          </a>{" "}
          by{" "}
          <a
            href="https://github.com/aternosorg"
            target="_blank"
            rel="noopener noreferrer"
            className="text-[var(--info)] underline decoration-1 underline-offset-2 transition-colors duration-150 hover:text-[var(--text)]"
          >
            Aternos
          </a>
        </span>
      </div>
    </footer>
  );
}
