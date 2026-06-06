# ProjectZomboid analyser design (Phase B.1)

## Summary

Implement the three top-priority ServerLog analysers — engine version, mod load order plus missing-mod problems, and server exception coalescing — by adding five Insight classes that plug into the framework's existing `PatternAnalyser`. Wire `ProjectZomboidServerLog::getDefaultAnalyser()` to return a configured `PatternAnalyser` carrying all four insight classes (`ModMissingSolution` is a Solution attached to `ModMissingProblem`, not a separately registered insight).

This document covers Phase B.1. Phase B.2 (PvpDamageAnalyser and AdminAuditAnalyser) ships separately and gets its own spec.

## Scope

- **In scope:** All work needed to make `(new ProjectZomboidServerLog())->setLogFile(path)->parse()->analyse()` return an `Analysis` populated with engine-version information, mod-load information, missing-mod problems with attached solutions, and server-exception problems coalesced by exception type.
- **Out of scope (B.1):** PvP damage, admin audit, codex-side redaction, custom Solution wording for `ServerExceptionProblem`, Hytale/Minecraft/SevenDaysToDie analysers, the empty `src/Analyser/ProjectZomboid/.gitkeep` placeholder.

## Architectural decision: no Analyser subclasses

The original Step-D plan called for a custom `ServerExceptionAnalyser` subclass to capture the tab-indented stack-trace lines that follow each `ERROR` header. On a closer reading of the framework, this is unnecessary:

- `Entry::__toString()` joins all of an entry's `Line`s with `\n`.
- `PatternAnalyser::analyseEntry()` calls `preg_match_all($pattern, $entry, ...)` against the stringified entry.
- A regex with the `s` flag captures across the embedded newlines and grabs the stack body in the same match.

The single `PatternAnalyser` instance configured with multiple insight classes covers all three analysers. No subclassing required.

## Components

All under `src/Analysis/ProjectZomboid/`:

| Class | Type | Purpose | Coalescing |
|---|---|---|---|
| `EngineVersionInformation` | Information | Capture `version=X.Y.Z <hash> <date> <time>` | Always equal (single engine version per file) |
| `ModLoadInformation` | Information | Capture each `loading <modId>` line | Equal when `mod` field matches |
| `ModMissingProblem` | Problem | Capture each `required mod "X" not found` warning; attach a `ModMissingSolution` | Equal when missing-mod name matches |
| `ModMissingSolution` | Solution | Pragmatic guidance ("Subscribe to the missing mod or remove its ID from the `Mods=` line in `serverconfig.ini`.") | n/a |
| `ServerExceptionProblem` | Problem | Capture exception header and the trailing tab-indented stack body in one match (multi-line regex with `s` flag) | Equal when exception-type string matches; first body wins, counter increments |

`ModMissingSolution` is constructed and attached inside `ModMissingProblem::setMatches()` so callers don't have to wire it manually.

`ServerExceptionProblem` overrides `isEqual()` to compare only the exception-type token. This deviates from the default `Information::isEqual` behaviour (label + value match) because the value field includes the (variable) stack body and we want different bodies of the same exception type to coalesce.

## Patterns

All Phase B.1 patterns live on `DebugServerPattern` (Phase A class). Existing constants reused as-is:

- `VERSION` — `/version=(?<version>\S+) (?<hash>[a-f0-9]{40}) (?<date>\d{4}-\d{2}-\d{2}) (?<time>\d{2}:\d{2}:\d{2})/`
- `MOD_LOAD` — `/loading (?<mod>[A-Za-z0-9_]+)\.?$/`
- `MOD_MISSING` — `/required mod "(?<mod>[^"]+)" not found/`

One new constant added:

- `EXCEPTION` — anchored at entry start, captures both the header line and the trailing tab-indented stack body in one match. Named groups: `type` (the FQCN of the thrown exception, parsed from the first body line) and `body` (zero-or-more additional indented stack frames).
  ```
  '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\][^\n]+Exception thrown\n\t(?<type>[A-Za-z0-9_.$]+(?:Exception|Error))[^\n]*(?<body>(?:\n\t.+)*)/'
  ```
  Note `[^\n]+` and `[^\n]*` rather than `.+` keep the regex well-behaved without the `s` flag — each character class explicitly excludes newlines, and `(?:\n\t.+)*` walks the body line-by-line. `$` inside the type class allows nested-class names like `IsoPropertyType$IsoPropertyTypeNotFoundException`.

The existing `EXCEPTION_HEADER` constant stays for any caller that only needs the header line; `EXCEPTION` is the one `ServerExceptionProblem` registers in `getPatterns()`.

## Wiring

`ProjectZomboidServerLog::getDefaultAnalyser()` changes from:

```php
return new PatternAnalyser();
```

to:

```php
return (new PatternAnalyser())
    ->addPossibleInsightClass(EngineVersionInformation::class)
    ->addPossibleInsightClass(ModLoadInformation::class)
    ->addPossibleInsightClass(ModMissingProblem::class)
    ->addPossibleInsightClass(ServerExceptionProblem::class);
```

The other ten ProjectZomboid log subclasses keep their empty `new PatternAnalyser()` stubs until Phase B.2 (PvpLog, AdminLog) and beyond.

## Test plan

Unit tests under `test/tests/Games/ProjectZomboid/Analysis/` (one per Insight class):

- `EngineVersionInformationTest` — `getPatterns()` returns the expected regex; `setMatches` populates label/value; `getMessage` reads as `"Engine version: 42.16.3 (build 0000…0000)"` or similar concise form.
- `ModLoadInformationTest` — `setMatches` extracts `mod`; two instances with the same mod compare equal; two with different mods compare not-equal.
- `ModMissingProblemTest` — `setMatches` extracts the missing-mod name; the problem carries exactly one `ModMissingSolution`; isEqual coalesces same name.
- `ServerExceptionProblemTest` — `setMatches` extracts both type and body; isEqual returns true for same type with different bodies; isEqual returns false for different types.

End-to-end test under `test/tests/Games/ProjectZomboid/Analyser/`:

- `ServerLogAnalysisTest::testAnalyseProducesExpectedInsights` — feeds existing `debug-server-minimal.txt` through `(new ProjectZomboidServerLog())->setLogFile(...)->parse()->analyse()`. Asserts:
  - 1× `EngineVersionInformation` (one version banner in fixture)
  - 3× `ModLoadInformation` (alpha/beta/gamma)
  - 1× `ModMissingProblem` (absent_mod) carrying 1× `ModMissingSolution`
  - 2× `ServerExceptionProblem` (NoSuchFileException + IsoPropertyTypeNotFoundException, distinct types so no coalescing in this fixture)

## Fixture changes

None. The existing synthetic `test/src/Games/ProjectZomboid/fixtures/debug-server-minimal.txt` already contains exactly the lines required for the end-to-end test above. No new identifiers or coordinates introduced.

## Commits (planned)

Following CLAUDE.md workflow conventions (one logical concept per commit, run `composer test` between):

1. `Document Phase B.1 ServerLog analyser design` — this spec file under `docs/superpowers/specs/`.
2. `pre-phase-B checkpoint` — `git commit --allow-empty`.
3. `Add EngineVersionInformation insight` — Insight class + unit test.
4. `Add ModLoadInformation insight` — Insight class + unit test.
5. `Add ModMissingProblem and ModMissingSolution` — Problem + Solution paired (Solution belongs to Problem, ships together).
6. `Add ServerExceptionProblem insight` — includes the new `DebugServerPattern::EXCEPTION` constant; Problem + unit test.
7. `Wire ProjectZomboidServerLog default analyser + end-to-end test` — modifies `ProjectZomboidServerLog::getDefaultAnalyser`, adds `ServerLogAnalysisTest`.

Total: 7 commits expected (6 if the empty checkpoint produces no diff and we skip per the workflow rule — but it always produces a diff because it's `--allow-empty`).

## Open issues

None. All Phase B.1 ambiguity was resolved in the question table preceding this spec.

## Pointers

- Phase A (foundation): commits `8ae7da5` through `cca5208` — the 11 Log subclasses, 11 Pattern classes, and `ProjectZomboidDetective` wiring this builds on.
- Workflow conventions: `CLAUDE.md` § Workflow conventions and § Pitfalls.
- Privacy boundary: `CLAUDE.md` § Privacy / fixture rules.
