"""
pz_parser.py — Deterministic Project Zomboid log parser.

Pure module (no I/O beyond reading the path it is handed). Walks a redacted
DebugLog-server*.txt file, extracts errors/warnings, attributes each to a mod
where evidence allows, classifies by kind, and computes deterministic
signatures. Output records are designed to be `dataclasses.asdict()`-ready
for direct JSON serialisation.

Pipeline phases (per design spec at
docs/superpowers/specs/2026-05-04-pz-deterministic-classifier-design.md):

1. Severity-prefix recognition (ERROR|SEVERE|WARN)
2. Bidirectional stack collection (pre-stack walk back, post-stack walk forward)
3. Mod attribution (direct, inferred, unattributed)
4. File:line extraction (five fallbacks)
5. Cause-chain extraction (Caused by: chains + standalone exception lines)
6. Java exception kind detection
7. Engine-noise tagging
8. Signature computation (pattern_id + signature)
9. Aggregation (dedup on signature)

Style notes mirror sibling tool pz_error_analysis.py: type hints with built-in
generics, `from __future__ import annotations`, regex precompilation as
module-level constants, stdlib-only.
"""
from __future__ import annotations

import hashlib
import pathlib
import re
from dataclasses import dataclass

# ---------------------------------------------------------------------------
# Tunable constants
# ---------------------------------------------------------------------------

#: Lookback window (in raw file lines) for inferred mod attribution.
INFERRED_LOOKBACK_LINES: int = 40
#: Maximum frames retained per record after pre+post stack merge.
MAX_STACK_FRAMES: int = 8
#: Maximum lines walked in each direction during bidirectional stack collection.
STACK_WALK_LINES: int = 25
#: Maximum cause-chain depth retained.
MAX_CAUSE_CHAIN_LEVELS: int = 6
#: Truncation length for the normalised first line that feeds pattern_id.
PATTERN_ID_FIRST_LINE_MAX: int = 200

# ---------------------------------------------------------------------------
# Line-shape regexes (parsing)
# ---------------------------------------------------------------------------

#: PZ DebugLog entry header.
#: Example: ``[16-04-26 00:01:19.080] ERROR: General      f:0, t:1, st:1,2,3,4> body``
ENTRY_RE = re.compile(
    r"^\[(?P<ts>\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\]\s+"
    r"(?P<level>[A-Z]+)\s*:\s*(?P<rest>.*)$"
)

#: Strips the "General  f:N, t:N, st:N,N,N,N>" prefix from a body line.
SESSION_META_RE = re.compile(
    r"^[A-Za-z][A-Za-z0-9]*\s+f:\d+,?\s*(?:t:\d+,?\s*)?st:[\d,]+>\s*"
)

# ---------------------------------------------------------------------------
# Severity-prefix recognition (phase 1)
# ---------------------------------------------------------------------------

#: Severity tokens that flag a body line as an error/warning event when they
#: appear at the start of body text. Per spec: broader than the existing
#: pz_error_analysis.py regex (adds SEVERE for Java util-logging).
SEVERITY_BODY_RE = re.compile(r"^\s*(ERROR|SEVERE|WARN)\s*[:\s]")
#: Bracketed-level tokens that map to severity events.
SEVERITY_LEVELS: tuple[str, ...] = ("ERROR", "WARN", "SEVERE", "FATAL")

# ---------------------------------------------------------------------------
# Stack-frame recognition (phase 2)
# ---------------------------------------------------------------------------

#: Markers that identify a line as stack-shaped. Used to gate pre/post stack
#: collection so we don't latch onto non-stack continuation text.
STACK_HINT_RE = re.compile(
    r"(?:\bat\s+\S+|\[string\s+\"|function:\s|file:\s|\.lua\b)",
    re.IGNORECASE,
)

# ---------------------------------------------------------------------------
# Mod attribution (phase 3)
# ---------------------------------------------------------------------------

#: Direct attribution marker: ``Lua((MOD:<name>))``.
LUA_MOD_MARKER_RE = re.compile(r"Lua\(\(MOD:([^)]+)\)\)")
#: Direct attribution: ``require("X") failed`` shape.
REQUIRE_FAILED_RE = re.compile(
    r"""require\s*\(\s*["']([^"']+)["']\s*\)\s+failed""",
    re.IGNORECASE,
)
#: Direct attribution: explicit ``needed by <mod>`` hint.
NEEDED_BY_RE = re.compile(r"needed\s+by\s+([A-Za-z0-9_'\- ]+?)(?:[,.]|$)", re.IGNORECASE)

#: Patterns that flag a body as "Lua-shaped" — gating filter for inferred
#: attribution. Mirrors the spec's enumeration.
LUA_SHAPED_PATTERNS: tuple[re.Pattern[str], ...] = (
    re.compile(r"luamanager\.getfunctionobject", re.IGNORECASE),
    re.compile(r"no\s+such\s+function", re.IGNORECASE),
    re.compile(r"exception\s+thrown", re.IGNORECASE),
    re.compile(r"runtimeexception", re.IGNORECASE),
    re.compile(r"illegalstateexception", re.IGNORECASE),
    re.compile(r"\blua\b", re.IGNORECASE),
)

# ---------------------------------------------------------------------------
# File:line extraction (phase 4) — five fallbacks tried in order
# ---------------------------------------------------------------------------

#: 1. ``at <path>.lua:<n>`` — typical Lua stack frame.
FILE_LINE_AT_RE = re.compile(r"\bat\s+([^\s:]+\.lua):(\d+)")
#: 2. ``function: ... file: <path>.lua line #<n>`` (or `: <n>`).
FILE_LINE_FUNCTION_RE = re.compile(
    r"function:\s*[^,]*?file:\s*([^\s,]+\.lua)\s+line\s*(?:#|:)\s*(\d+)",
    re.IGNORECASE,
)
#: 3. ``[string "<path>.lua"]:<n>`` — Lua VM source string.
FILE_LINE_STRING_RE = re.compile(r"""\[string\s+["']([^"']+\.lua)["']\]:(\d+)""")
#: 4. quoted path ending in a known extension; line # optional.
FILE_LINE_QUOTED_RE = re.compile(
    r"""["']([^"']+\.(?:lua|txt|xml|json|ini|cfg|bin))["'](?::(\d+))?"""
)
#: 5. unquoted path segment beginning with a recognised root.
FILE_LINE_UNQUOTED_RE = re.compile(
    r"\b((?:media|maps|lua|scripts)/[\w./\-]+\.(?:lua|txt|xml|json|ini|cfg|bin))(?::(\d+))?"
)

# ---------------------------------------------------------------------------
# Cause-chain extraction (phase 5)
# ---------------------------------------------------------------------------

#: ``Caused by: <ExceptionClass>: <msg>`` (msg optional).
CAUSED_BY_RE = re.compile(
    r"Caused\s+by:\s+((?:\w+\.)+\w+(?:Exception|Error))(?::\s*(.+?))?\s*$",
    re.IGNORECASE,
)
#: Standalone Java exception line: ``com.foo.BarException: msg``.
EXCEPTION_LINE_RE = re.compile(
    r"((?:\w+\.)+\w+(?:Exception|Error))(?::\s*(.+?))?(?=\s+at\s|\s*$)"
)

# ---------------------------------------------------------------------------
# Engine-noise tagging (phase 7)
# ---------------------------------------------------------------------------

ENGINE_NOISE_PATTERNS: tuple[re.Pattern[str], ...] = (
    re.compile(r"kahluathread\.flusherrormessage", re.IGNORECASE),
    re.compile(r"dumping\s+lua\s+stack\s+trace", re.IGNORECASE),
)

# ---------------------------------------------------------------------------
# Signature normalisation (phase 8)
# ---------------------------------------------------------------------------

DOUBLE_QUOTED_RE = re.compile(r'"[^"]*"')
SINGLE_QUOTED_RE = re.compile(r"'[^']*'")
NUMERIC_RUN_RE = re.compile(r"\d{2,}")
WS_RUN_RE = re.compile(r"\s+")
#: Strips a leading ``ERROR:`` / ``SEVERE:`` / ``WARN:`` / ``FATAL:`` token
#: from a body line so a body that happens to begin with the severity word
#: hashes to the same pattern_id as the bracketed-only variant. Matches the
#: token plus any colon and trailing whitespace; case-insensitive.
SEVERITY_PREFIX_STRIP_RE = re.compile(
    r"^\s*(?:ERROR|SEVERE|WARN|FATAL)\s*[:\s]\s*", re.IGNORECASE
)

# ---------------------------------------------------------------------------
# Dataclasses — match the JSON keys the spec mandates so consumers can
# `dataclasses.asdict(record)` straight to JSON.
# ---------------------------------------------------------------------------


@dataclass
class Entry:
    """One parsed log entry. Continuation lines (TAB-indented or otherwise
    non-header lines) are folded into ``body``. Phase-2 stack collection
    walks neighbouring entries (not raw lines), so no extra context is
    stored here.
    """

    timestamp: str
    level: str
    body: list[str]
    line_start: int
    line_end: int


@dataclass
class FirstSeen:
    """Provenance for the first occurrence of a deduped record."""

    file: str
    line: int
    timestamp: str


@dataclass
class Record:
    """One classified, deduplicated error/warning record. Field names mirror
    the JSON output schema in the spec verbatim — this object is intended to
    be `dataclasses.asdict()`-ed straight into the output document.
    """

    signature: str
    pattern_id: str
    level: str
    kind: str
    mod_id: str
    mod_name: str
    attribution: str
    confidence: str
    attribution_reason: str
    file: str
    line: int
    cause_chain: str
    stack: list[str]
    first_seen: FirstSeen
    occurrence_count: int
    files: list[str]
    excerpt: str


# ---------------------------------------------------------------------------
# Phase 0: file parse
# ---------------------------------------------------------------------------


def parse_file(path: pathlib.Path) -> list[Entry]:
    """Parse a DebugLog-server file into a list of multi-line entries.

    Continuation lines (those not matching ENTRY_RE) append to the previous
    entry's body, mirroring codex's PatternParser behaviour for multi-line
    Java stack traces under an ERROR header.
    """
    entries: list[Entry] = []
    current: Entry | None = None
    with path.open("r", encoding="utf-8", errors="replace") as f:
        for lineno, raw in enumerate(f, start=1):
            line = raw.rstrip("\n")
            m = ENTRY_RE.match(line)
            if m:
                if current is not None:
                    entries.append(current)
                current = Entry(
                    timestamp=m.group("ts"),
                    level=m.group("level"),
                    body=[m.group("rest")],
                    line_start=lineno,
                    line_end=lineno,
                )
            elif current is not None:
                current.body.append(line)
                current.line_end = lineno
            # else: orphan line at start of file (no preceding entry); ignore.
    if current is not None:
        entries.append(current)
    return entries


# ---------------------------------------------------------------------------
# Phase 1: severity-prefix recognition
# ---------------------------------------------------------------------------


def is_severity_entry(entry: Entry) -> bool:
    """True if this entry is an ERROR/WARN/SEVERE/FATAL — either by the
    bracketed level or a leading SEVERE/ERROR/WARN token in the body (after
    stripping the session-meta prefix)."""
    if entry.level in SEVERITY_LEVELS:
        return True
    if entry.body and SEVERITY_BODY_RE.match(_strip_session_meta(entry.body[0])):
        return True
    return False


def effective_level(entry: Entry) -> str:
    """Return the effective severity for an entry. Body-prefix takes
    precedence — covers the SEVERE-in-body case where bracketed level is LOG
    *and* the case where bracketed level is ERROR but body says SEVERE.
    """
    if entry.body:
        m = SEVERITY_BODY_RE.match(_strip_session_meta(entry.body[0]))
        if m:
            return m.group(1).upper()
    return entry.level


# ---------------------------------------------------------------------------
# Phase 2: bidirectional stack collection
# ---------------------------------------------------------------------------


def _is_stack_shaped(line: str) -> bool:
    return bool(STACK_HINT_RE.search(line))


def _strip_session_meta(body_line: str) -> str:
    """Strip the ``General  f:N, t:N, st:...> `` session-metadata prefix from
    a body's first line so pattern matching can run against the meaningful tail.
    """
    return SESSION_META_RE.sub("", body_line)


def _collect_pre_stack(entries: list[Entry], hit_idx: int) -> list[str]:
    """Walk back through prior entries; collect stack-shaped lines from each
    entry's body. Stop at the previous severity-flagged entry. Cap collection
    at MAX_STACK_FRAMES and at STACK_WALK_LINES of body lines examined.
    Per spec, only return the block if at least one line looks stack-shaped.
    """
    collected: list[str] = []
    lines_examined = 0
    for j in range(hit_idx - 1, -1, -1):
        prior = entries[j]
        # Stop at another severity line (the previous error's boundary).
        if is_severity_entry(prior):
            break
        # Walk this entry's body in reverse; for body[0] the session-meta
        # prefix is part of the line — strip it before stack-shape check.
        for k in range(len(prior.body) - 1, -1, -1):
            line = prior.body[k]
            stripped = _strip_session_meta(line) if k == 0 else line
            lines_examined += 1
            if _is_stack_shaped(stripped):
                collected.append(stripped.strip())
                if len(collected) >= MAX_STACK_FRAMES:
                    break
            if lines_examined >= STACK_WALK_LINES:
                break
        if len(collected) >= MAX_STACK_FRAMES or lines_examined >= STACK_WALK_LINES:
            break
    if not collected:
        return []
    collected.reverse()  # restore source order
    return collected


def _collect_post_stack(entries: list[Entry], hit_idx: int) -> list[str]:
    """Look at the entry's own body continuation lines first (stack frames
    attached to the ERROR header become continuation lines after parsing),
    then walk forward through subsequent entries. Stop at the next severity
    entry. Cap at MAX_STACK_FRAMES and at STACK_WALK_LINES of body lines."""
    entry = entries[hit_idx]
    collected: list[str] = []
    lines_examined = 0
    # Body continuations (skip body[0] which is the headline itself).
    for line in entry.body[1:]:
        lines_examined += 1
        if _is_stack_shaped(line):
            collected.append(line.strip())
            if len(collected) >= MAX_STACK_FRAMES:
                return collected
        if lines_examined >= STACK_WALK_LINES:
            return collected
    for j in range(hit_idx + 1, len(entries)):
        next_entry = entries[j]
        if is_severity_entry(next_entry):
            break
        for k, line in enumerate(next_entry.body):
            stripped = _strip_session_meta(line) if k == 0 else line
            lines_examined += 1
            if _is_stack_shaped(stripped):
                collected.append(stripped.strip())
                if len(collected) >= MAX_STACK_FRAMES:
                    return collected
            if lines_examined >= STACK_WALK_LINES:
                return collected
    return collected


def collect_stack(entries: list[Entry], hit_idx: int) -> list[str]:
    """Merge pre + post stack, dedup preserving order, cap at MAX_STACK_FRAMES."""
    pre = _collect_pre_stack(entries, hit_idx)
    post = _collect_post_stack(entries, hit_idx)
    seen: set[str] = set()
    merged: list[str] = []
    for frame in pre + post:
        if frame in seen:
            continue
        seen.add(frame)
        merged.append(frame)
        if len(merged) >= MAX_STACK_FRAMES:
            break
    return merged


# ---------------------------------------------------------------------------
# Phase 3: mod attribution
# ---------------------------------------------------------------------------


def _norm_mod_key(raw_name: str) -> str:
    """Lowercase, strip spaces / apostrophes / hyphens. Used as mod_id."""
    s = raw_name.lower()
    for ch in (" ", "'", "-"):
        s = s.replace(ch, "")
    return s


def _entry_text(entry: Entry) -> str:
    """Whole-entry text (body + collected stack) for marker scanning."""
    return "\n".join(entry.body)


def attribute_entry(entry: Entry, prior_lookback_lines: list[str]) -> tuple[str, str, str, str, str]:
    """Determine ``(mod_id, mod_name, attribution, confidence, reason)``.

    ``prior_lookback_lines`` is the body lines from prior entries that fall
    within INFERRED_LOOKBACK_LINES raw-file-line distance from this entry's
    start, in source order. The list is scanned in reverse for the nearest
    ``Lua((MOD:Y))`` marker when inferred attribution is being attempted.

    Direct-attribution priority: Lua marker -> needed-by -> require-failed.

    Rationale: ``needed by <mod>`` names the dependent mod (more semantically
    targeted) and is preferred over ``require("...") failed`` which only names
    the missing module path. ``Lua((MOD:...))`` is unambiguous and wins
    outright.
    """
    text = _entry_text(entry)
    # 1. Direct via Lua((MOD:X)) — unambiguous; outranks every other signal.
    m = LUA_MOD_MARKER_RE.search(text)
    if m:
        raw = m.group(1).strip()
        return (
            _norm_mod_key(raw),
            raw,
            "direct",
            "high",
            "Lua((MOD:...)) marker on the entry itself",
        )
    # 2. Direct via "needed by <mod>"
    m = NEEDED_BY_RE.search(text)
    if m:
        raw = m.group(1).strip().rstrip(".,;")
        return (
            _norm_mod_key(raw),
            raw,
            "direct",
            "high",
            "needed by <mod> hint",
        )
    # 3. Direct via require("X") failed — attribute to required module name.
    m = REQUIRE_FAILED_RE.search(text)
    if m:
        raw = m.group(1).strip()
        # Mod-name first segment (PZ paths often look like Mod/Foo/Bar).
        mod_name = raw.split("/")[0] if "/" in raw else raw
        return (
            _norm_mod_key(mod_name),
            mod_name,
            "direct",
            "high",
            'require("...") failed shape',
        )
    # 4. Inferred — Lua-shaped body + recent Lua((MOD:Y)) within lookback.
    if any(p.search(text) for p in LUA_SHAPED_PATTERNS):
        for line in reversed(prior_lookback_lines):
            mm = LUA_MOD_MARKER_RE.search(line)
            if mm:
                raw = mm.group(1).strip()
                return (
                    _norm_mod_key(raw),
                    raw,
                    "inferred",
                    "medium",
                    f"Lua-shaped body; nearest Lua((MOD:{raw})) within "
                    f"{INFERRED_LOOKBACK_LINES}-line lookback",
                )
    return (
        "__unattributed__",
        "",
        "unattributed",
        "low",
        "no marker; body not Lua-shaped or no recent Lua((MOD:...))",
    )


# ---------------------------------------------------------------------------
# Phase 4: file:line extraction (five fallbacks, in order)
# ---------------------------------------------------------------------------


def extract_file_line(text: str) -> tuple[str, int]:
    """Run the five fallbacks in order. Returns ``(file, line)`` with line=0
    when only a path was matched."""
    m = FILE_LINE_AT_RE.search(text)
    if m:
        return m.group(1), int(m.group(2))
    m = FILE_LINE_FUNCTION_RE.search(text)
    if m:
        return m.group(1), int(m.group(2))
    m = FILE_LINE_STRING_RE.search(text)
    if m:
        return m.group(1), int(m.group(2))
    m = FILE_LINE_QUOTED_RE.search(text)
    if m:
        return m.group(1), int(m.group(2)) if m.group(2) else 0
    m = FILE_LINE_UNQUOTED_RE.search(text)
    if m:
        return m.group(1), int(m.group(2)) if m.group(2) else 0
    return "", 0


# ---------------------------------------------------------------------------
# Phase 5: cause-chain extraction
# ---------------------------------------------------------------------------


def extract_cause_chain(text: str) -> str:
    """Return ``ExceptionA: msg -> ExceptionB: msg`` joined chain, deduped,
    capped at MAX_CAUSE_CHAIN_LEVELS levels.
    """
    tokens: list[str] = []
    seen: set[str] = set()
    for line in text.splitlines():
        cb = CAUSED_BY_RE.search(line)
        if cb:
            cls = cb.group(1)
            msg = cb.group(2) or ""
            tok = f"{cls}: {msg.strip()}".rstrip(": ").strip()
            if tok not in seen:
                seen.add(tok)
                tokens.append(tok)
            continue
        ex = EXCEPTION_LINE_RE.search(line)
        if ex:
            cls = ex.group(1)
            msg = ex.group(2) or ""
            tok = f"{cls}: {msg.strip()}".rstrip(": ").strip()
            if tok not in seen:
                seen.add(tok)
                tokens.append(tok)
        if len(tokens) >= MAX_CAUSE_CHAIN_LEVELS:
            break
    return " -> ".join(tokens[:MAX_CAUSE_CHAIN_LEVELS])


# ---------------------------------------------------------------------------
# Phase 6: Java exception kind detection
# ---------------------------------------------------------------------------


JAVA_EXCEPTION_RE = re.compile(r"(?:\w+\.)+\w+(?:Exception|Error)\b")


def detect_kind(entry: Entry, attribution: str, body_text: str) -> str:
    """Determine the ``kind`` field. Order: engine_noise > require_failed >
    java_exception > lua_runtime > runtime."""
    # Phase 7 short-circuit (engine noise outranks others per spec — engine
    # noise is PZ's own diagnostic chatter regardless of class).
    if any(p.search(body_text) for p in ENGINE_NOISE_PATTERNS):
        return "engine_noise"
    if REQUIRE_FAILED_RE.search(body_text):
        return "require_failed"
    has_java = bool(JAVA_EXCEPTION_RE.search(body_text))
    has_lua_marker = bool(LUA_MOD_MARKER_RE.search(body_text))
    if has_java and not has_lua_marker:
        return "java_exception"
    # Lua-attributed runtime / inferred
    if has_lua_marker or attribution in ("direct", "inferred"):
        return "lua_runtime"
    return "runtime"


# ---------------------------------------------------------------------------
# Phase 8: signature computation
# ---------------------------------------------------------------------------


def normalize_first_line(first: str) -> str:
    """Per spec: strip session metadata prefix, strip any leading severity
    word (so ``SEVERE: foo`` and ``foo`` produce the same pattern_id when both
    are SEVERE-level), flatten quoted strings to ``"<S>"`` / ``'<S>'``, flatten
    ≥2-digit numeric runs to ``<N>``, collapse whitespace, truncate to 200
    chars.
    """
    s = first.strip()
    s = SESSION_META_RE.sub("", s)
    # Strip any leading ERROR:/SEVERE:/WARN:/FATAL: that survived in the body
    # — the bracketed level already feeds pattern_id separately, so leaving
    # the body-prefix in place would fragment signatures across "body has
    # SEVERE: prefix" vs "body has no prefix but bracketed level is SEVERE."
    s = SEVERITY_PREFIX_STRIP_RE.sub("", s)
    s = DOUBLE_QUOTED_RE.sub('"<S>"', s)
    s = SINGLE_QUOTED_RE.sub("'<S>'", s)
    s = NUMERIC_RUN_RE.sub("<N>", s)
    s = WS_RUN_RE.sub(" ", s)
    return s[:PATTERN_ID_FIRST_LINE_MAX]


def compute_pattern_id(level: str, first_line: str) -> str:
    """``sha256(level + normalized_first_line)[:16]``, prefixed ``sha256:``.

    16 hex chars (64 bits) chosen for JSON readability vs collision-resistance
    trade-off; consumers treat as opaque.
    """
    norm = normalize_first_line(first_line)
    h = hashlib.sha256(f"{level}\n{norm}".encode("utf-8")).hexdigest()
    return f"sha256:{h[:16]}"


def compute_signature(pattern_id: str, mod_id: str) -> str:
    """``sha256(pattern_id + mod_id)[:16]``, prefixed ``sha256:``.

    16 hex chars (64 bits) chosen for JSON readability vs collision-resistance
    trade-off; consumers treat as opaque.
    """
    h = hashlib.sha256(f"{pattern_id}\n{mod_id}".encode("utf-8")).hexdigest()
    return f"sha256:{h[:16]}"


# ---------------------------------------------------------------------------
# Aggregation (phase 9) and the public classify_entries entry point
# ---------------------------------------------------------------------------


_CONFIDENCE_RANK: dict[str, int] = {"low": 0, "medium": 1, "high": 2}
_ATTRIBUTION_RANK: dict[str, int] = {
    "unattributed": 0,
    "inferred": 1,
    "direct": 2,
}


def _build_excerpt(entry: Entry, max_chars: int = 1000) -> str:
    """Best-effort one-block excerpt of the entry (header + continuations)."""
    lines: list[str] = []
    header = f'[{entry.timestamp}] {entry.level}: '
    if entry.body:
        lines.append(header + entry.body[0])
        for cont in entry.body[1:]:
            lines.append(cont)
    text = "\n".join(lines)
    if len(text) > max_chars:
        text = text[:max_chars] + "\n... [truncated]"
    return text


def _build_lookback_window(entries: list[Entry], hit_idx: int) -> list[str]:
    """Collect body lines from prior entries whose ``line_start`` falls within
    INFERRED_LOOKBACK_LINES raw-file-line distance from the current entry.

    Spec wording is "within the previous 40 lines", measured in raw file lines
    (mirrors pzmm's ``(i - last_mod_line) <= 40``, inclusive of 40). Counting
    raw lines means a multi-line entry (e.g., a 5-line Java stack trace) does
    not shrink the practical window the way a body-line budget would.

    Returned list is in source order (oldest first) so callers can call
    ``reversed()`` on it.
    """
    if hit_idx <= 0:
        return []
    threshold = entries[hit_idx].line_start - INFERRED_LOOKBACK_LINES
    in_window: list[Entry] = []
    for j in range(hit_idx - 1, -1, -1):
        prior = entries[j]
        if prior.line_start < threshold:
            break
        in_window.append(prior)
    # We accumulated newest-first; reverse so we emit in source order.
    in_window.reverse()
    collected: list[str] = []
    for prior in in_window:
        collected.extend(prior.body)
    return collected


def classify_entries(entries: list[Entry], source_file: str = "") -> list[Record]:
    """Apply phases 1-9 to a parsed-file entry list. Returns one Record per
    unique (mod_id, error_shape) pair after dedup on signature.
    """
    by_signature: dict[str, Record] = {}
    for hit_idx, entry in enumerate(entries):
        if not is_severity_entry(entry):
            continue
        level = effective_level(entry)
        body_text = _entry_text(entry)
        # Phase 2: stack collection
        stack = collect_stack(entries, hit_idx)
        # Phase 3: attribution (with INFERRED_LOOKBACK_LINES lookback)
        prior_window = _build_lookback_window(entries, hit_idx)
        mod_id, mod_name, attribution, confidence, attribution_reason = attribute_entry(
            entry, prior_window
        )
        # Phase 4: file:line extraction (search body + stack frames)
        search_text = body_text + "\n" + "\n".join(stack)
        file_path, line_no = extract_file_line(search_text)
        # Phase 5: cause-chain extraction
        cause_chain = extract_cause_chain(search_text)
        # Phase 6 & 7: kind detection (engine_noise short-circuits)
        kind = detect_kind(entry, attribution, body_text)
        # Phase 8: signature computation
        pattern_id = compute_pattern_id(level, entry.body[0] if entry.body else "")
        signature = compute_signature(pattern_id, mod_id)
        # Phase 9: dedup & aggregate
        if signature not in by_signature:
            by_signature[signature] = Record(
                signature=signature,
                pattern_id=pattern_id,
                level=level,
                kind=kind,
                mod_id=mod_id,
                mod_name=mod_name,
                attribution=attribution,
                confidence=confidence,
                attribution_reason=attribution_reason,
                file=file_path,
                line=line_no,
                cause_chain=cause_chain,
                stack=list(stack),
                first_seen=FirstSeen(
                    file=source_file,
                    line=entry.line_start,
                    timestamp=entry.timestamp,
                ),
                occurrence_count=1,
                files=[source_file] if source_file else [],
                excerpt=_build_excerpt(entry),
            )
        else:
            rec = by_signature[signature]
            rec.occurrence_count += 1
            if source_file and source_file not in rec.files:
                rec.files.append(source_file)
            # Promote attribution / confidence if this hit is stronger.
            if _ATTRIBUTION_RANK[attribution] > _ATTRIBUTION_RANK[rec.attribution]:
                rec.attribution = attribution
                rec.attribution_reason = attribution_reason
                if mod_name:
                    rec.mod_name = mod_name
            if _CONFIDENCE_RANK[confidence] > _CONFIDENCE_RANK[rec.confidence]:
                rec.confidence = confidence
            # Merge stack frames (preserving order, capped).
            for frame in stack:
                if frame not in rec.stack and len(rec.stack) < MAX_STACK_FRAMES:
                    rec.stack.append(frame)
            # Extend cause chain if the new hit has additional segments.
            if cause_chain and cause_chain != rec.cause_chain:
                # Concatenate unseen tokens.
                old = rec.cause_chain.split(" -> ") if rec.cause_chain else []
                new = cause_chain.split(" -> ")
                merged = list(old)
                for tok in new:
                    if tok and tok not in merged:
                        merged.append(tok)
                rec.cause_chain = " -> ".join(merged[:MAX_CAUSE_CHAIN_LEVELS])
    return list(by_signature.values())


__all__ = [
    "Entry",
    "FirstSeen",
    "Record",
    "parse_file",
    "classify_entries",
    "is_severity_entry",
    "effective_level",
    "collect_stack",
    "attribute_entry",
    "extract_file_line",
    "extract_cause_chain",
    "detect_kind",
    "normalize_first_line",
    "compute_pattern_id",
    "compute_signature",
    "INFERRED_LOOKBACK_LINES",
    "MAX_STACK_FRAMES",
    "STACK_WALK_LINES",
    "MAX_CAUSE_CHAIN_LEVELS",
    "SEVERITY_LEVELS",
]
