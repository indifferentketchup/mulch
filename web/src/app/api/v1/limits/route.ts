import { NextResponse } from "next/server";
import { LIMIT_BYTES, LIMIT_LINES } from "@/lib/log-service";

// GET /api/v1/limits - the upload caps a client should honor before POSTing.
export async function GET() {
  return NextResponse.json({
    success: true,
    bytes: LIMIT_BYTES,
    lines: LIMIT_LINES,
  });
}
