import { NextResponse } from "next/server";
import { createLog, LogValidationError } from "@/lib/log-service";
import { rateLimit, clientIp } from "@/lib/rate-limit";
import type { CreateLogRequest } from "@/lib/types";

const COOKIE_MAX_AGE = 90 * 24 * 60 * 60;

// Stable v1 alias of POST /api/new. Returns the same shape plus the raw URL,
// mirroring the PHP /1/log endpoint.
export async function POST(request: Request) {
  const rl = rateLimit(clientIp(request));
  if (!rl.allowed) {
    return NextResponse.json(
      { success: false, error: "Rate limit exceeded. Please try again shortly." },
      { status: 429, headers: { "Retry-After": String(rl.retryAfter) } }
    );
  }

  try {
    const body: CreateLogRequest = await request.json();
    const { id, token } = await createLog(body);
    const response = NextResponse.json({
      success: true,
      id,
      url: `/${id}`,
      raw: `/api/v1/log/${id}/raw`,
    });
    response.cookies.set(`iblogs_token_${id}`, token, {
      path: "/",
      httpOnly: true,
      sameSite: "lax",
      maxAge: COOKIE_MAX_AGE,
    });
    return response;
  } catch (e) {
    if (e instanceof LogValidationError) {
      return NextResponse.json({ success: false, error: e.message }, { status: e.status });
    }
    console.error("v1 create log error:", e);
    return NextResponse.json(
      { success: false, error: "Internal server error." },
      { status: 500 }
    );
  }
}
