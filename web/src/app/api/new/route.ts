import { NextResponse } from "next/server";
import { createLog } from "@/lib/log-service";
import type { CreateLogRequest } from "@/lib/types";

const LIMIT_BYTES = parseInt(process.env.IBLOGS_STORAGE_LIMIT_BYTES || "10485760");
const LIMIT_LINES = parseInt(process.env.IBLOGS_STORAGE_LIMIT_LINES || "25000");

export async function POST(request: Request) {
  try {
    const body: CreateLogRequest = await request.json();

    if (!body.content || body.content.trim().length === 0) {
      return NextResponse.json(
        { success: false, error: "Content is required." },
        { status: 400 }
      );
    }

    const bytes = new TextEncoder().encode(body.content).length;
    if (bytes > LIMIT_BYTES) {
      return NextResponse.json(
        { success: false, error: `Log is too large. Maximum ${(LIMIT_BYTES / 1024 / 1024).toFixed(0)} MB.` },
        { status: 413 }
      );
    }

    const lines = body.content.split("\n").length;
    if (lines > LIMIT_LINES) {
      return NextResponse.json(
        { success: false, error: `Log has too many lines. Maximum ${LIMIT_LINES} lines.` },
        { status: 413 }
      );
    }

    const { id, token } = await createLog(body);

    const response = NextResponse.json({
      success: true,
      id,
      url: `/${id}`,
    });

    response.cookies.set(`iblogs_token_${id}`, token, {
      path: "/",
      httpOnly: true,
      sameSite: "lax",
      maxAge: 90 * 24 * 60 * 60,
    });

    return response;
  } catch (e) {
    console.error("Create log error:", e);
    return NextResponse.json(
      { success: false, error: "Internal server error." },
      { status: 500 }
    );
  }
}
