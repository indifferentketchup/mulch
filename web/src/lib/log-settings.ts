// Server- and client-safe log viewer settings: keys, metadata, defaults, and
// the cookie parser. No React and no "use client" here so the server component
// can seed the provider from the request cookie. The provider/hook live in
// components/log/LogSettingsContext.tsx.

export type LogSettingKey =
  | "fullWidth"
  | "noWrap"
  | "overflow"
  | "floatingScrollbar"
  | "hideEngineNoise"
  | "showAllEntries";

export type LogSettingsState = Record<LogSettingKey, boolean>;

export interface LogSettingMeta {
  key: LogSettingKey;
  label: string;
  desc: string;
  group: "Layout" | "Content";
}

export const SETTINGS: LogSettingMeta[] = [
  { key: "fullWidth", label: "Full Width", desc: "Remove the centered container to use the full viewport width.", group: "Layout" },
  { key: "noWrap", label: "No Wrap", desc: "Disable line wrapping to show each log line as a single horizontal row.", group: "Layout" },
  { key: "overflow", label: "Overflow", desc: "Let wide log lines overflow the page width instead of scrolling inside the panel.", group: "Layout" },
  { key: "floatingScrollbar", label: "Floating Scrollbar", desc: "Show a sticky bottom scrollbar for navigating wide, unwrapped log files.", group: "Layout" },
  { key: "hideEngineNoise", label: "Hide Engine Noise", desc: "Filter low-severity engine noise out of the problem panel.", group: "Content" },
  { key: "showAllEntries", label: "Show All Entries", desc: "Disable smart folding to show every log entry, including info and debug lines.", group: "Content" },
];

export const DEFAULT_SETTINGS: LogSettingsState = {
  fullWidth: false,
  noWrap: false,
  overflow: false,
  floatingScrollbar: false,
  hideEngineNoise: true,
  showAllEntries: false,
};

export const SETTINGS_COOKIE_NAME = "IBLOGS_SETTINGS";

/**
 * Parse the IBLOGS_SETTINGS cookie value (URL-encoded JSON) into a partial
 * settings object. Used on the server to seed the provider so the first paint
 * already reflects the user's saved toggles (no hydration flash). Unknown or
 * non-boolean keys are dropped.
 */
export function parseSettingsCookie(
  raw: string | undefined
): Partial<LogSettingsState> {
  if (!raw) return {};
  try {
    const parsed = JSON.parse(decodeURIComponent(raw));
    if (parsed && typeof parsed === "object") {
      const out: Partial<LogSettingsState> = {};
      for (const meta of SETTINGS) {
        if (typeof parsed[meta.key] === "boolean") {
          out[meta.key] = parsed[meta.key];
        }
      }
      return out;
    }
  } catch {
    /* malformed cookie; fall back to defaults */
  }
  return {};
}
