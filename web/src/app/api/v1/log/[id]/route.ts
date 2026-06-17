import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { getLog, deleteLog } from "@/lib/log-service";

// GET /api/v1/log/{id} - the stored log plus metadata and analysis.
export async function GET(
  _request: Request,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const log = await getLog(id);
  if (!log) {
    return NextResponse.json({ success: false, error: "Not found" }, { status: 404 });
  }
  const content = log.content ?? "";
  return NextResponse.json({
    success: true,
    id: log._id,
    content,
    source: log.source,
    metadata: log.metadata,
    analysis: log.analysis || null,
    created: log.created instanceof Date ? log.created.toISOString() : log.created,
    lines: content.split("\n").length,
    bytes: new TextEncoder().encode(content).length,
  });
}

// DELETE /api/v1/log/{id} - requires the owner token cookie.
export async function DELETE(
  _request: Request,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const cookieStore = await cookies();
  const token = cookieStore.get(`iblogs_token_${id}`)?.value;
  if (!token) {
    return NextResponse.json({ success: false, error: "Unauthorized" }, { status: 401 });
  }
  const deleted = await deleteLog(id, token);
  if (!deleted) {
    return NextResponse.json(
      { success: false, error: "Not found or unauthorized" },
      { status: 404 }
    );
  }
  return NextResponse.json({ success: true });
}
