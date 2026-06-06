#!/usr/bin/env python3
"""
pz_error_analysis.py — Qwen-backed Project Zomboid error analyzer.

Walks `*DebugLog-server*.txt` files (DEFAULT_INPUT — already PII-redacted by
pz_redact_all.sh), groups WARN/ERROR/FATAL entries with surrounding context,
deduplicates by signature hash, and asks Qwen to classify each unique
signature into a fixed taxonomy (missing_mod, java_exception, lua_error,
out_of_memory, ...) with a short title / summary / likely_cause /
suggested_fix / confidence.

Standalone: requires Python 3.10+ and the `openai` package
(`pip install openai>=1.30`). Talks to a local OpenAI-compatible endpoint
(default sam-desktop llama-swap on port 8401); override with QWEN_BASE_URL
and QWEN_MODEL env vars.
"""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import os
import re
import sys
import time
from pathlib import Path
from typing import Any, Iterator

from openai import OpenAI

_REPO_ROOT = Path(__file__).resolve().parents[2]

DEFAULT_INPUT = _REPO_ROOT / ".scratch" / "pz" / "Logs.redacted"
DEFAULT_OUT = _REPO_ROOT / ".scratch" / "pz" / "analysis.json"

# --- Qwen client (inlined from /opt/analytics/ib_analytics/llm/local_client.py
#     so this script has no cross-repo dependency; mirror upstream changes if
#     the analytics client API evolves) ---

QWEN_DEFAULT_BASE_URL = "http://100.101.41.16:8401/v1"
QWEN_DEFAULT_MODEL = "qwen3.6-35b-a3b"

SAMPLING_STRUCTURED: dict[str, Any] = {
    "temperature": 0.7,
    "top_p": 0.80,
    "extra_body": {
        "top_k": 20,
        "presence_penalty": 1.5,
        "chat_template_kwargs": {"enable_thinking": False},
    },
}


def get_client() -> OpenAI:
    return OpenAI(
        base_url=os.environ.get("QWEN_BASE_URL", QWEN_DEFAULT_BASE_URL),
        api_key="EMPTY",
    )


def get_model() -> str:
    return os.environ.get("QWEN_MODEL", QWEN_DEFAULT_MODEL)


def structured_call(
    tool_schema: dict[str, Any],
    messages: list[dict[str, Any]],
    *,
    sampling: dict[str, Any] = SAMPLING_STRUCTURED,
    client: OpenAI | None = None,
    model: str | None = None,
    max_tokens: int = 4096,
) -> dict[str, Any]:
    cli = client or get_client()
    mdl = model or get_model()
    fn_name = tool_schema["function"]["name"]
    kwargs = dict(sampling)
    extra_body = dict(kwargs.pop("extra_body", {}))
    response = cli.chat.completions.create(
        model=mdl,
        messages=messages,
        tools=[tool_schema],
        tool_choice="required",
        max_tokens=max_tokens,
        extra_body=extra_body,
        **kwargs,
    )
    choice = response.choices[0]
    tool_calls = getattr(choice.message, "tool_calls", None) or []
    if not tool_calls:
        raise ValueError(
            f"Qwen did not invoke {fn_name}; finish_reason={choice.finish_reason}, "
            f"content={(choice.message.content or '')[:500]}"
        )
    call = tool_calls[0]
    if call.function.name != fn_name:
        raise ValueError(
            f"Qwen invoked unexpected tool {call.function.name!r}; expected {fn_name!r}"
        )
    try:
        return json.loads(call.function.arguments)
    except json.JSONDecodeError as e:
        raise ValueError(
            f"Malformed tool-call arguments for {fn_name}: {e}; "
            f"raw={call.function.arguments[:500]}"
        ) from e


# --- Parser ---

ENTRY_RE = re.compile(
    r"^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\]\s+"
    r"(LOG|WARN|ERROR|FATAL)\s*:\s*(.*)"
)
SESSION_META_RE = re.compile(r"^[A-Za-z]+\s+f:\d+,?\s*(?:t:\d+,?\s*)?st:[\d,]+>\s*")
DOUBLE_QUOTED_RE = re.compile(r'"[^"]*"')
SINGLE_QUOTED_RE = re.compile(r"'[^']*'")
NUMERIC_RUN_RE = re.compile(r"\d{2,}")
WS_RUN_RE = re.compile(r"\s+")

CATEGORIES = [
    "missing_mod", "mod_conflict", "lua_error", "java_exception",
    "out_of_memory", "corrupt_save", "network_error", "load_order",
    "performance", "server_crash", "unknown",
]

TOOL_SCHEMA: dict[str, Any] = {
    "type": "function",
    "function": {
        "name": "submit_error_analysis",
        "description": (
            "Analyse a single Project Zomboid server error block and emit "
            "structured insight."
        ),
        "parameters": {
            "type": "object",
            "properties": {
                "category": {"type": "string", "enum": CATEGORIES},
                "severity": {"type": "string", "enum": ["problem", "warning", "info"]},
                "title": {"type": "string", "description": "One-line headline (<=80 chars)"},
                "summary": {"type": "string", "description": "1-3 sentences explaining what happened"},
                "likely_cause": {"type": "string", "description": "Most plausible cause given the context"},
                "suggested_fix": {"type": "string", "description": "Concrete remediation, server-admin actionable"},
                "confidence": {"type": "number", "minimum": 0.0, "maximum": 1.0},
            },
            "required": [
                "category", "severity", "title", "summary",
                "likely_cause", "suggested_fix", "confidence",
            ],
        },
    },
}

SYSTEM_PROMPT = """You are a Project Zomboid dedicated server administrator
diagnosing a server log. You receive one error/warning event with surrounding
context (entries marked with `>>>` are the hit; the rest are leading or
trailing context). Classify the event using the submit_error_analysis tool
ONLY — never reply in plain text.

Rules:
- `category` must be one of the enum values; choose `unknown` only if no
  other fits.
- `severity`: problem = breaks something users notice; warning = degraded
  but functional; info = noteworthy but not failing.
- `title`: at most 80 chars, neutral and specific.
- `suggested_fix`: a concrete admin action ("subscribe to mod X", "increase
  -Xmx to 8G", "remove the conflicting mod from Mods= line"), not generic
  advice.
- `confidence`: 0.0-1.0; lower it when the evidence is ambiguous.
"""

MAX_PROMPT_CHARS = 4000


def parse_file(path: Path) -> list[dict[str, Any]]:
    """Parse a DebugLog-server file into a list of multi-line entries.

    Continuation lines (lines that don't match ENTRY_RE) append to the
    previous entry, mirroring codex's PatternParser behaviour.
    """
    entries: list[dict[str, Any]] = []
    current: dict[str, Any] | None = None
    with path.open("r", encoding="utf-8", errors="replace") as f:
        for lineno, raw in enumerate(f, start=1):
            line = raw.rstrip("\n")
            m = ENTRY_RE.match(line)
            if m:
                if current is not None:
                    entries.append(current)
                current = {
                    "timestamp": m.group(1),
                    "level": m.group(2),
                    "body": [m.group(3)],
                    "line_start": lineno,
                    "line_end": lineno,
                }
            elif current is not None:
                current["body"].append(line)
                current["line_end"] = lineno
            # else: orphan line at start of file (no preceding entry); ignore.
    if current is not None:
        entries.append(current)
    return entries


def signature_for(level: str, body_lines: list[str]) -> str:
    """Stable signature derived from the first body line only.

    Stack-trace continuations are deliberately ignored: the same logical
    exception can produce slightly different traces (e.g. timing-related
    code paths) but should still collapse to one signature. Quoted strings
    (vehicle names, mod IDs, paths) are flattened to <S>; numeric runs of
    length >= 2 are flattened to <N>; session-metadata prefix
    (`General  f:0,t:N,st:N,N,N>`) is stripped.
    """
    first = (body_lines[0] if body_lines else "").strip()
    first = SESSION_META_RE.sub("", first)
    first = DOUBLE_QUOTED_RE.sub('"<S>"', first)
    first = SINGLE_QUOTED_RE.sub("'<S>'", first)
    first = NUMERIC_RUN_RE.sub("<N>", first)
    first = WS_RUN_RE.sub(" ", first)
    first = first[:200]
    h = hashlib.sha256(f"{level}\n{first}".encode("utf-8")).hexdigest()
    return f"sha256:{h[:16]}"


def build_excerpt(
    entries: list[dict[str, Any]], hit_idx: int, context: int
) -> str:
    """Render an excerpt centered on entries[hit_idx] with ±context entries."""
    start = max(0, hit_idx - context)
    end = min(len(entries), hit_idx + context + 1)
    lines: list[str] = []
    for i in range(start, end):
        e = entries[i]
        is_hit = i == hit_idx
        marker = ">>>" if is_hit else "   "
        prefix = f'{marker} [{e["timestamp"]}] {e["level"]}: '
        body = e["body"]
        if is_hit:
            for j, body_line in enumerate(body):
                lines.append(prefix + body_line if j == 0 else "       " + body_line)
        else:
            first = (body[0] if body else "").strip()[:200]
            lines.append(prefix + first)
            if len(body) > 1:
                lines.append(f'       ... (+{len(body) - 1} more lines)')
    excerpt = "\n".join(lines)
    if len(excerpt) > MAX_PROMPT_CHARS:
        excerpt = excerpt[:MAX_PROMPT_CHARS] + "\n... [truncated]"
    return excerpt


def iter_warn_or_error(entries: list[dict[str, Any]]) -> Iterator[int]:
    for i, e in enumerate(entries):
        if e["level"] in ("WARN", "ERROR", "FATAL"):
            yield i


def collect_signatures(
    input_dir: Path, context: int
) -> tuple[dict[str, dict[str, Any]], dict[str, int]]:
    """Walk DebugLog-server files and collect dedup'd signatures."""
    signatures: dict[str, dict[str, Any]] = {}
    files_scanned = 0
    log_lines_total = 0
    error_lines_total = 0

    for path in sorted(input_dir.glob("*DebugLog-server*.txt")):
        files_scanned += 1
        entries = parse_file(path)
        log_lines_total += sum(len(e["body"]) for e in entries)
        for hit_idx in iter_warn_or_error(entries):
            hit = entries[hit_idx]
            error_lines_total += len(hit["body"])
            sig = signature_for(hit["level"], hit["body"])
            occurrence = {
                "file": path.name,
                "line": hit["line_start"],
                "timestamp": hit["timestamp"],
            }
            if sig not in signatures:
                signatures[sig] = {
                    "signature": sig,
                    "level": hit["level"],
                    "first_seen": occurrence,
                    "occurrence_count": 1,
                    "files": [path.name],
                    "excerpt": build_excerpt(entries, hit_idx, context),
                }
            else:
                rec = signatures[sig]
                rec["occurrence_count"] += 1
                if path.name not in rec["files"]:
                    rec["files"].append(path.name)
    return signatures, {
        "files_scanned": files_scanned,
        "log_lines_total": log_lines_total,
        "error_lines_total": error_lines_total,
    }


def call_qwen(client: OpenAI, model: str, sig_rec: dict[str, Any]) -> dict[str, Any]:
    user_prompt = (
        f'Level: {sig_rec["level"]}\n'
        f'First seen: {sig_rec["first_seen"]["file"]} '
        f'line {sig_rec["first_seen"]["line"]}\n'
        f'Occurrences across this run: {sig_rec["occurrence_count"]} '
        f'(across {len(sig_rec["files"])} file(s))\n\n'
        f'Log excerpt:\n{sig_rec["excerpt"]}'
    )
    return structured_call(
        TOOL_SCHEMA,
        [
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": user_prompt},
        ],
        sampling=SAMPLING_STRUCTURED,
        client=client,
        model=model,
    )


def atomic_write(path: Path, payload: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + ".tmp")
    with tmp.open("w", encoding="utf-8") as f:
        json.dump(payload, f, indent=2, ensure_ascii=False)
    tmp.replace(path)


def load_existing(path: Path) -> dict[str, dict[str, Any]]:
    """Reload signatures previously written to --out.

    Only signatures with an `llm` field count as completed. Bare records
    (left behind when --limit truncated a prior run) get re-attempted on
    resume so progressive analysis converges.
    """
    if not path.exists():
        return {}
    try:
        with path.open("r", encoding="utf-8") as f:
            data = json.load(f)
        return {
            s["signature"]: s
            for s in data.get("signatures", [])
            if "signature" in s and "llm" in s
        }
    except Exception:
        return {}


def summarise(analyzed: list[dict[str, Any]]) -> dict[str, Any]:
    sev_counts = {"problem": 0, "warning": 0, "info": 0}
    by_cat: dict[str, int] = {}
    for s in analyzed:
        llm = s.get("llm") or {}
        sev = llm.get("severity")
        cat = llm.get("category")
        if sev in sev_counts:
            sev_counts[sev] += 1
        if cat:
            by_cat[cat] = by_cat.get(cat, 0) + 1
    return {
        "problems": sev_counts["problem"],
        "warnings": sev_counts["warning"],
        "info": sev_counts["info"],
        "by_category": by_cat,
    }


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--input", type=Path, default=DEFAULT_INPUT)
    ap.add_argument("--out", type=Path, default=DEFAULT_OUT)
    ap.add_argument("--context", type=int, default=20)
    ap.add_argument("--limit", type=int, default=None,
                    help="Stop after N new signatures analysed.")
    ap.add_argument("--resume", action="store_true",
                    help="Reuse existing analysis from --out if present.")
    ap.add_argument("--checkpoint-every", type=int, default=25)
    args = ap.parse_args()

    if not args.input.is_dir():
        print(f"error: {args.input} not a directory", file=sys.stderr)
        sys.exit(2)

    started = dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds")
    print(f"[init] scanning {args.input}")
    signatures, file_stats = collect_signatures(args.input, args.context)
    print(
        f"[init] {file_stats['files_scanned']} file(s), "
        f"{file_stats['log_lines_total']} log lines, "
        f"{file_stats['error_lines_total']} error lines, "
        f"{len(signatures)} unique signature(s)"
    )

    existing = load_existing(args.out) if args.resume else {}
    if existing:
        print(f"[init] {len(existing)} signature(s) already analysed; resuming")

    client = get_client()
    model = get_model()
    print(f"[init] qwen model={model}")

    n_new = 0
    t0 = time.time()
    analyzed: list[dict[str, Any]] = []

    # Process in occurrence_count desc so --limit N picks the most-impactful
    # signatures rather than whichever happened to scan first.
    for sig, rec in sorted(
        signatures.items(), key=lambda kv: -kv[1]["occurrence_count"]
    ):
        if sig in existing:
            analyzed.append(existing[sig])
            continue
        if args.limit is not None and n_new >= args.limit:
            analyzed.append(rec)  # keep raw record so it's not lost on resume
            continue
        try:
            llm = call_qwen(client, model, rec)
            rec["llm"] = llm
        except Exception as e:
            rec["llm"] = {"error": str(e)[:500]}
            print(f"  [{n_new + 1}] LLM error on {sig}: {e}", file=sys.stderr)
        analyzed.append(rec)
        n_new += 1
        if n_new % args.checkpoint_every == 0:
            payload = {
                "meta": {
                    "input_dir": str(args.input),
                    **file_stats,
                    "unique_signatures": len(signatures),
                    "redacted": True,
                    "qwen_model": model,
                    "started": started,
                    "checkpoint_at": dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds"),
                },
                "signatures": analyzed,
                "summary": summarise(analyzed),
            }
            atomic_write(args.out, payload)
            rate = n_new / max(time.time() - t0, 1e-3)
            print(f"  [{n_new}] checkpoint @ {rate:.2f} sig/s")

    finished = dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds")
    payload = {
        "meta": {
            "input_dir": str(args.input),
            **file_stats,
            "unique_signatures": len(signatures),
            "redacted": True,
            "qwen_model": model,
            "started": started,
            "finished": finished,
        },
        "signatures": analyzed,
        "summary": summarise(analyzed),
    }
    atomic_write(args.out, payload)
    print(f"[done] {n_new} new, {len(analyzed)} total -> {args.out}")


if __name__ == "__main__":
    main()
