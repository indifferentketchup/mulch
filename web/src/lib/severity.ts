// Severity maps to the colorblind-safe Okabe-Ito ramp defined in globals.css.
// critical -> red, high -> orange, medium -> amber, low -> info blue.
// Used by the problem readout and the log gutter ticks so a severity reads
// the same color everywhere.

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
    default:
      return "var(--sev-medium)";
  }
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
    default:
      return 2;
  }
}
