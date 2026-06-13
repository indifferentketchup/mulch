import { cookies } from "next/headers";
import { notFound } from "next/navigation";
import { getLog } from "@/lib/log-service";
import { LogShell } from "@/components/log/LogShell";
import { AnalysisPoller } from "@/components/log/AnalysisPoller";
import { LogSettingsProvider } from "@/components/log/LogSettingsContext";
import { parseSettingsCookie } from "@/lib/log-settings";
import type { AnalysisData } from "@/lib/types";

interface LogPageProps {
  params: Promise<{ id: string }>;
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

  const lineCount = log.content.split("\n").length;
  const bytes = new TextEncoder().encode(log.content).length;
  const created =
    log.created instanceof Date ? log.created : new Date(log.created);

  const analysis = (log.analysis ?? null) as AnalysisData | null;
  const ready = analysis != null;
  const problems = analysis?.problems ?? [];
  const entries = analysis?.entries ?? [];
  const information = analysis?.information ?? [];
  const title = analysis?.title || id;
  const noiseCount = problems.filter((p) => p.is_noise).length;

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
        content={log.content}
        lineCount={lineCount}
        bytes={bytes}
        problems={problems}
        entries={entries}
        noiseCount={noiseCount}
        canDelete={canDelete}
      />
      <AnalysisPoller id={id} ready={ready} />
    </LogSettingsProvider>
  );
}
