# PZ deterministic classifier — design spec

> Drafted 2026-05-04. Status: design-approved, awaiting implementation plan.
> Sibling tool to the existing pre-production Qwen analyzer (`pz_error_analysis.py`), which is unaffected by this work.

## Summary

A new deterministic-only Project Zomboid log classifier that lives alongside the existing Qwen-based analyzer in `tools/pz-analyzer/`. Walks redacted `DebugLog-server*.txt` files, extracts errors/warnings, attributes each to a mod where evidence allows, classifies by kind, and emits a structured JSON report. **Zero AI dependency**: this is the artefact that informs the future PHP / iblogs production path.

The patterns it implements are inspired by `paraxaQQ/pzmm`'s `core/inspector.py` — Lua mod-marker attribution, multi-fallback file:line extraction, bidirectional stack collection, cause-chain unwinding, engine-noise tagging. Reimplemented originally; no code copied verbatim.

## Why a separate tool, not an edit of `pz_error_analysis.py`

Two artefacts, two purposes:

- `pz_error_analysis.py` (existing, untouched) — pre-production discovery tool. Sends residual log content to Qwen so the developer can see what categories the deterministic side hasn't yet captured.
- `pz_classify.py` (new) — production-bound deterministic classifier. Output is what an iblogs PHP port would eventually emit. Runs in seconds, no API dependency, no PII-going-to-LLM consideration.

Coexisting them lets the developer compare outputs and treat the LLM's residual output as the "deterministic to-do list."

## Scope

**In scope:**
- Two new files: `tools/pz-analyzer/pz_parser.py` (pure module) and `tools/pz-analyzer/pz_classify.py` (CLI orchestrator).
- Tests under `tools/pz-analyzer/tests/` with synthetic fixtures.
- Operates exclusively on the already-redacted directory produced by `pz_redact_all.sh` (`.scratch/pz/Logs.redacted/`).

**Out of scope:**
- Any modification to `pz_error_analysis.py`, `pz_redact_all.sh`, or PHP codex source.
- Filesystem-based mod-scan reattribution (pzmm's symbol-index, vehicle-index, file-path-ownership reattribution requires an actual mod folder we don't have on the server side).
- iblogs / bosslogs integration. The output schema is designed with that future port in mind, but no PHP code is written here.
- Generic AI tab patterns from pzmm's `core/ai.py`. Explicitly excluded.

## Architecture

```
                redacted .txt files
                        |
                        v
          +---------------------------+
          | pz_classify.py            |   argparse · directory walk · aggregate · JSON write
          | (orchestrator)            |
          +-------------+-------------+
                        |
                        v
          +---------------------------+
          | pz_parser.py              |   regexes · parse · classify · sign
          | (pure module, no I/O      |
          |  beyond reading the path  |
          |  it is handed)            |
          +---------------------------+
```

Two files inside `tools/pz-analyzer/`:

- **`pz_parser.py`** — stateless. All regex constants, `parse_file(path) -> list[Entry]`, attribution helpers, file:line extractors, cause-chain extractor, signature computation. No `argparse`, no JSON writing, no directory walking. Unit-testable in isolation.
- **`pz_classify.py`** — entry point. CLI args, walks the redacted directory, calls `pz_parser`, aggregates records by signature, writes JSON, prints a one-line stats summary.

The split is deliberate: `pz_parser.py` is the module that eventually wants to be ported to PHP codex (separate spec). Keeping it pure makes that port mechanical and Python-side tests trivial.

## Parser pipeline phases

For each `*DebugLog-server*.txt`, the parser walks lines once and emits records via the following phases.

### 1. Severity-prefix recognition

Regex: `^\s*(ERROR|SEVERE|WARN)\s*[:\s]`. Broader than the existing `pz_error_analysis.py` regex — adds `SEVERE` (Java util-logging convention; appears in some PZ Java exception blocks). `LOG`/`INFO` is ignored at this layer.

### 2. Stack collection — bidirectional

Pzmm's contribution: PZ emits stack frames *before* the ERROR/WARN line as often as after.

- **Pre-stack**: walk up to 25 lines back from the severity line. Stop at another severity line or 8 collected. Only keep the block if at least one line looks stack-shaped (`at `, `[string ...]`, `function:`, `file:`, `.lua` markers).
- **Post-stack**: walk forward up to 25 lines, gated by engine-noise detection. Stop at another severity line or 8 collected.
- Merge deduped, preserving order; cap at 8 frames per record.

### 3. Mod attribution — three buckets

| Bucket | Trigger | Confidence |
|---|---|---|
| `direct` | Line itself matches `Lua\(\(MOD:([^)]+)\)\)` (or the `require("X") failed` shape, or an explicit `needed by <mod>` hint elsewhere in the entry) | `high` |
| `inferred` | No marker on this line, but body is Lua-shaped (see below) *and* a `Lua((MOD:Y))` was emitted within the previous 40 lines | `medium` |
| `unattributed` | Neither of the above | `low`; `mod_id = "__unattributed__"` |

"Lua-shaped" means the body matches at least one of (case-insensitive): `luamanager.getfunctionobject`, `no such function`, `exception thrown`, `runtimeexception`, `illegalstateexception`, or contains the bare token `lua`. This filter prevents inferred attribution from latching onto unrelated severity lines that happened to fall within the lookback window.

`mod_id` derives from the marker's raw name with a `_norm_mod_key` transform: lowercase, strip spaces / apostrophes / hyphens. `mod_name` preserves the human-readable form.

We do **not** attempt pzmm's filesystem-based reattribution.

### 4. File:line extraction — five fallbacks

Tried in order against the entry body and stack frames:

1. `at <path>.lua:<n>`
2. `function: ... file: <path>.lua line #<n>` (or `: <n>`)
3. `[string "<path>.lua"]:<n>`
4. quoted path ending in `.lua` / `.txt` / `.xml` / `.json` / `.ini` / `.cfg` / `.bin`
5. unquoted path segment beginning with `media/`, `maps/`, `lua/`, `scripts/`

Returns `(file, line)`; `line=0` if the matched form had no line number.

### 5. Cause-chain extraction

`Caused by: <X>` chains plus standalone exception lines (`(\w+\.)+\w+(Exception|Error): <msg>`) are normalised to `<ExceptionClass>: <msg>` tokens and joined with ` -> `. Up to 6 chain levels, deduped. Captures both Java exception nesting and Lua-wrapped exception chains.

### 6. Java exception kind detection

DebugLog-server has both Lua and Java exceptions; pzmm targets `console.txt` which is Lua-dominant. Extension here:

- `kind = "java_exception"` when the entry body or stack contains `(\w+\.)+\w+(Exception|Error)` AND no `Lua((MOD:X))` marker is present anywhere in the entry.
- These typically resolve to `mod_id: __unattributed__` because Java code in PZ is engine, not mod. The exception class name becomes part of the message skeleton so similar Java exceptions dedup tightly.

### 7. Engine-noise tagging

`kind = "engine_noise"` when the body contains `kahluathread.flusherrormessage` or `dumping lua stack trace`. These severity-ERROR lines are PZ's own diagnostic chatter about its error reporting, not actual errors. They stay in the output (consumer can filter on `kind`).

### 8. Signature computation

Two-level deterministic identity, both stored on every record:

```
pattern_id  = sha256(level + normalized_first_line)[:16]
signature   = sha256(pattern_id + mod_id)[:16]
```

Normalization for `pattern_id`:
- Strip session metadata prefix (`General  f:N, t:N, st:N,N,N,N>` shape)
- Strip body-prefix severity token (`ERROR:` / `SEVERE:` / `WARN:` / `FATAL:`, case-insensitive) so a body that opens with the severity word still hashes the same as one that doesn't.
- Flatten double- and single-quoted strings to `"<S>"` / `'<S>'`
- Flatten ≥2-digit numeric runs to `<N>`
- Collapse whitespace
- Truncate to 200 chars

Both fields ride on every record. Two consumer views, neither requires LLM:

- **Per-mod view** (signature is the dedup key): one record per `(mod_id, error_shape)` pair.
- **Pattern fan-out view** (group records by `pattern_id`): see all mods that hit the same shape.

### 9. Aggregation

Records dedup on `signature`. On second-and-subsequent occurrences: `occurrence_count++`, `files` set-extends, attribution-confidence promotes (direct beats inferred beats unattributed), stack and `cause_chain` merge.

## Output schema

```json
{
  "meta": {
    "input_dir": "/opt/ik-codex/.scratch/pz/Logs.redacted",
    "files_scanned": 6,
    "log_lines_total": 78654,
    "error_lines_total": 30984,
    "unique_signatures": N,
    "unique_patterns": M,
    "redacted": true,
    "started": "ISO8601",
    "finished": "ISO8601"
  },
  "signatures": [
    {
      "signature": "sha256:...",
      "pattern_id": "sha256:...",
      "level": "ERROR",
      "kind": "lua_runtime|require_failed|java_exception|engine_noise|runtime",
      "mod_id": "spongies_clothing",
      "mod_name": "Spongie's Clothing",
      "attribution": "direct|inferred|unattributed",
      "confidence": "high|medium|low",
      "attribution_reason": "...",
      "file": "media/lua/client/X.lua",
      "line": 42,
      "cause_chain": "ExceptionA: msg -> ExceptionB: msg",
      "stack": ["at A.lua:12", "at B.lua:34"],
      "first_seen": {"file": "...", "line": 1234, "timestamp": "26-04-26 17:14:35.128"},
      "occurrence_count": 47,
      "files": ["..."],
      "excerpt": "..."
    }
  ],
  "summary": {
    "errors": N,
    "warnings": N,
    "by_kind": {"lua_runtime": ..., "java_exception": ..., "require_failed": ..., "engine_noise": ..., "runtime": ...},
    "by_attribution": {"direct": ..., "inferred": ..., "unattributed": ...},
    "by_confidence": {"high": ..., "medium": ..., "low": ...},
    "top_mods": [{"mod_id": "...", "mod_name": "...", "occurrence_count": N}, ...]
  }
}
```

Default output path: `/opt/ik-codex/.scratch/pz/classify.json` (gitignored under `.scratch/`).

## CLI

```
pz_classify.py [--input <dir>] [--out <path>] [--quiet]
```

- `--input` defaults to `<repo>/.scratch/pz/Logs.redacted`
- `--out` defaults to `<repo>/.scratch/pz/classify.json`
- `--quiet` suppresses the trailing summary line

No `--limit`, `--resume`, or `--checkpoint-every`. Runs in seconds; nothing to throttle or resume.

## Tests

New directory `tools/pz-analyzer/tests/`. Stdlib `unittest`. Three files, ~18 tests total.

- **`test_parser.py`** (~10 tests) — one fixture per scenario in `tests/fixtures/` (synthetic, tracked in git): pure-Lua-attributed, pure-Java-exception, inferred-from-context, unattributed-engine-noise, multi-cause-chain, pre-stack-collection, post-stack-collection, severity-variants, file-line-extraction-fallbacks. All synthetic identifiers (placeholder Steam IDs / mod names) per the existing PHP-side `test/src/Games/ProjectZomboid/fixtures/` convention.
- **`test_attribution.py`** (~5 tests) — three confidence buckets, the 40-line lookback boundary, "needed by X" extraction, and the rejection of inferred attribution when the message isn't Lua-shaped.
- **`test_signatures.py`** (~3 tests) — `pattern_id` stability across formatting variations (whitespace, numeric values, quoted strings) and `signature` uniqueness across mods.

Invocation: `python -m unittest discover tools/pz-analyzer/tests/`. No external deps.

## Verification

End-to-end smoke against the redacted real-data directory:

```
bash /opt/ik-codex/tools/pz-analyzer/pz_redact_all.sh   # one-time, already done
python /opt/ik-codex/tools/pz-analyzer/pz_classify.py
```

Expect:
- 6 files scanned, ~30,984 error lines processed.
- A meaningful number of unique signatures and patterns (likely in the low hundreds for signatures; fewer patterns).
- `top_mods` lists the highest-occurrence mods.
- PII audit: no real Steam IDs, IPs, or coordinates in the output JSON (input is already redacted; classifier doesn't introduce PII).

Test invocation: `python -m unittest discover tools/pz-analyzer/tests/` should be all-green.

## Risks and open questions

- **Inferred attribution accuracy.** The 40-line lookback is pzmm's heuristic; it's correct for tightly-paced server bursts but can mis-attribute when an unrelated mod logs in the gap. Surface as `confidence: medium` so consumers can choose to treat them differently. Acceptable for v1; tunable via a constant in `pz_parser.py`.
- **Pzmm targets `console.txt`, we target `DebugLog-server.txt`.** Format overlap is high (both share `Lua((MOD:X))` markers, Caused-by chains, Java exception shapes), but some patterns may be `console.txt`-specific. Tests use `DebugLog-server`-shaped fixtures only.
- **Future PHP port.** `pz_parser.py` is structured for mechanical translation to a `LuaErrorAnalyser` / `ModAttributionAnalyser` pair under `src/Analyser/ProjectZomboid/` in a separate spec. Output schema chosen to be PHP-codex-compatible (Insight subclasses with typed fields).
- **Licence.** The `paraxaQQ/pzmm` zip we reviewed has no top-level LICENSE; this spec mandates rewriting the patterns originally rather than copying code. Regex shapes and heuristics are general programming patterns and not author-specific, but no code blocks are lifted verbatim.

## Out of scope (explicit)

- Editing `pz_error_analysis.py` or `pz_redact_all.sh`.
- Modifying any file in `/opt/ik-codex/src/`, `/opt/ik-codex/test/`, or `/opt/iblogs/`.
- AI / LLM integration of any kind in the new tool.
- LLM inference at runtime in iblogs / bosslogs production. The Qwen analyzer (`pz_error_analysis.py`) is a developer-only discovery tool used to expand the deterministic ruleset in `pz_parser.py` (and its future PHP port). Production rendering is deterministic-only, forever.
- iblogs front-end rendering of the classification output.
- Filesystem mod-scan reattribution (pzmm's symbol/vehicle indexes).
