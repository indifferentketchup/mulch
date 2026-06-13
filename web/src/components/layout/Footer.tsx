export function Footer() {
  return (
    <footer className="relative z-10 mx-auto flex w-full max-w-[1400px] flex-wrap items-center justify-between gap-4 px-[var(--page-padding)] py-[clamp(1rem,3vw,2rem)] text-[clamp(0.75rem,2vw,0.9rem)] text-[var(--text-muted)] max-[640px]:justify-center">
      <nav className="flex gap-6 max-[640px]:w-full max-[640px]:order-1 max-[640px]:justify-center">
        <a
          href="https://github.com/indifferentketchup/iblogs"
          target="_blank"
          rel="noopener noreferrer"
          className="flex items-center gap-2 transition-colors duration-150 hover:text-[var(--accent)]"
        >
          GitHub
        </a>
        <a
          href="/api"
          className="flex items-center gap-2 transition-colors duration-150 hover:text-[var(--accent)]"
        >
          API
        </a>
      </nav>
      <span className="max-[640px]:order-2">
        based on{" "}
        <a
          href="https://github.com/aternosorg/mclogs"
          target="_blank"
          rel="noopener noreferrer"
          className="transition-colors duration-150 hover:text-[var(--accent)]"
        >
          mclogs
        </a>{" "}
        by{" "}
        <a
          href="https://github.com/aternosorg"
          target="_blank"
          rel="noopener noreferrer"
          className="transition-colors duration-150 hover:text-[var(--accent)]"
        >
          Aternos
        </a>
      </span>
    </footer>
  );
}
