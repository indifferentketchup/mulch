import type { Metadata } from "next";
import { cookies, headers } from "next/headers";
import { notFound } from "next/navigation";
import { getLog } from "@/lib/log-service";
import { LogShell } from "@/components/log/LogShell";
import { AnalysisPoller } from "@/components/log/AnalysisPoller";
import { LogSettingsProvider } from "@/components/log/LogSettingsContext";
import { parseSettingsCookie } from "@/lib/log-settings";
import { formatRetention } from "@/lib/format";
import type { AnalysisData } from "@/lib/types";

interface LogPageProps {
  params: Promise<{ id: string }>;
}

const STORAGE_TTL = parseInt(process.env.IBLOGS_STORAGE_TTL ?? "7776000", 10);
const ABUSE_EMAIL = process.env.IBLOGS_LEGAL_ABUSE ?? null;

export async function generateMetadata({ params }: LogPageProps): Promise<Metadata> {
  const { id } = await params;
  const log = await getLog(id);
  if (!log) return { title: "Log not found" };

  const analysis = (log.analysis ?? null) as AnalysisData | null;
  const logTitle = analysis?.title || id;
  const content = log.content ?? "";
  const lineCount = content.split("\n").length;
  const errors = analysis?.errors ?? 0;
  const problems = analysis?.problems ?? [];

  const parts: string[] = [];
  parts.push(`${lineCount.toLocaleString()} line${lineCount === 1 ? "" : "s"}`);
  if (errors > 0) parts.push(`${errors.toLocaleString()} error${errors === 1 ? "" : "s"}`);
  if (problems.length > 0) parts.push(`${problems.length} problem${problems.length === 1 ? "" : "s"}`);

  const description = parts.length > 0
    ? `${parts.join(", ")}. Analyzed by mulch.`
    : "Analyzed by mulch.";

  return {
    title: logTitle,
    description,
  };
}

export default async function LogPage({ params }: LogPageProps) {
  const { id } = await params;
  const log = await getLog(id);

  if (!log) notFound();

  const cookieStore = await cookies();
  const initialSettings = parseSettingsCookie(
    cookieStore.get("IBLOGS_SETTINGS")?.value
  );
  const token = cookieStore.get(`iblogs_token_${id}`)?.value;
  const canDelete = !!token && !!log.token && token === log.token;

  const content = log.content ?? "";
  const lineCount = content.split("\n").length;
  const bytes = new TextEncoder().encode(content).length;
  const created =
    log.created instanceof Date ? log.created : new Date(log.created);

  const analysis = (log.analysis ?? null) as AnalysisData | null;
  const ready = analysis != null;
  const problems = analysis?.problems ?? [];
  const entries = analysis?.entries ?? [];
  const information = analysis?.information ?? [];
  const title = analysis?.title || id;

  const retentionLabel = `kept ${formatRetention(STORAGE_TTL)} from last view`;
  const abuseEmail = ABUSE_EMAIL;
  const createdMs = created.getTime();

  const headersList = await headers();
  const host = headersList.get("host");
  const displayUrl = host ? `${host}/${id}` : `/${id}`;

  return (
    <LogSettingsProvider initial={initialSettings}>
      <LogShell
        id={id}
        title={title}
        detected={analysis?.detected}
        createdLabel={created.toLocaleString()}
        source={log.source}
        metadata={log.metadata ?? []}
        information={information}
        content={content}
        lineCount={lineCount}
        bytes={bytes}
        errors={analysis?.errors ?? 0}
        problems={problems}
        entries={entries}
        canDelete={canDelete}
        createdMs={createdMs}
        displayUrl={displayUrl}
        retentionLabel={retentionLabel}
        abuseEmail={abuseEmail}
      />
      <AnalysisPoller id={id} ready={ready} />
    </LogSettingsProvider>
  );
}
