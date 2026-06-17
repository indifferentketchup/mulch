// Severity maps to the WARM ramp defined in globals.css.
// critical -> red mix, high -> orange #ff8c42, medium -> amber, low -> muted, noise -> ghost.
// Used by the problem readout and the log gutter ticks so a severity reads
// the same color everywhere.
//
// NOTE: "Noise" is a live enum case in codex-pz Severity (value 5, below Low=20).
// analyze.php emits it via $problem->getSeverity()->name, so the frontend must handle it.

export function severityVar(severity: string): string {
  switch (severity) {
    case "Critical":
      return "var(--sev-critical)";
    case "High":
      return "var(--sev-high)";
    case "Medium":
      return "var(--sev-medium)";
    case "Low":
      return "var(--sev-low)";
    case "Noise":
      return "var(--sev-noise)";
    default:
      return "var(--sev-medium)";
  }
}

// Per-severity border tint for the whole problem row. A 40% mix of the severity
// hue over the panel keeps the border legible without becoming a solid alarm bar.
export function severityBorderVar(severity: string): string {
  return `color-mix(in srgb, ${severityVar(severity)} 40%, transparent)`;
}

export function severityBgVar(severity: string): string {
  switch (severity) {
    case "Critical":
      return "var(--sev-critical-bg)";
    case "High":
      return "var(--sev-high-bg)";
    case "Medium":
      return "var(--sev-medium-bg)";
    case "Low":
      return "var(--sev-low-bg)";
    case "Noise":
      return "var(--sev-noise-bg)";
    default:
      return "var(--sev-medium-bg)";
  }
}

// Rank for picking the most severe problem on a shared line (lower = worse).
export function severityRank(severity: string): number {
  switch (severity) {
    case "Critical":
      return 0;
    case "High":
      return 1;
    case "Medium":
      return 2;
    case "Low":
      return 3;
    case "Noise":
      return 4;
    default:
      return 2;
  }
}
