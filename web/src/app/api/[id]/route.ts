import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { getLog, deleteLog } from "@/lib/log-service";

export async function GET(
  _request: Request,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const log = await getLog(id);

  if (!log) {
    return NextResponse.json({ error: "Not found" }, { status: 404 });
  }

  return NextResponse.json({
    id: log._id,
    content: log.content,
    source: log.source,
    metadata: log.metadata,
    analysis: log.analysis || null,
    created: log.created instanceof Date ? log.created.toISOString() : log.created,
    lines: log.content.split("\n").length,
    bytes: new TextEncoder().encode(log.content).length,
  });
}

export async function DELETE(
  _request: Request,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const cookieStore = await cookies();
  const token = cookieStore.get("iblogs_token")?.value;

  if (!token) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const deleted = await deleteLog(id, token);
  if (!deleted) {
    return NextResponse.json({ error: "Not found or unauthorized" }, { status: 404 });
  }

  return NextResponse.json({ success: true });
}
