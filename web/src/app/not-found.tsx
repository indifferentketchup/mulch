import Link from "next/link";

export default function NotFound() {
  return (
    <main className="relative z-10 mx-auto flex w-full max-w-[min(100%,calc(1400px-var(--page-padding)*2))] flex-1 flex-col items-center justify-center rounded-[12px] bg-[var(--bg-surface)] p-[clamp(2rem,5vw,3rem)] text-center">
      <div className="text-[clamp(4rem,15vw,8rem)] font-semibold leading-none text-[var(--text)] opacity-15">
        404
      </div>
      <div className="-mt-2 text-[clamp(1.25rem,4vw,1.8rem)] font-bold text-[var(--text)]">
        Log not found
      </div>
      <p className="mt-3 mb-[clamp(1.5rem,4vw,2rem)] text-[clamp(0.9rem,2vw,1rem)] text-[var(--text-muted)]">
        The log you&apos;re looking for doesn&apos;t exist or has expired.
      </p>
      <Link
        href="/"
        className="inline-flex items-center gap-2 rounded-[8px] bg-[var(--accent)] px-6 py-[clamp(0.6rem,2vw,0.7rem)] font-semibold text-[var(--bg)] transition-all duration-150 hover:bg-[color-mix(in_srgb,var(--accent)_78%,var(--bg)_22%)] active:scale-[0.97] focus-visible:outline-2 focus-visible:outline-[var(--accent)] focus-visible:outline-offset-2"
      >
        Paste a new log
      </Link>
    </main>
  );
}
