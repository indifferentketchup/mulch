#!/usr/bin/env python3
"""
pz_classify.py — Deterministic Project Zomboid log classifier orchestrator.

Walks ``*DebugLog-server*.txt`` files under the redacted-logs directory,
runs the pz_parser pipeline per file, merges records cross-file by their
deterministic ``signature``, and emits the spec-shaped JSON report.

Companion to the existing Qwen-backed discovery tool ``pz_error_analysis.py``
(left untouched). Zero AI dependency, stdlib-only, runs in seconds.

By convention the input is always the redacted directory produced by
``pz_redact_all.sh``; ``meta.redacted`` is therefore hard-coded ``true``.
If the user overrides ``--input`` to a non-redacted source we still emit
``true`` because we have no upstream way to verify redaction status.

Pipeline:
  parser.parse_file        per-file Entry list
  parser.classify_entries  per-file deduped Record list
  _merge_cross_file        global Record list deduped across files
  _build_summary           top-line stats + by_kind / by_attribution / top_mods

Output schema, CLI flags, and aggregation rules are defined in
``docs/superpowers/specs/2026-05-04-pz-deterministic-classifier-design.md``.
"""
from __future__ import annotations

import argparse
import dataclasses
import json
import sys
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

from pz_parser import (
    MAX_CAUSE_CHAIN_LEVELS,
    MAX_STACK_FRAMES,
    SEVERITY_LEVELS,
    Record,
    classify_entries,
    parse_file,
)

# ---------------------------------------------------------------------------
# Defaults / constants
# ---------------------------------------------------------------------------

_REPO_ROOT = Path(__file__).resolve().parents[2]
DEFAULT_INPUT: Path = _REPO_ROOT / ".scratch" / "pz" / "Logs.redacted"
DEFAULT_OUT: Path = _REPO_ROOT / ".scratch" / "pz" / "classify.json"

#: Filename glob driving the directory walk.
INPUT_GLOB: str = "*DebugLog-server*.txt"
#: Cap on entries in ``summary.top_mods`` — most occurrence-count-heavy mods.
TOP_MODS_LIMIT: int = 10

#: Confidence / attribution promotion ladders (higher rank wins on merge).
_CONFIDENCE_RANK: dict[str, int] = {"low": 0, "medium": 1, "high": 2}
_ATTRIBUTION_RANK: dict[str, int] = {
    "unattributed": 0,
    "inferred": 1,
    "direct": 2,
}
#: Levels that count as errors (vs warnings) in the summary.
_ERROR_LEVELS: frozenset[str] = frozenset({"ERROR", "SEVERE", "FATAL"})


# ---------------------------------------------------------------------------
# Cross-file aggregation (spec §9, inter-file equivalent of parser dedup)
# ---------------------------------------------------------------------------


def _merge_cross_file(per_file_records: list[Record]) -> list[Record]:
    """Merge ``Record`` instances across files by ``signature``.

    The parser already dedups within a single file. This is the inter-file
    equivalent: when the same signature appears in records from multiple
    files, sum occurrences, union file lists, promote attribution/confidence,
    and merge stack and cause-chain (deduped, capped at parser constants).
    First-seen is the earliest by file-then-line; since callers feed records
    in sorted file order, the first record we encounter per signature is
    already the earliest.
    """
    by_signature: dict[str, Record] = {}
    for incoming in per_file_records:
        existing = by_signature.get(incoming.signature)
        if existing is None:
            # First occurrence — copy so we don't mutate the caller's list.
            by_signature[incoming.signature] = Record(
                signature=incoming.signature,
                pattern_id=incoming.pattern_id,
                level=incoming.level,
                kind=incoming.kind,
                mod_id=incoming.mod_id,
                mod_name=incoming.mod_name,
                attribution=incoming.attribution,
                confidence=incoming.confidence,
                attribution_reason=incoming.attribution_reason,
                file=incoming.file,
                line=incoming.line,
                cause_chain=incoming.cause_chain,
                stack=list(incoming.stack),
                first_seen=incoming.first_seen,
                occurrence_count=incoming.occurrence_count,
                files=list(incoming.files),
                excerpt=incoming.excerpt,
            )
            continue
        # Aggregate.
        existing.occurrence_count += incoming.occurrence_count
        for fname in incoming.files:
            if fname not in existing.files:
                existing.files.append(fname)
        # Promote attribution / confidence / mod_name on stronger evidence.
        if _ATTRIBUTION_RANK[incoming.attribution] > _ATTRIBUTION_RANK[existing.attribution]:
            existing.attribution = incoming.attribution
            existing.attribution_reason = incoming.attribution_reason
            if incoming.mod_name:
                existing.mod_name = incoming.mod_name
        if _CONFIDENCE_RANK[incoming.confidence] > _CONFIDENCE_RANK[existing.confidence]:
            existing.confidence = incoming.confidence
        # Merge stack frames preserving order, capped.
        for frame in incoming.stack:
            if frame not in existing.stack and len(existing.stack) < MAX_STACK_FRAMES:
                existing.stack.append(frame)
        # Merge cause chain (deduped tokens, capped).
        if incoming.cause_chain and incoming.cause_chain != existing.cause_chain:
            old = existing.cause_chain.split(" -> ") if existing.cause_chain else []
            new = incoming.cause_chain.split(" -> ")
            merged = list(old)
            for tok in new:
                if tok and tok not in merged:
                    merged.append(tok)
            existing.cause_chain = " -> ".join(merged[:MAX_CAUSE_CHAIN_LEVELS])
    return list(by_signature.values())


# ---------------------------------------------------------------------------
# Summary computation
# ---------------------------------------------------------------------------


def _build_summary(records: list[Record]) -> dict[str, object]:
    """Build the ``summary`` block per spec.

    Counts records (signatures), not raw occurrences, except for ``top_mods``
    which sums ``occurrence_count`` per mod_id so that volume-driving mods
    surface even when they hit the same shape repeatedly.
    """
    errors = sum(1 for r in records if r.level in _ERROR_LEVELS)
    warnings = sum(1 for r in records if r.level == "WARN")
    by_kind = Counter(r.kind for r in records)
    by_attribution = Counter(r.attribution for r in records)
    by_confidence = Counter(r.confidence for r in records)

    # Group by mod_id summing total occurrence_count; preserve any mod_name.
    mod_totals: dict[str, int] = {}
    mod_names: dict[str, str] = {}
    for r in records:
        mod_totals[r.mod_id] = mod_totals.get(r.mod_id, 0) + r.occurrence_count
        # First non-empty mod_name wins; subsequent records may have empty
        # mod_name (e.g. for unattributed) so don't overwrite with "".
        if r.mod_name and r.mod_id not in mod_names:
            mod_names[r.mod_id] = r.mod_name
    top_mods = sorted(
        (
            {
                "mod_id": mod_id,
                "mod_name": mod_names.get(mod_id, ""),
                "occurrence_count": total,
            }
            for mod_id, total in mod_totals.items()
        ),
        key=lambda d: d["occurrence_count"],
        reverse=True,
    )[:TOP_MODS_LIMIT]

    return {
        "errors": errors,
        "warnings": warnings,
        "by_kind": dict(by_kind),
        "by_attribution": dict(by_attribution),
        "by_confidence": dict(by_confidence),
        "top_mods": top_mods,
    }


# ---------------------------------------------------------------------------
# Driver
# ---------------------------------------------------------------------------


def _run(input_dir: Path, out_path: Path, *, quiet: bool) -> int:
    if not input_dir.is_dir():
        print(
            f"pz_classify: --input directory not found: {input_dir}",
            file=sys.stderr,
        )
        return 2

    started = datetime.now(timezone.utc).isoformat(timespec="seconds")
    files = sorted(input_dir.glob(INPUT_GLOB))

    all_records: list[Record] = []
    log_lines_total = 0
    error_lines_total = 0

    for path in files:
        try:
            entries = parse_file(path)
        except Exception as exc:  # noqa: BLE001 — orchestrator must keep going.
            print(
                f"pz_classify: warning: failed to parse {path.name}: {exc}",
                file=sys.stderr,
            )
            continue
        # Body-line totals: every line under every parsed entry contributes
        # to log_lines_total; severity-level entries' body lines feed
        # error_lines_total. Counted before dedup so it reflects raw volume.
        for e in entries:
            log_lines_total += len(e.body)
            if e.level in SEVERITY_LEVELS:
                error_lines_total += len(e.body)
        all_records.extend(classify_entries(entries, source_file=path.name))

    merged = _merge_cross_file(all_records)
    merged.sort(key=lambda r: r.occurrence_count, reverse=True)

    finished = datetime.now(timezone.utc).isoformat(timespec="seconds")

    unique_patterns = len({r.pattern_id for r in merged})

    document: dict[str, object] = {
        "meta": {
            "input_dir": str(input_dir),
            "files_scanned": len(files),
            "log_lines_total": log_lines_total,
            "error_lines_total": error_lines_total,
            "unique_signatures": len(merged),
            "unique_patterns": unique_patterns,
            "redacted": True,
            "started": started,
            "finished": finished,
        },
        "signatures": [dataclasses.asdict(r) for r in merged],
        "summary": _build_summary(merged),
    }

    tmp = out_path.with_suffix(out_path.suffix + ".tmp")
    try:
        out_path.parent.mkdir(parents=True, exist_ok=True)
        with tmp.open("w", encoding="utf-8") as f:
            json.dump(document, f, ensure_ascii=False, indent=2)
            f.write("\n")
        tmp.replace(out_path)
    except OSError as exc:
        print(f"pz_classify: failed to write {out_path}: {exc}", file=sys.stderr)
        # Best-effort cleanup of the temp file.
        try:
            tmp.unlink()
        except OSError:
            pass
        return 1

    if not quiet:
        print(
            f"pz_classify: {len(files)} file(s), {log_lines_total} log lines, "
            f"{error_lines_total} error lines, {len(merged)} records "
            f"({unique_patterns} unique patterns) -> {out_path}"
        )
    return 0


def _parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        prog="pz_classify",
        description=(
            "Deterministic Project Zomboid log classifier. Walks redacted "
            "DebugLog-server*.txt files, classifies errors/warnings, and "
            "emits a JSON report."
        ),
    )
    parser.add_argument(
        "--input",
        type=Path,
        default=DEFAULT_INPUT,
        help=f"Input directory of redacted log files (default: {DEFAULT_INPUT}).",
    )
    parser.add_argument(
        "--out",
        type=Path,
        default=DEFAULT_OUT,
        help=f"Output JSON path (default: {DEFAULT_OUT}).",
    )
    parser.add_argument(
        "--quiet",
        action="store_true",
        help="Suppress the trailing one-line summary.",
    )
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    args = _parse_args(argv)
    return _run(args.input, args.out, quiet=args.quiet)


if __name__ == "__main__":
    sys.exit(main())
