// Groups the flat `information` list from the analyzer into the same buckets the
// old PHP frontend rendered: engine version (+ build hash), loaded mods, grouped
// asset warnings, and everything else ("Detected"). The analyzer returns plain
// {label, value} items, so we classify by label rather than by PHP's concrete
// Insight class.

import type { InfoItem, ProblemData } from "./types";

export interface GroupedInfo {
  engineVersion: InfoItem | null;
  buildHash: string | null;
  mods: InfoItem[];
  /** Asset warnings collapsed to one row per label, e.g. "Missing icon ×40". */
  assetGroups: { label: string; count: number }[];
  /** Engine-version + any other non-mod, non-asset detected info. */
  other: InfoItem[];
}

const ASSET_LABEL = /^(missing icon|missing thumpsound|missing sound|invalid sprite|sprite)/i;

export function groupInformation(information: InfoItem[]): GroupedInfo {
  let engineVersion: InfoItem | null = null;
  let buildHash: string | null = null;
  const mods: InfoItem[] = [];
  const other: InfoItem[] = [];
  const assetCounts = new Map<string, number>();

  for (const info of information) {
    const label = info.label ?? "";
    if (/^engine version/i.test(label)) {
      engineVersion = info;
      const m = info.value.match(/build\s+([a-f0-9]+)/i);
      if (m) buildHash = m[1].slice(0, 12);
    } else if (/^mod loaded/i.test(label)) {
      mods.push(info);
    } else if (ASSET_LABEL.test(label)) {
      assetCounts.set(label, (assetCounts.get(label) ?? 0) + 1);
    } else {
      other.push(info);
    }
  }

  if (engineVersion) other.push(engineVersion);

  const assetGroups = [...assetCounts.entries()].map(([label, count]) => ({
    label,
    count,
  }));

  return { engineVersion, buildHash, mods, assetGroups, other };
}

// Asset-warning groups render as low-severity gated rows in the problems panel
// (hidden by default), mirroring the PHP behaviour.
export function assetGroupsToProblems(
  groups: { label: string; count: number }[]
): ProblemData[] {
  return groups.map((g) => ({
    message: g.label,
    severity: "Low",
    count: g.count,
    entry_line: null,
    is_noise: true,
    kind: "unknown",
    attribution: "unattributed",
    rank: 5,
    gated: true,
    solutions: [],
  }));
}
