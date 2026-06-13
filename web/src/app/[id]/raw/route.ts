import { NextResponse } from "next/server";
import { getRawLog } from "@/lib/log-service";

export async function GET(
  _request: Request,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const content = await getRawLog(id);

  if (content === null) {
    return NextResponse.json({ error: "Not found" }, { status: 404 });
  }

  return new NextResponse(content, {
    headers: { "Content-Type": "text/plain; charset=utf-8" },
  });
}
