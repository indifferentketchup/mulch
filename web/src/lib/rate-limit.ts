/**
 * Per-IP sliding-window rate limiter.
 *
 * CAVEAT: state lives in this module's process memory. It is correct only for a
 * SINGLE app instance. Behind multiple replicas / a horizontally scaled deploy,
 * each instance keeps its own window and the effective limit is multiplied by
 * the replica count. For multi-instance enforcement this must move to a shared
 * store (Redis, Mongo TTL collection, etc). The PHP app enforced rate limits at
 * the edge; this is the Next port's in-process stand-in.
 */

const WINDOW_MS = parseInt(process.env.IBLOGS_RATE_LIMIT_WINDOW_MS || "60000");
const MAX_REQUESTS = parseInt(process.env.IBLOGS_RATE_LIMIT_MAX || "30");

// Cap the number of tracked keys so a flood of distinct IPs cannot grow the map
// without bound. When exceeded, the oldest-touched keys are evicted.
const MAX_KEYS = 50000;

interface Bucket {
  // Sorted ascending request timestamps (ms) inside the current window.
  hits: number[];
}

interface RateLimitGlobal {
  _iblogsRateLimit?: Map<string, Bucket>;
}

const globalWithRl = globalThis as unknown as RateLimitGlobal;
const buckets: Map<string, Bucket> =
  globalWithRl._iblogsRateLimit ?? (globalWithRl._iblogsRateLimit = new Map());

export interface RateLimitResult {
  allowed: boolean;
  limit: number;
  remaining: number;
  /** Seconds until the caller may retry; only meaningful when !allowed. */
  retryAfter: number;
}

/**
 * Record a hit for `key` and report whether it is within the sliding window
 * limit. A request is counted only when allowed, so a blocked client cannot
 * push its own window further out by hammering the endpoint.
 */
export function rateLimit(
  key: string,
  limit: number = MAX_REQUESTS,
  windowMs: number = WINDOW_MS,
): RateLimitResult {
  const now = Date.now();
  const windowStart = now - windowMs;

  let bucket = buckets.get(key);
  if (!bucket) {
    if (buckets.size >= MAX_KEYS) {
      // Map preserves insertion order; drop the oldest entry.
      const oldest = buckets.keys().next().value;
      if (oldest !== undefined) {
        buckets.delete(oldest);
      }
    }
    bucket = { hits: [] };
    buckets.set(key, bucket);
  }

  // Drop hits that have aged out of the window.
  while (bucket.hits.length > 0 && bucket.hits[0] <= windowStart) {
    bucket.hits.shift();
  }

  if (bucket.hits.length >= limit) {
    const oldest = bucket.hits[0];
    const retryAfter = Math.max(1, Math.ceil((oldest + windowMs - now) / 1000));
    return { allowed: false, limit, remaining: 0, retryAfter };
  }

  bucket.hits.push(now);
  // Refresh insertion order so active keys are not evicted as "oldest".
  buckets.delete(key);
  buckets.set(key, bucket);

  return {
    allowed: true,
    limit,
    remaining: Math.max(0, limit - bucket.hits.length),
    retryAfter: 0,
  };
}

/**
 * Best-effort client IP from standard proxy headers, falling back to a shared
 * bucket key when no address is available (so the limiter still degrades to a
 * global cap rather than failing open per-request).
 */
export function clientIp(request: Request): string {
  const forwarded = request.headers.get("x-forwarded-for");
  if (forwarded) {
    const first = forwarded.split(",")[0]?.trim();
    if (first) {
      return first;
    }
  }
  return request.headers.get("x-real-ip")?.trim() || "unknown";
}
