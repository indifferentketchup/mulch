import { NextResponse } from "next/server";
import { createLog, LogValidationError } from "@/lib/log-service";
import { rateLimit, clientIp } from "@/lib/rate-limit";
import type { CreateLogRequest } from "@/lib/types";

const COOKIE_MAX_AGE = 90 * 24 * 60 * 60;

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
    // createLog validates (LogValidationError) and runs the upload-filter
    // pipeline before persisting, so the route stays thin.
    const { id, token } = await createLog(body);

    const response = NextResponse.json({ success: true, id, url: `/${id}` });
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
    console.error("Create log error:", e);
    return NextResponse.json(
      { success: false, error: "Internal server error." },
      { status: 500 }
    );
  }
}
