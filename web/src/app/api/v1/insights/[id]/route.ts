import { NextResponse } from "next/server";
import { getLog } from "@/lib/log-service";

// GET /api/v1/insights/{id} - the analysis only (problems + information +
// counts), without the raw log body. Mirrors the PHP /1/insights endpoint.
export async function GET(
  _request: Request,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const log = await getLog(id);
  if (!log) {
    return NextResponse.json({ success: false, error: "Not found" }, { status: 404 });
  }
  if (!log.analysis) {
    // Analysis runs asynchronously after upload; signal "not ready yet".
    return NextResponse.json({ success: true, ready: false, analysis: null });
  }
  const a = log.analysis;
  return NextResponse.json({
    success: true,
    ready: true,
    detected: a.detected,
    title: a.title,
    lines: a.lines,
    errors: a.errors,
    analysis: { problems: a.problems, information: a.information, gated: a.gated ?? [] },
  });
}
