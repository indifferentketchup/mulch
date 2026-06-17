import type { Metadata } from "next";
import Link from "next/link";

export const metadata: Metadata = {
  title: "API",
  description: "mulch HTTP API: upload, fetch, and analyze logs programmatically.",
};

interface Endpoint {
  method: string;
  path: string;
  desc: string;
}

const ENDPOINTS: Endpoint[] = [
  { method: "POST", path: "/api/v1/log", desc: "Upload a log. Body: { content, source?, metadata? }. Returns { id, url }." },
  { method: "GET", path: "/api/v1/log/{id}", desc: "Fetch a log with its metadata and analysis." },
  { method: "DELETE", path: "/api/v1/log/{id}", desc: "Delete a log (requires the owner token cookie)." },
  { method: "GET", path: "/api/v1/insights/{id}", desc: "Fetch the analysis only (problems + information), without the body." },
  { method: "GET", path: "/api/v1/limits", desc: "Upload caps: max bytes and max lines." },
];

const METHOD_COLOR: Record<string, string> = {
  GET: "var(--sev-low)",
  POST: "var(--sev-medium)",
  DELETE: "var(--error)",
};

export default function ApiDocsPage() {
  return (
    <main className="mx-auto w-full max-w-[840px] px-[var(--page-padding)] py-[clamp(1.5rem,4vw,3rem)]">
      <h1 className="font-[var(--font-sans)] text-[clamp(1.4rem,4vw,2rem)] font-semibold tracking-[-0.02em] text-[var(--text)]">
        API
      </h1>
      <p className="mt-3 max-w-[60ch] font-[var(--font-sans)] text-[clamp(0.9rem,2vw,1rem)] text-[var(--text-muted)]">
        Upload, fetch, and analyze logs programmatically. All endpoints return JSON.
        Uploads are rate limited per IP and run through the same PII and size filters
        as the web uploader.
      </p>

      <div className="mt-8 flex flex-col gap-2.5">
        {ENDPOINTS.map((e) => (
          <div
            key={`${e.method} ${e.path}`}
            className="flex flex-col gap-1.5 rounded-[var(--radius-md)] border border-[var(--border)] bg-[var(--bg-surface)] p-[clamp(0.75rem,2vw,1rem)] sm:flex-row sm:items-baseline sm:gap-4"
          >
            <div className="flex shrink-0 items-baseline gap-2.5">
              <span
                className="inline-flex min-w-[3.5rem] justify-center rounded-[var(--radius-xs)] px-2 py-0.5 font-[var(--font-mono)] text-[0.72rem] font-semibold uppercase"
                style={{
                  color: METHOD_COLOR[e.method] ?? "var(--text)",
                  backgroundColor: `color-mix(in srgb, ${METHOD_COLOR[e.method] ?? "var(--text)"} 14%, transparent)`,
                }}
              >
                {e.method}
              </span>
              <code className="font-[var(--font-mono)] text-[clamp(0.8rem,2vw,0.88rem)] text-[var(--text)]">
                {e.path}
              </code>
            </div>
            <p className="font-[var(--font-sans)] text-[0.85rem] text-[var(--text-muted)]">
              {e.desc}
            </p>
          </div>
        ))}
      </div>

      <div className="mt-8 rounded-[var(--radius-md)] border border-[var(--border)] bg-[var(--bg-inset)] p-[clamp(0.75rem,2vw,1rem)]">
        <p className="mb-2 font-[var(--font-mono)] text-[0.72rem] font-semibold uppercase tracking-[0.04em] text-[var(--text-muted)]">
          Example
        </p>
        <pre className="overflow-x-auto font-[var(--font-mono)] text-[0.8rem] leading-relaxed text-[var(--text)]">
{`curl -X POST https://your-host/api/v1/log \\
  -H "Content-Type: application/json" \\
  -d '{"content":"<your log text>","source":"my-tool"}'`}
        </pre>
      </div>

      <Link
        href="/"
        className="mt-8 inline-block font-[var(--font-mono)] text-[0.8rem] text-[var(--text-muted)] transition-colors hover:text-[var(--accent)]"
      >
        &larr; back to mulch
      </Link>
    </main>
  );
}
