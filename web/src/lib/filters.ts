/**
 * Upload-time safety / PII filters.
 *
 * Port of the SAFE subset of the PHP pipeline in
 * `iblogs/src/Filter/*` (see `Filter::getAll()`), which runs over raw log
 * content BEFORE it is written to storage. The PHP order is:
 *
 *   [Trim, LimitBytes, LimitLines, ProjectZomboidRedactor, Username, AccessToken]
 *
 * ProjectZomboidRedactor is a PHP codex class
 * (`IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor`)
 * that scrubs Steam IDs, player names, coordinates and its own IP regexes.
 * It is NOT a portable regex and is therefore NOT reimplemented here.
 * Instead, `redactPzPii` calls the analyzer microservice's `/redact`
 * endpoint, which vendors codex-pz and runs the real redactor server-side.
 *
 * The remaining filters (Trim, LimitBytes, LimitLines, Username,
 * AccessToken) are pure string transforms with no PHP dependency and stay
 * in-process.
 */

// Raw content now lives in GridFS, so the upload cap is no longer pinned under
// MongoDB's 16 MB document limit. The remaining constraint is operational:
// analyzer request size and the cost of shipping + parsing the body.
export const DEFAULT_LIMIT_BYTES = parseInt(
  process.env.IBLOGS_STORAGE_LIMIT_BYTES || "52428800",
);
export const DEFAULT_LIMIT_LINES = parseInt(
  process.env.IBLOGS_STORAGE_LIMIT_LINES || "1000000",
);

const encoder = new TextEncoder();
const decoder = new TextDecoder();

/** Byte length of a string as UTF-8, matching PHP's byte-oriented limits. */
export function byteLength(content: string): number {
  return encoder.encode(content).length;
}

/** Line count using the same `\n` split PHP's LimitLinesFilter uses. */
export function lineCount(content: string): number {
  return content.split("\n").length;
}

/**
 * TrimFilter: strip leading/trailing whitespace. PHP `trim()` also drops a
 * leading UTF-8 BOM is not part of `trim()`, but a BOM is invisible PII-free
 * noise that breaks downstream parsing, so we strip it explicitly first.
 */
function trim(content: string): string {
  if (content.charCodeAt(0) === 0xfeff) {
    content = content.slice(1);
  }
  return content.trim();
}

/**
 * LimitBytesFilter: mirror PHP `mb_strcut($data, 0, $limit)` which truncates
 * at a UTF-8 byte boundary WITHOUT splitting a multibyte character.
 */
function limitBytes(content: string, limit: number): string {
  const bytes = encoder.encode(content);
  if (bytes.length <= limit) {
    return content;
  }
  // `fatal: false` (default) makes TextDecoder drop a trailing partial
  // multibyte sequence rather than emit U+FFFD, matching mb_strcut.
  return decoder.decode(bytes.subarray(0, limit));
}

/** LimitLinesFilter: keep the first `limit` newline-delimited lines. */
function limitLines(content: string, limit: number): string {
  return content.split("\n").slice(0, limit).join("\n");
}

interface Replacement {
  pattern: RegExp;
  replacement: string;
}

// UsernameFilter (iblogs/src/Filter/UsernameFilter.php). Patterns are CASELESS
// in PHP (PatternWithReplacement default modifier), hence the `i` flag.
const USERNAME_REPLACEMENTS: Replacement[] = [
  // C:\Users\<name>\  (windows)
  { pattern: /C:\\Users\\[^\\]+\\/gi, replacement: "C:\\Users\\********\\" },
  // C:\\Users\\<name>\\  (windows, double backslashes)
  {
    pattern: /C:\\\\Users\\\\[^\\]+\\\\/gi,
    replacement: "C:\\\\Users\\\\********\\\\",
  },
  // C:/Users/<name>/  (windows, forward slashes)
  { pattern: /C:\/Users\/[^/]+\//gi, replacement: "C:/Users/********/" },
  // /home/<name>/  (linux)
  { pattern: /(?<!\w)\/home\/[^/]+\//gi, replacement: "/home/********/" },
  // /Users/<name>/  (macos)
  { pattern: /(?<!\w)\/Users\/[^/]+\//gi, replacement: "/Users/********/" },
  // USERNAME=<name>  (environment variable)
  { pattern: /USERNAME=\w+/gi, replacement: "USERNAME=********" },
];

// AccessTokenFilter (iblogs/src/Filter/AccessTokenFilter.php). Also CASELESS.
const ACCESS_TOKEN_REPLACEMENTS: Replacement[] = [
  {
    pattern: /\(Session ID is token:[^:]+:[^)]+\)/gi,
    replacement:
      "(Session ID is token:****************:****************)",
  },
  {
    pattern: /--accessToken [^ ]+/gi,
    replacement: "--accessToken ****************:****************",
  },
  { pattern: /"authToken":"[^"]+"/gi, replacement: '"authToken":"****************"' },
  {
    pattern: /"refreshToken":"[^"]+"/gi,
    replacement: '"refreshToken":"****************"',
  },
];

function applyReplacements(content: string, rules: Replacement[]): string {
  for (const { pattern, replacement } of rules) {
    content = content.replace(pattern, replacement);
  }
  return content;
}

/**
 * Project Zomboid PII redaction. Delegates to the analyzer microservice's
 * `/redact` endpoint, which runs the real codex-pz `ProjectZomboidRedactor`
 * server-side. Returns the redacted content unchanged if the analyzer is
 * unreachable (fail-open) so a transient analyzer outage does not block
 * uploads - the alternative would silently drop user logs. The fail-open
 * is logged so an outage is visible in server logs.
 *
 * Set `IBLOGS_REDACT_DISABLED=1` to skip the round-trip entirely (e.g. for
 * dev work where the analyzer is intentionally not running). When disabled
 * the function is a true no-op and PZ PII is NOT scrubbed - callers that
 * require the guarantee must not set this env.
 */
const REDACT_DISABLED = process.env.IBLOGS_REDACT_DISABLED === "1";

export async function redactPzPii(content: string): Promise<string> {
  if (REDACT_DISABLED || content.length === 0) return content;
  const url = `${process.env.ANALYZER_URL || "http://analyzer:8080"}/redact`;
  let res: Response;
  try {
    res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "text/plain; charset=utf-8" },
      body: content,
    });
  } catch (e) {
    console.error("[filters] analyzer /redact unreachable, fail-open:", e);
    return content;
  }
  if (!res.ok) {
    console.error(`[filters] analyzer /redact returned ${res.status}, fail-open`);
    return content;
  }
  let body: { redacted?: string };
  try {
    body = (await res.json()) as { redacted?: string };
  } catch (e) {
    console.error("[filters] analyzer /redact invalid JSON, fail-open:", e);
    return content;
  }
  if (typeof body.redacted !== "string") {
    console.error("[filters] analyzer /redact missing 'redacted' field, fail-open");
    return content;
  }
  return body.redacted;
}

export interface UploadFilterOptions {
  limitBytes?: number;
  limitLines?: number;
}

/**
 * Run the SAFE upload filter pipeline in the same order as PHP's
 * `Filter::filterAll`, with the PZ redactor slot held by the analyzer call
 * in `redactPzPii`. Returns the filtered content.
 */
export async function applyUploadFilters(
  content: string,
  options: UploadFilterOptions = {},
): Promise<string> {
  const maxBytes = options.limitBytes ?? DEFAULT_LIMIT_BYTES;
  const maxLines = options.limitLines ?? DEFAULT_LIMIT_LINES;

  content = trim(content);
  content = limitBytes(content, maxBytes);
  content = limitLines(content, maxLines);
  content = await redactPzPii(content);
  content = applyReplacements(content, USERNAME_REPLACEMENTS);
  content = applyReplacements(content, ACCESS_TOKEN_REPLACEMENTS);
  return content;
}
