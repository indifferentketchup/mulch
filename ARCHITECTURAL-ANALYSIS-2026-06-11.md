---
title: "Architectural Analysis: iblogs + codex-pz (wiring, large-log performance, frontend)"
focus_area: "iblogs/src + iblogs/web + worker.php, codex-pz/src, and the composer path seam between them"
size: "large — two first-party packages (73 + 147 PHP files), data signal (MongoDB), system-seam signal (composer path dependency), performance driving concern"
roster: "han.core:structural-analyst, han.core:behavioral-analyst, han.core:concurrency-analyst, han.core:data-engineer, han.core:on-call-engineer, han.core:user-experience-designer, han.core:risk-analyst, han.core:software-architect, han.core:system-architect"
git_available: "yes — but monorepo history is only 2 commits (subdirectory histories stripped), so churn evidence was unavailable in practice"
generated: "2026-06-11"
generated_by: "han.core:architectural-analysis"
sections_included:
  - executive_summary
  - structural_analysis
  - behavioral_analysis
  - concurrency_analysis
  - data_engineering_analysis
  - on_call_resilience
  - ux_analysis
  - risk_assessment
  - software_architecture_recommendations
  - system_architecture_recommendations
---

# Architectural Analysis: iblogs + codex-pz

## How to Read This Report

This report analyzes the architecture of **the iblogs web app, the codex-pz parsing framework, and the seam between them**. It is layered: each analysis section is the verbatim output of one specialist agent, and the Executive Summary is the only synthesized prose.

- **Executive Summary.** The shape of the architecture, the few findings that matter most, and the highest-impact recommendations. Read this if you have two minutes.
- **Analysis sections** (Structural, Behavioral, Concurrency, Data-Engineering, On-Call Resilience, UX). Each is a specialist's full findings with file paths and verbatim code. Findings carry stable IDs (`S#`, `B#`, `C#`, `DE-###`, `OCE-###`, `UX-###`) you can cite in tickets and follow-up work.
- **Risk Assessment.** `R#` items scoring the structural / behavioral / concurrency findings by likelihood, severity, blast radius, and reversibility.
- **Software-Architecture Recommendations.** `A#` recommendations with pseudocode sketches, each cross-referencing the findings that drove it.
- **System-Architecture Recommendations.** `SA#` recommendations and a context map for the iblogs ↔ codex-pz package seam.

> Sizing and roster: this run was classified **large** and dispatched 9 agents. Sections not part of this run: no Security section (`adversarial-security-analyst` omitted by the band cap — token/PII surface exists; re-run or dispatch separately if wanted); no DevOps Readiness section (`devops-engineer` omitted — `on-call-engineer` was preferred because the focus area is application source; note no CI pipeline exists at all, see SA2); no Codebase Map (`codebase-explorer` not needed — CLAUDE.md files already map the architecture). A UX Analysis section (non-standard for this skill) was added at the user's request. The `aternos/*` directories were excluded as read-only upstream references.

---

## Executive Summary

**Focus area:** The iblogs web app (`iblogs/src`, `iblogs/web`, `worker.php`), the codex-pz log-parsing framework (`codex-pz/src`), and the Composer path-dependency seam joining them. Driving concerns: (1) is the codex-pz wiring correct and complete, (2) huge logs are slow, (3) make the UI better.

**Bottom line:** The wiring is *functionally* live (Minecraft, Hytale, and Project Zomboid detectives are registered and the template is forward-compatible with codex-pz's full capability surface), but the seam is broken at the build level and the runtime is architecturally unprotected against exactly the workload it exists for: large logs. There is no caching anywhere despite a fully built cache facility, every read re-runs the full parse+analyse pipeline, and a single domain class (`Log`) fuses storage, parsing, metrics, and presentation so there is no seam at which to fix any of it cheaply.

**Most critical findings (across all dimensions):**

- **S1 / R2 (High):** `iblogs/composer.json` still points its path repository at `/opt/ik-codex`, which no longer exists. The app only runs because `vendor/` is committed. Any clean install, CI run, or Docker rebuild fails. Fix: `../codex-pz`.
- **B4 / DE-002 / C8 / R3 (High):** Every view of a log re-runs full detect → parse → analyse → render (50–100 MB peak heap for a max-size log), while `CacheEntry` + the Mongo `cache` collection + its TTL index ship fully built and are **never called**. This is the direct cause of "huge logs slow things down."
- **S5 / B5 / C10 / R1 (High):** `Log::getPageDescription()` and `log.php:66` call `getAnalysis()->...` with no null guard. Any upload that doesn't match a detective (plain text, unknown game) stores fine but **500s on every view**.
- **OCE-001/002/003/006 (blockers):** `/1/analyse` parses up to 20 MB with no limits; backtracking-prone redaction regexes never check `preg_replace` failure (silent content corruption to empty); the worker loop has no try/finally exception boundary; no time/memory guard around parse in a persistent worker.
- **S8 / B13 / R11 (Med, wiring):** Every upload is wrapped in `StringLogFile` (path always null), so codex-pz's `FilenameDetector` — the 0.95-weight *primary* detector for all 11 PZ log classes — is permanently dead. The uploader's `source` filename hint is stored but never reaches detection. Also: codex-pz now ships a `SevenDaysToDie` detective (empty stub) that iblogs does not register (S3 — safe today, a silent gap when it becomes real).

**Highest-impact recommendations:**

- **SA1:** Fix the composer path to `../codex-pz` and make clean-install a CI-enforced contract (SA2 adds a seam check that fails CI when a codex-pz detective isn't registered or allow-listed in iblogs).
- **A1 → A2:** Make parsing lazy behind a single `getCodexLog()` seam in `Log` (kills the redundant-parse cluster), then wire the existing dead `CacheEntry` for analysis/render output keyed by `content-hash + codex-pz version` (SA7), with invalidation in `Log::delete()` first.
- **A4 + A9a:** Worker-loop try/finally + `set_time_limit` + structured log line; null-guard every `getAnalysis()` consumer. These are the cheap fixes for the three blocker-class failure modes.
- **A5 / SA3:** Add a path-hint-bearing `LogFile` to codex-pz and thread the stored `source` into detection, resurrecting `FilenameDetector`.
- **UX quick wins:** wire or demote the dead `#error-toggle` button, make the scroll/fold controls real `<button>`s, remove `maximum-scale=1`, map upload errors to human language; structural UI win = server-side pre-folding once caching lands (UX-005).

**Clean dimensions and omitted domains:** No deadlocks or true data races exist (shared-nothing FrankenPHP workers — concurrency findings are request-state-bleed and cross-process TOCTOU only). Filter ordering (limits before regex) is correct. The template ↔ codex-pz capability wiring (severity, mod-attribution, cause-chain interfaces) is structurally complete and forward-compatible. Omitted by the band cap: `adversarial-security-analyst` (guessable `rand()` IDs, token handling, and PII gaps were flagged by other agents — a dedicated security pass is worth a follow-up run) and `devops-engineer` (no CI, single-node Mongo, no observability — flagged but not audited).

---
## Structural Analysis

> Verbatim output from `structural-analyst`. `S#` findings on module boundaries, coupling, dependency direction, abstractions, and duplication.

**S1: Stale Composer path repository — codex-pz is unreachable for fresh installs**
- **Dimension:** Coupling
- **File(s):** `iblogs/composer.json`
- **Finding:** The `repositories` block points to `/opt/ik-codex`, a path that does not exist on this host. The locked entry in `composer.lock` records this same path as the resolved dist source. The package now lives at `codex-pz/`. Running `composer install` on a fresh checkout or inside a Docker build that does not have `/opt/ik-codex` mounted will fail to resolve `indifferentketchup/codex-pz`. Production builds currently rely entirely on the vendor snapshot already present. `composer update indifferentketchup/codex-pz` will also fail.
- **Impact:** The cross-repo dependency is broken at the Composer level for any environment that does not reproduce the original `/opt/ik-codex` path. Correct value: `../codex-pz` (relative) or the absolute monorepo path.

**S2: `Log::getLogfile()` implements `LogInterface::getLogFile()` under a mismatched name — interface contract violation**
- **File(s):** `codex-pz/src/Log/LogInterface.php:39`, `codex-pz/src/Log/Log.php:51`
- **Finding:** Interface declares `getLogFile(): LogFileInterface`; concrete class implements `getLogfile()` (lowercase f). PHP dispatch is case-insensitive so it works at runtime, but static analysis, IDEs, and any future subclass overriding `getLogFile()` will misfire (the override would leave `getLogfile()` unoverridden).
- **Impact:** Silently inconsistent public API; subtle dispatch bugs for subclasses.

**S3: `SevenDaysToDie` detective exists in codex-pz but is omitted from iblogs's Detective — currently safe but undocumented**
- **File(s):** `iblogs/src/Detective.php`, `codex-pz/src/Detective/SevenDaysToDie/SevenDaysToDieDetective.php`
- **Finding:** iblogs registers Minecraft, Hytale, ProjectZomboid only. `SevenDaysToDieDetective` is an empty stub (`// TODO`) registering no log classes, so the omission is structurally safe today — but there is no comment or doc on the iblogs side flagging the gap. When 7DTD detection becomes real in codex-pz, iblogs needs a synchronized update with no signal prompting it.
- **Impact:** Low now; medium coordination risk later.

**S4: `IPv4Filter` and `IPv6Filter` are dead code — superseded by `ProjectZomboidRedactorFilter` but never removed**
- **File(s):** `iblogs/src/Filter/IPv4Filter.php`, `IPv6Filter.php`, `Filter.php`
- **Finding:** `Filter::getAll()` registers Trim, LimitBytes, LimitLines, ProjectZomboidRedactor, Username, AccessToken. The IP filters are instantiated nowhere. The `/1/filters` endpoint exposes the active list, so clients never see them either.
- **Impact:** Cognitive noise; risk of accidental re-registration with divergent exemption lists and replacement tokens.

**S5: `Log::getPageDescription()` dereferences `getAnalysis()` without null guard — crash on non-analysable log types**
- **File(s):** `iblogs/src/Log.php:504`, `iblogs/web/frontend/log.php:66`
- **Finding:** `getAnalysis(): ?Analysis` returns null when the codex log is not `AnalysableLogInterface` (the default fallback `Log` class). `getPageDescription()` calls `$this->getAnalysis()->getProblems()` unguarded; `log.php:66` calls `$log->getAnalysis()->getInformation()` unguarded (line 104 correctly uses `?->`).
- **Impact:** Fatal `TypeError` on the view page and meta description for any log the Detective cannot classify.

**S6: `Log::process()` runs full parse + analyse on every `setContent()` call — and save() re-triggers it**
- **File(s):** `iblogs/src/Log.php:215–231`
- **Finding:** `processAndDeobfuscate()` runs detect+parse+analyse; `Deobfuscator::deobfuscate()` is a no-op returning null. `save()` calls `setContent($content)` after `insertOne`, so every upload performs the full pipeline (detect/parse not cached; analyse memoized inside codex).
- **Impact:** Doubled parsing CPU at upload for max-size logs; structural cause is `save()` reprocessing instead of retaining state.

**S7: `LimitLinesFilter` splits on `"\n"` while parsers split on `PHP_EOL` — line-ending mismatch**
- **File(s):** `iblogs/src/Filter/LimitLinesFilter.php:21`, `codex-pz/src/Parser/Parser.php:45`
- **Finding:** Latent divergence for `\r\n` content; currently aligned because the server is Linux.
- **Impact:** Wrong line counts / single-entry parses for CRLF logs in any non-Linux context.

**S8: `StringLogFile` always returns null from `getPath()`, making `FilenameDetector` a no-op for all iblogs uploads**
- **File(s):** `iblogs/src/Log.php:226`, `codex-pz/src/Log/File/StringLogFile.php`, `codex-pz/src/Detective/FilenameDetector.php`
- **Finding:** All 11 PZ log subclasses register `FilenameDetector` (weight 0.95) as their primary fast-path detector; with a null path it returns false immediately. Every PZ paste pays full content-scan detection; the uploader's `source` filename hint is stored on the Log but never threaded into detection.
- **Impact:** Slower detection on every paste; reduced accuracy for format-ambiguous PZ logs (e.g. CmdLog vs AdminLog).

**S9: `Log::getAnalysis()` doesn't retain its result — leaky reliance on codex memoization, called 3×/render**
- **File(s):** `iblogs/src/Log.php:248–255`, `codex-pz/src/Log/AnalysableLog.php:27–38`
- **Finding:** Cheap at runtime (codex memoizes), but the iblogs-level accessor re-dispatches each call; callers must know codex internals to reason about identity. Leaky abstraction at the package seam.

**S10: `LogResponse::jsonSerialize()` mutates the shared codex log via `setIncludeEntries(false)`**
- **File(s):** `iblogs/src/Api/Response/LogResponse.php:54`
- **Finding:** Serialization permanently flips `includeEntries` on the shared instance for the rest of the request. Structurally unsafe pattern; silently misbehaves if Log objects are ever reused across responses.

**S11: `Filter::$filter` static cache persists across FrankenPHP worker requests — incomplete reset protocol**
- **File(s):** `iblogs/src/Filter/Filter.php:9–29`, `iblogs/worker.php`
- **Finding:** worker.php resets only `MongoDBClient` and `URL` between requests. `Filter::$filter` (and config limits baked into filter instances) persist for the worker lifetime. The inter-request reset protocol is an undocumented invariant: every other static must be stateless.

**S12: Minecraft and Hytale logs deliver empty analyses — template capability coupling is broad but mostly dormant**
- **File(s):** `codex-pz/src/Log/Hytale/HytaleServerLog.php`, `codex-pz/src/Log/Minecraft/MinecraftLog.php`, `iblogs/web/frontend/log.php`
- **Finding:** Both `getDefaultAnalyser()` return `PatternAnalyser` with zero insight classes. The template's full capability branches (`SeverityAwareInsightInterface`, `EngineNoiseInsightInterface`, `ModAttributedInsightInterface`, `CauseChainInsightInterface`) only ever activate for PZ. By design: when those analysers are populated supplier-side, iblogs surfaces them with no code change.

**S13: codex-pz `LogInterface` doesn't declare `setIncludeEntries()` (or correctly-cased `getLogFile`) — callers depend on the concrete class**
- **File(s):** `codex-pz/src/Log/LogInterface.php`, `iblogs/src/Log.php:212`, `LogResponse.php:54`, `LogInsightsAction.php:27`
- **Finding:** The interface is narrower than the contract iblogs actually consumes — leaky abstraction at the seam.

**S14: CLAUDE.md documentation in both packages carries stale paths and mismatched version constraints**
- **Finding:** `iblogs/CLAUDE.md` says sibling at `/opt/ik-codex-pz` and constraint `^0.5.0`; `codex-pz/CLAUDE.md` says iblogs constraint `^0.3.0` and call sites at `/opt/iblogs/src/...`; actual composer.json says `^0.6.0` via path repo. Three contradictory values across docs and reality.
- **Impact:** Misleads AI-assisted development into wrong edits and wrong paths.

**S15: Dead IP filters duplicate `ProjectZomboidRedactor` IP regexes with different precision and replacement tokens**
- **Finding:** Old filters match `999.999.999.999` and replace with `**.**.**.**`; the redactor validates octets and replaces with `[REDACTED_IP]`, no exemptions. Re-registration would produce inconsistent redaction. Migration leftovers.

**S16: A new `Detective` object graph (3 sub-detectives, 11 PZ class registrations) is built on every `setContent()`**
- **File(s):** `iblogs/src/Log.php:226`
- **Finding:** The Detective is a pure-function object holding only the class list, yet re-instantiated on every save and every find. A cached/static factory would eliminate the repeated construction.

**S17: `LogFileInterface::getContent(): string` forecloses streaming — memory-efficient processing structurally impossible**
- **File(s):** `codex-pz/src/Log/File/StreamLogFile.php`, `LogFileInterface.php`
- **Finding:** Even `StreamLogFile` fully buffers (`fread` loop into a string). Parsers `explode(PHP_EOL, ...)` produce a second copy — ~2× content size in memory before analysis begins. Nothing in `LogFileInterface` or `ParserInterface` admits a line-at-a-time path; fixing this is a cross-package breaking change.

**Structural Summary.** Key concerns: the Composer path is broken at the infrastructure level (S1); the filename-based detection shortcut is permanently disabled for all uploads (S8); null-dereference crash path on non-analysable logs (S5). Well-structured areas: the Filter pipeline (clean abstract base, single choke point, correct migration of IP filters out of `getAll()`); the codex-pz Analysis capability hierarchy correctly wired into `log.php` with safe `instanceof` checks; `AnalysableLog::analyse()` memoization; the worker-loop reset placement for the two stateful singletons it does cover. Churn analysis skipped: only 2 commits of monorepo history.

---
## Behavioral Analysis

> Verbatim output from `behavioral-analyst`. `B#` findings on data flow, error propagation, state management, and integration boundaries.

**B1: Full parse+analyse executed on save — filter chain runs, then the pipeline, before the HTTP response**
- `Log::save()` runs `Filter::filterAll($content)`, inserts to Mongo, then calls `setContent($content)` → `processAndDeobfuscate()` → `process()` (detect + parse + analyse). `Deobfuscator::deobfuscate()` is a no-op today, but the double-process path exists if it ever returns content. A 10 MB upload is fully parsed synchronously before the 201 returns.

**B2: Filter ordering is correct — limits run before expensive regex filters**
- Chain: Trim → LimitBytes → LimitLines → ProjectZomboidRedactor (9 regex passes) → Username (6) → AccessToken (4). `LimitLinesFilter` explodes up to 25k lines into an array per upload (bounded, upload-only). IPv4/IPv6 filters confirmed not registered; IP scrubbing rides entirely on the PZ redactor for all log types.

**B3: `preg_replace_callback` null returns unchecked — silent content corruption or unhandled TypeError**
- `RegexFilter::filter()` and `ProjectZomboidRedactor::redact()` assign `preg_replace*` results straight back to the content variable. PCRE backtrack exhaustion or bad UTF-8 (with `/u`) returns `null`; the upload then either corrupts stored content toward empty or throws a `TypeError` that nothing catches (`CreateLogAction` → `ApiAction::run` have no try/catch).

**B4: Every `Log::find()` re-runs full detect+parse+analyse — `CacheEntry` infrastructure exists but is never called**
- `find()` → `fromObject()` → `setContent()` → full pipeline. `CacheEntry` + Mongo cache collection + TTL index are wired (schema and index exist) but no Action or read path ever instantiates `CacheEntry`. ViewLogAction per request: Mongo read → detect → parse → analyse → `Printer::print()` (iterates all entries again). LogInsightsAction: same minus print. No reuse across requests.

**B5: `getAnalysis()` null-deref in `getPageDescription()` and `log.php:66`** — same as S5; line 104 guards with `?->`, line 66 does not. Any undetectable log = 500 on view.

**B6: `AnalysableLog::analyse()` memoization works within a request only** — `log.php` calls `getAnalysis()` 3×; memoization collapses that to one analyser pass per request. No cross-request benefit; concurrent views of a popular log each run full analysis.

**B7: `Filter::$filter` static persists; `Config` reads config.json once at process start** — stateless today, latent risk for future stateful filters; config file edits require a worker restart (undocumented). Env vars are read live via `getenv()` (positive).

**B8: `MongoDBClient::reset()` per request — new Client wrapper per request**
- worker.php resets the connection at the start of every request; `connect()` lazily builds a `new Client(...)` (re-parsing the URL, re-selecting the DB). `serverSelectionTimeoutMS=5000`: if Mongo is slow, every worker blocks up to 5 s. Mongo failures propagate as unhandled exceptions → raw 500s with no friendly error.

**B9: `ensureIndexes()` failure at startup is swallowed (error_log only)** — without the TTL index, logs never expire and accumulate indefinitely; the startup connection is also discarded by the first request's reset.

**B10: `Id::generate()` uses `rand()`; `generateId()` is check-then-insert (TOCTOU)**
- `do { new Id() } while (hasLog($id))` then a separate `insertOne`. Two workers can pass the check and collide; duplicate-key `BulkWriteException` is uncaught. `rand()` is predictable and poorly seeded across simultaneously-started workers. `Token::generate()` already uses `random_bytes` — the correct pattern exists in-repo.

**B11: `hasLog()` fetches the full document (up to 10 MB of content) just to test existence** — on every upload; `findLog(includeContent:false)` exists and is unused here.

**B12: SevenDaysToDie not registered in iblogs** — codex-pz stub registers no log classes, so registration would add nothing today; 7DtD logs fall to the base `Log` class → no analysis + the B5 null-deref on view.

**B13: `FilenameDetector` always returns false for API uploads** — `StringLogFile` path is always null; the `source` parameter is stored in Mongo but never used to populate the log file's path. Filename detection is dead code for the entire product.

**B14: `LinePatternDetector` is O(lines) per candidate but currently unused** — PZ classes use FilenameDetector + WeightedSinglePatternDetector; Hytale/Minecraft use FirstLinesPatternDetector. Latent only.

**B15: `Printer::print()` builds the full HTML string in memory before echo**
- `printLog()` concatenates every entry's HTML (3–4 nested divs per line). For a 25k-line log the rendered string can reach 50–100 MB, held simultaneously with the raw content and `Entry[]` array. No streaming or chunked output anywhere in the view path.

**B16: `Analysis::addInsight()` does an O(N) linear dedup scan per insert** — O(N²) for logs producing many distinct insights. Stack traces capped at 500; pattern insights uncapped.

**B17: `AnalyseLogAction` (/1/analyse) bypasses `Filter::filterAll()` entirely**
- `new Log()->setContent($content)` directly: no byte/line limits, no PII scrubbing. Steam IDs, player names, IPs round-trip in the JSON response.

**B18: `ContentParser` accepts 2× STORAGE_LIMIT_BYTES (20 MB) and never re-checks size after decompression** — `gzinflate`/`gzdecode` are correctly capped at `$limit`, but the analyse path (B17) can receive up to 20 MB of decompressed content with no further limit.

**B19: `URL::getCurrent()` reads `$_SERVER['REQUEST_SCHEME']` with no fallback** — `readProtocol()` has a full fallback chain but `getCurrent()` doesn't use it. Under proxies that omit REQUEST_SCHEME: warning + malformed URI; `isApi()` routing depends on it.

**B20: `Singleton::$instances` persists; routers and Action objects are reused per worker** — currently stateless and safe; the implicit "all singletons must be stateless between requests" contract is undocumented and unenforced.

**Behavioral Summary.** Top concerns: (1) no caching of parse/analyse results despite full cache infrastructure (B4); (2) the getAnalysis() null-deref (B5); (3) full parse+analyse on every upload before the response (B1). Well-handled: filter ordering; analyse() memoization; StackTrace HIT_CAP (500); CompositeAnalyser setLog propagation; the worker reset placement for MongoDBClient/URL; gzip decode caps prevent zip-bomb expansion.

---

## Concurrency Analysis

> Verbatim output from `concurrency-analyst`. `C#` findings. Concurrency model: FrankenPHP worker mode — each worker is a persistent single-threaded PHP process (16 prod / 4 dev), shared-nothing between workers. Risks are (a) request-state bleed across successive requests in one worker, (b) unbounded in-worker memory, (c) cross-process races through Mongo/filesystem.

**C1: Router singletons — fragile design invariant.** `register()`/`setDefaultAction()` are public but only called from constructors today. Any future request-conditional registration would silently accumulate routes across requests. Latent, not triggered.

**C2: `Filter::$filter` static array persists across requests.** Safe today (all filters stateless); invisible invariant that any future filter must remain stateless.

**C3: `Config::getName()` falls back to `URL::getBase()`** — safe in the current sequence; startup code calling it before a request would cache a malformed host. Narrow.

**C4: `MongoDBClient::reset()` tears down the Client on every request.** Topology re-initialization per request × 16 workers; up to 5 s blocking per worker when Mongo is slow to respond. Most actionable pure-performance overhead for small reads; the startup `ensureIndexes()` connection is wasted by the first reset.

**C5: TOCTOU race in log ID generation** — `hasLog()` check and `insertOne()` are not atomic across worker processes; duplicate-key `BulkWriteException` is unguarded. Correct fix: trust the unique `_id` index, catch-and-retry.

**C6: Filter static cache — classified clean/intentional** (config values immutable per process).

**C7: `AssetLoader` cache file** — `file_put_contents` is non-atomic, but `loadCache()` degrades gracefully to per-request `hash_file()` on a bad read. Only risky if build.php re-runs against a live container; the degraded path is silent/unlogged.

**C8: Full parse+analyse on every find(); peak heap 50–100 MB per max-size log per worker.** Raw string + StringLogFile copy + full Entry[] graph live simultaneously; gc runs only after the request. With 4–16 workers each potentially holding a max-size log, instance memory = workers × peak. `memory_limit` fatal → FrankenPHP worker restart is the only backstop. **Primary driver of the "huge logs slow things down" concern.**

**C9: `rand()` seeding** — workers started in the same second share seeds; the first IDs generated by each of 16 workers after a deploy/recycle are likely identical, making C5 collisions reliable at startup bursts (15 of 16 simultaneous creations could 500). Fix: `random_int()` (already used in `Token::generate()`).

**C10: `getAnalysis()` null-deref** (same as S5/B5) — the fallback base `Log` class is reachable whenever detection fails; the stored log becomes unrenderable.

**Concurrency Summary.** Deadlock potential: not applicable (no locks, single-threaded workers, shared-nothing). Async error handling: not applicable (no async constructs). Well-handled: `URL::clear()` correctly placed inside the request callback; cookies/settings/Log objects are per-request; `analyse()` memoization; AssetLoader graceful degradation; `ProjectZomboidRedactor` fully stateless; Config holds only read-only data and reads env live.

---
## Data-Engineering Analysis

> Verbatim findings from `data-engineer` (DE-###). Workload: write-on-upload, read-heavy document store; one document per paste, up to ~10 MiB content; TTL 90 days; PII-bearing user content.

**DE-001: Log content stored uncompressed as a single document string, approaching the 16 MB BSON limit** — `Log.php:356-364` stores `"data" => $content` raw. Logs compress 5–15×; `ext-zlib` is already a hard dependency but used only for request decompression. Raising STORAGE_LIMIT_BYTES toward 16 MiB makes `insertOne` throw. *Remediate:* gzip at rest with a `compressed` flag (P0); stored `size` field so metadata views never hydrate content (P1); GridFS/chunking for oversized content (P2). **Degrades operations.**

**DE-002: Full parse+analysis recomputed on every read; existing cache facility unused** — `CacheEntry` (get/set/TTL) and the indexed `cache` collection ship dead. *Remediate:* read-through cache of analysis/render output keyed by id + content/version hash (P0); cache rendered output, invalidate on delete (P1); precompute analysis at upload as a write-time read model (P2). **Blocks the stated performance goal.**

**DE-003: Non-atomic ID generation (check-then-insert TOCTOU) with `rand()` and no duplicate-key handling** — collision = uncaught BulkWriteException = lost upload; `rand()` also makes IDs guessable (enumeration risk — cross-ref security). *Remediate:* `random_int` + catch duplicate-key + bounded retry (P0); remove the `hasLog` pre-check loop (P1). **Blocks correctness.**

**DE-004: Full-document fetch where only metadata/token is needed** — `DeleteLogAction`, `LogInsightsAction`, `ViewLogAction`, `RawLogAction` all use default `includeContent: true`; `findLog(includeContent:false)` exists and is proven in `BulkDeleteLogsAction`. Delete pulls up to 10 MiB to authorize a token then discards it. **Degrades operations.**

**DE-005: TTL renewal inconsistent** — only the HTML `ViewLogAction` renews; raw/insights traffic never does, so hotlinked logs vanish at 90 days under active use; meanwhile every browser view incurs a write (`renew()` → `updateOne`). *Remediate:* one explicit policy across read actions; throttle renewal. **Operational friction.**

**DE-006: Upload non-idempotent** — client retries create duplicate logs with independent ids/tokens. Optional `Idempotency-Key`. **Operational friction.**

**DE-007: `getSize()`/`getLinesCount()`/`getErrorsCount()` recompute via full passes per call** — `getPageDescription()` triggers several full iterations of a multi-MB structure for values immutable once stored. *Remediate:* memoize per request (P0); persist size/lineCount/errorCount/problemCount at write time (P1). **Degrades operations.**

**DE-008: PII coverage rides entirely on the PZ redactor; UsernameFilter matches only fixed path/env shapes** — misses are stored permanently in plaintext and served raw. *Remediate:* classify the `data` field as residual-PII; verify the PZ IP regexes run on non-PZ pastes; re-add explicit IP filters or broaden coverage; redaction-coverage test corpus. **Degrades operations (governance).**

**DE-009: No per-record erasure path beyond TTL** — deletion requires the creator token; no operator delete-by-id; future cache entries would outlive deleted sources unless invalidation is wired into `Log::delete()` *before* caching ships. **Degrades operations; escalates if an erasure obligation exists (open question).**

**DE-010: `cache` collection + `CacheEntry` are dead machinery (YAGNI as-shipped, but the right tool)** — adopt for DE-002 rather than delete; delete only if DE-002 is declined.

**DE-011: `ensureIndexes` per worker boot; legacy-ID fallback doubles every Mongo miss** — `findLog`/`deleteLog` issue a second query against `substr($id,1)` on every not-found; 404 storms (bot probes) cost 2× ops. Gate on legacy-shape predicate or backfill and remove. **Operational friction.**

**DE-012: `MetadataEntry::getDisplayValue(): string` returns `mixed`** — numeric/bool metadata value = TypeError = 500 at render. Cast/normalize. **Polish.**

Severity counts: Blocks correctness 2 (DE-002, DE-003) · Degrades operations 4 · Friction 3 · Polish 2 · YAGNI 1. Open questions: production read:write ratio and p99 doc size; whether a right-to-erasure obligation exists (privacy URL is published).

---

## On-Call Resilience

> Verbatim findings from `on-call-engineer` (OCE-###). Application source only. Failure profile: CPU/memory exhaustion of a long-lived FrankenPHP worker pinned by one giant parse, with a secondary ReDoS path through redaction regexes; on-call sees almost nothing (only logging in app source is one error_log on index creation).

**Wakes someone up (4):**
- **OCE-001: `/1/analyse` parses fully unbounded content** — `AnalyseLogAction.php:21-24` runs detect+parse+analyse on up to ~20 MB with no `Filter::filterAll`, no limits. Pure compute amplification, repeatable, nothing stored. *Today:* reject > STORAGE_LIMIT_BYTES/LINES with a 413. *Paved path:* a `BoundedLogInput` value object all entry points must construct from.
- **OCE-002: Backtracking-prone IP/redaction regexes over multi-MB content with no PCRE-failure handling** — `ProjectZomboidRedactor.php:49-57` IPv6 alternation + nested quantifiers; `preg_replace*` null returns never checked (`RegexFilter.php:48-55`). Either a CPU pin (worker starvation) or — if `pcre.backtrack_limit` aborts — content silently overwritten with null and stored/served empty (gray failure). *Today:* null-check every `preg_replace*`, keep prior content, emit a metric. *Fix the patterns in codex-pz (the pattern source), not iblogs.*
- **OCE-003: No per-request exception boundary in the worker loop** — `worker.php:21-31` has no try/catch/finally; resets run at iteration start, not in `finally`. `PatternParser` throws `InvalidArgumentException` on match-count mismatch and nothing catches it. *Today:* try/catch(\Throwable) + move resets into finally + structured error line.
- **OCE-006: No time or memory guard around synchronous parse in a persistent worker** — no `set_time_limit` anywhere in app source; safety lives entirely in ini config the source ignores. *Today:* `set_time_limit($budget)` at the top of `Log::process()`.

**Degrades reliability (5):** OCE-004 every read (even `/1/raw`) pays full parse+analyse — fetch the stored string directly, make parsing lazy, then cache rendered output in the existing `cache` collection. OCE-005 ContentParser buffers 2× the limit (20 MB) before any truncation, with up to 5 chained decompression passes — truncate immediately post-decode, drop MAX_ENCODING_STEPS to 1–2. OCE-007 `mb_strcut` at the 10 MB boundary can feed invalid UTF-8 into `/u` redaction passes → null → blank stored log — same null-check fix + `mb_check_encoding` sanitize after LimitBytes. OCE-009 zero hot-path observability (no parse timing, no content-size metric, no correlation id) — one structured log line per request at the worker boundary is the multiplier on every other finding. (OCE-003 also listed here as boundary work.)

**On-call friction (3):** OCE-008 non-idempotent upload (duplicates on proxy retry). OCE-010 Mongo `socketTimeoutMS` default 60 s — a gray-failing Mongo holds a worker a full minute per query; lower to 5–10 s + `maxTimeMS`. OCE-011 legacy-ID fallback doubles Mongo round-trips on every miss — exactly the bot-probe traffic pattern.

**Polish:** OCE-012 `Detective::detect` runs every candidate's detectors over full content with no early budget (covered once input is bounded). **YAGNI:** OCE-013 `RateLimitErrorAction` is a control-shaped artifact with no enforcement behind it in app source — document as external-only or implement a real bucket; the danger is an operator believing rate limiting exists.

**Verdict:** Block shipping on OCE-001/002/003/006 — each is independently a worker-pin or silent-corruption path reachable by a single request, and multi-MB uploads are the expected workload, not an edge case. Open questions: do the IPv6 patterns actually backtrack catastrophically under the deployed `pcre.backtrack_limit` (decides pin vs silent truncation); does `mb_strcut` + `/u` actually produce the null path (decides OCE-007 severity).

---
## UX Analysis

> Verbatim findings from `user-experience-designer` (UX-###), added to this run at the user's request. Two personas: the *uploader* (paste/share after a crash) and the *reader* (a helper opening a shared link to diagnose). Severity counts: Blocks 0 · Degrades task 6 · Friction 9 · Polish 4.

**Degrades task (6):**
- **UX-001:** Drag-to-reveal fold bars (`log.js:421-464`) have no keyboard or screen-reader equivalent — keyboard/SR readers can only "expand all" or nothing. Make the fold bar a real button with Enter/Space + arrow-key reveal. *(moderate)*
- **UX-002:** Deep-link `#L` anchors can land on a folded (hidden) line — "look at line 4120" appears to do nothing. Auto-reveal the anchored entry's fold run. *(moderate)*
- **UX-004:** No visibility of system status while a large log compresses/uploads (`start.js:58-116`) — just a `btn-working` class for many seconds. Add phase text ("Compressing… / Uploading…") and progress. *(moderate)*
- **UX-005:** Server renders the entire log as one DOM blob (`log.php:211`); the client-side smart-fold then reflows the page after first paint. The right long-term fix is server-side pre-folding / chunked or virtualized rendering; interim, a skeleton/"Analysing N lines…" state during `is-folding`. *(structural — pair with the perf push)*
- **UX-010:** Primary log controls (`#down-button`, `#up-button`, `#error-toggle`) are `<div>`s with click handlers — not focusable, not announced. Convert to `<button>`. *(quick win)*
- **UX-013:** `maximum-scale=1` in the viewport meta (`parts/head.php:28`) blocks pinch-zoom on 0.75rem monospace text. Remove it. *(quick win)*

**Friction (9):** UX-003 filters silently rewrite/truncate the log before sharing with no "removed 3 IPs, truncated to 25,000 lines" disclosure. UX-006 upload errors surface as raw "429 (Too Many Requests)" — map to plain language with next steps. **UX-007 the red error-count chip (`#error-toggle`, `log.php:48-53`) has button chrome but no click handler anywhere in the JS — a dead signifier at the core diagnostic moment**; wire it to jump to errors or restyle as a static chip. UX-008 copy/settings changes have no `aria-live` announcement. UX-011 warning-vs-error severity in the log body is conveyed by color alone (WCAG 1.4.1). UX-012 `#facc15` warning text likely fails 4.5:1 contrast (verify against shipped FRONTEND_COLOR_* palette). UX-014 the typewriter header loop and infinite save-button pulse ignore `prefers-reduced-motion` (the only guarded block covers the problems panel). UX-015 no skip link / landmark regions for a giant log page. UX-016 the problems panel — the product's highest-value output — sits below metadata with no working jump control and no sticky access.

**Polish (4):** UX-009 fold-bar drag semantics undocumented in-UI; UX-017 no "Analysis complete — no problems detected" empty state; UX-018 delete flow is safe (proper confirm, neutral cancel — no dark patterns found) but has no undo/confirmation; UX-019 the save button pulses forever once content exists.

**Priority order:** (1) real buttons + wire the error chip (UX-007/010); (2) keyboard-operable fold bars + anchor auto-reveal (UX-001/002); (3) remove maximum-scale (UX-013); (4) upload progress + fold/render status, then server-side pre-folding with the perf work (UX-004/005); (5) human error messages + redaction disclosure (UX-006/003); (6) non-color severity cues + theme-derived contrast (UX-011/012); (7) reduced-motion gating + aria-live (UX-014/008); (8) promote the problems panel + empty state (UX-016/017).

---

## Risk Assessment

> Verbatim output from `risk-analyst`. 38 S/B/C findings consolidated into 17 R# items. Scores: likelihood / severity / blast radius / reversibility.

**High (3):**
- **R1 (S5, B5, C10): Fatal 500 on every view of an undetectable log.** Near certain · High · single module (view/description paths) · easy fix (`?->` at two call sites). Deferred: any plain-text or unknown-format paste stores fine, then 500s for every visitor of its URL, forever.
- **R2 (S1): Broken Composer path blocks all builds and deploys.** Near certain · High · system-wide (every Composer operation) · easy fix (`../codex-pz`). Deferred: the next image rebuild — including an emergency security patch — fails at the Composer step and the service cannot be redeployed.
- **R3 (B4, B6, C8, S6, S9, S16): No cross-request analysis cache; full parse+analyse on every view.** Near certain · High · system-wide · difficult (cache wiring is tractable; the keying/serialization decisions are the work). Deferred: a popular 10 MB log shared on Discord → 10 simultaneous views → every worker at 50–100 MB peak → memory_limit hits → FrankenPHP kills workers mid-response. Recurs every spike.

**Medium (5):**
- **R4 (B3):** PCRE null-return corrupts or destroys log content on upload (malformed UTF-8 / backtrack limit). Possible · High · localized · easy.
- **R5 (B15, S17):** Printer builds full HTML (50–100 MB) in memory; compounds R3; proper fix is a cross-package streaming contract change. Likely · High · single module · difficult.
- **R6 (B8, C4):** Mongo topology reconstructed per request; a transient Mongo hiccup blocks all 16 workers for up to 5 s each. Near certain occurrence · Medium severity · system-wide · moderate.
- **R7 (B10, C5, C9):** rand()-seeded IDs + TOCTOU → duplicate-key 500s, reproducible at every deploy's simultaneous worker start. Possible · Medium · localized · easy.
- **R8 (B17, B18):** /1/analyse bypasses filters — PII round-trips in responses; 20 MB unbounded analysis input. Likely · Medium · localized · easy.

**Low (9):** R9 hasLog() full-doc fetch (easy). R10 REQUEST_SCHEME without fallback — would silently misroute ALL /1/* to the frontend router under a proxy change (easy: use readProtocol()). R11 FilenameDetector dead / `source` never threaded into detection (moderate: needs a path-bearing LogFile). R12 setIncludeEntries mutation + interface gap (easy; becomes a real bug once parsed objects are cached). R13 ensureIndexes failure swallowed → silent disk-fill if the TTL index is ever lost. R14 SevenDaysToDie unregistered (future-state only). R15 dead IP filters (delete). R16 getLogFile casing (rename). R17 Filter static cache invariant (document).

**Not assigned an R# (no material current risk):** S12, S14, B1, B2, B14, B16, B20, C1, C3, C7.

**Priority order for the driving concerns:** R2 → R1 → R3 → R5 → R4 → R11 → R6/R7 → R8 → R9–R17 opportunistically.

---
## Software-Architecture Recommendations

> Verbatim output from `software-architect` (A#). Core diagnosis: **`Log` is a god-object that conflates four responsibilities** — persistence, codex orchestration, derived-metric computation, and presentation wiring. Almost every performance and correctness finding traces back to that conflation, because there is no seam at which to insert caching, lazy parsing, or an error boundary.

**A1: Introduce a lazy `ParsedLog` seam so parsing happens once, on demand** *(B4, B6, S6, S9, S16, C8, R3 High; OCE-004, DE-002, DE-007 — SRP/DIP)*
`setContent()` should store the raw string only; parsing happens on first `getCodexLog()` and is memoized:
```php
public function setContent(string $content): static {
    $this->rawContent = $content; $this->log = null; return $this;
}
public function getCodexLog(): ?LogInterface {
    if ($this->log === null && $this->rawContent !== null) {
        $this->log = $this->parsePipeline($this->rawContent);
    }
    return $this->log;
}
```
One seam resolves the whole redundant-parse cluster: raw endpoint never parses, save() doesn't re-parse, metrics parse at most once. **Keystone — prerequisite for A2/A3/A9.**

**A2: Wire the existing dead `CacheEntry` for render/analysis output, with invalidation in `Log::delete()`** *(B4, DE-002, DE-009, R3 — DIP)*
Key by `id + content-hash` (plus codex-pz version per SA7); read-through in `getPrinter()->print()` and `getAnalysis()`; **do not ship before `delete()` invalidation is wired** or deleted logs leak cached PII. Add `CacheEntry::delete()`.

**A3: Make the raw endpoint and metadata reads bypass parsing entirely** *(OCE-004, B11, DE-004, DE-011 — ISP)*
Post-A1: `getContent()` returns `$this->rawContent` directly; `hasLog()` uses `findLog(includeContent:false)`; `DeleteLogAction` fetches without content.

**A4: try/finally exception boundary + resource guard at the worker request seam** *(OCE-003, OCE-006, B3, B8, B9, B10, R4, R6 — SoC at the composition root)*
```php
frankenphp_handle_request(function () {
    set_time_limit($parseBudgetSeconds);
    try { MongoDBClient::getInstance()->reset(); URL::clear(); route(); }
    catch (Throwable $e) { ErrorResponse::emit(500, ...); logStructured($e); }
    finally { /* the single documented place for cross-request reset */ }
});
```
Also the home of the structured per-request log line (OCE-009). Per-Action try/catch was considered and rejected (duplicates policy 13×, can't reset singletons).

**A5: Thread the stored `source` filename hint into detection** *(S8, R11 — wiring completeness)*
```php
$file = new HintedStringLogFile($data, $this->getSource());
$this->log = new Detective()->setLogFile($file)->detect();
```
Caveat: `StringLogFile` has no path setter and `PathLogFile` requires a real file — the additive codex-pz class is deferred to system altitude (SA3); the iblogs-side threading lands once it exists.

**A6: Delete the dead IP filters; close the /1/analyse filter bypass** *(S4, S15, DE-008, B17, R8 — cohesion: one owner for PII redaction)*
Delete `IPv4Filter`/`IPv6Filter`; extract a shared content-intake step so `AnalyseLogAction` runs the same `Filter::filterAll` chain as `CreateLogAction`.

**A7: `random_int` + insert-and-catch; drop the `hasLog` pre-check** *(B10, C5, C9, DE-003, R7)*
```php
for ($attempt = 0; $attempt < 5; $attempt++) {
    $this->id ??= new Id();   // random_int inside
    try { insertOne(['_id' => $this->id->get(), ...]); break; }
    catch (DuplicateKeyException) { $this->id = null; }
}
```
The unique `_id` constraint is the source of truth; the pre-check is redundant and racy.

**A8: Stop `LogResponse::jsonSerialize()` mutating the shared codex log** *(S10, S13, B20 — CQS)*
Build the entries-excluded view without calling the setter. Becomes a real bug the moment A1/A2 share parsed instances — sequencing matters. The clean interface fix is deferred to SA4.

**A9: Null-guard analysis consumers now; persist derived counts at write time next** *(S5/B5/C10/R1 High; DE-007, DE-001)*
(a) Immediate: `$this->getAnalysis()?->getProblems() ?? []` in `getPageDescription()` and `log.php:66`. (b) Medium: store `size`/`lineCount`/`errorCount`/`problemCount` in the Mongo doc at write time so reads need no parse.

**Sequencing roadmap:** Quick wins — A9a, A4, A6, A7, A8 (iblogs side). Medium — A1, then A3 and A9b. Structural — A2 (only after A1 + delete-invalidation), A5 (after SA3).
**Intentionally not addressed:** S3 (register 7DTD only when real), S12 (dormant by design), S7, B16 (revisit after A1/A2 remove dominant costs), B19/OCE-007/010/013/DE-005/DE-012 (low-priority hardening). **Deferred YAGNI:** router registration freeze (C1), generic ContentPipeline abstraction, pagination/virtualization render API (reopen if cached renders still exceed budget — pairs with UX-005).
**Deferred to system altitude:** S1 (composer seam), S2/S13 (LogInterface contract), S17/OCE-005 (streaming contract), OCE-002 (regex fix belongs in codex-pz), the `HintedStringLogFile` addition.

---

## System-Architecture Recommendations

> Verbatim output from `system-architect` (SA#). The seam is a package/bounded-context boundary inside one deployable, joined by a Composer path repository. Verified: `PathLogFile` requires `file_exists` so it cannot carry a hint for Mongo-resident content; `setIncludeEntries` is on concrete `Log` only; the committed `vendor/` snapshot is the only reason the app runs today.

**SA1: Fix the path-repo URL; make clean-install the enforced contract** *(S1, R2, S14)*
```json
"repositories": [ { "type": "path", "url": "../codex-pz", "options": { "symlink": false } } ]
```
`symlink:false` is correct — it preserves the install-copy boundary so the `^0.6.0` constraint still arbitrates rather than collapsing into a live symlink that hides version skew. Regenerate the lock (don't hand-edit). Reconcile all CLAUDE.md files to the single live constraint. Keep `vendor/` committed only behind SA2's CI freshness check.

**SA2: Make the documented sync rule mechanical with a CI seam gate** *(S3, R14, S14, S12)*
No CI exists today. Add a pipeline that: (1) `composer install --no-cache` in iblogs/ against `../codex-pz` (guards SA1); (2) `composer test` in codex-pz/; (3) a seam-contract check asserting the installed codex-pz version satisfies the iblogs constraint AND every concrete `*Detective` in codex-pz is either registered in `iblogs/src/Detective.php` or on an explicit allow-list (SevenDaysToDie = "stub, intentionally unregistered until its analyser ships"). Converts the gentleman's-agreement sync rule into an enforced customer-supplier contract — the docs have already demonstrably drifted (S14).

**SA3: Thread the `source` hint across the seam via a path-bearing, content-injected LogFile** *(S8, B13, R11)*
```php
// codex-pz, additive:
class HintedStringLogFile extends LogFile {
    public function __construct(string $content, ?string $pathHint = null) {
        $this->content = $content; $this->path = $pathHint; // no file_exists, no disk read
    }
}
```
Non-breaking supplier addition; a wrong/spoofed `source` only mis-weights detection (content detectors still vote); a missing hint degrades to today's behavior.

**SA4: Widen `LogInterface` to publish what iblogs actually calls** *(S2, S13, R16, R12 — Open Host Service)*
Promote `setIncludeEntries(bool): static` onto the interface (additive — the concrete class already implements it); fix the `getLogFile`/`getLogfile` casing. iblogs then types against `LogInterface` only.

**SA5: Stage streaming as expand-and-contract on codex-pz** *(S17, R5, B15)*
```php
interface LogFileInterface { getContent(): string; streamContent(): iterable; /* default yields getContent() */ }
interface PrinterInterface { print(): string; printTo(callable $sink): void; /* default sinks print() */ }
```
Expand additively with default implementations (no flag-day), migrate iblogs's large-log render to `printTo($echoChunk)`, deprecate the string methods only after the consumer moves — coordinated under SA2's gate. Interim load-shedding: the existing LimitBytes/LimitLines guard at the upload edge.

**SA6: Fix the redaction regexes supplier-side; consume via version bump** *(OCE-002, R4, B3, B16)*
Anchor/atomic-group the patterns, check `preg_last_error()` after every pass, **fail closed** (reject the upload) rather than storing partial output; replace `addInsight`'s O(N²) scan with hash-keyed dedup. Do NOT hot-patch in iblogs — that forks the redaction model and the next codex-pz bump silently reintroduces the vuln.

**SA7: Version-and-content-hash the cached Analysis projection** *(DE-002, R3, S11/B20)*
```
cacheKey = hash(content) + ":" + installedCodexPzVersion + ":" + analyserRev
```
The serialized Analysis is NOT a stable cross-version contract; the version component makes a codex-pz bump degrade to a cold-cache recompute (slow, correct) instead of corrupt output. If a singleton Detective is ever cached cross-request (S16), it must obey the worker-reset protocol — a precondition, not a green light.

### Context Map

```
codex-pz ── Customer-Supplier (Composer path repo) ──▶ iblogs       (URL broken → SA1: "../codex-pz")
codex-pz ── sync rule = documentation only ──▶ iblogs                (→ mechanical CI gate, SA2)
codex-pz LogInterface ── too-narrow Published Language ──▶ iblogs    (→ Open Host Service, SA4)
codex-pz FilenameDetector ── path-hint contract severed ──▶ iblogs   (→ HintedStringLogFile, SA3)
codex-pz LogFile/Printer ── string-only contracts ──▶ iblogs         (→ expand-and-contract streaming, SA5)
codex-pz ProjectZomboidRedactor ── owns redaction model ──▶ iblogs   (fix supplier-side, bump, SA6)
iblogs cache collection ── keys on Analysis shape ──▶ codex-pz       (→ version-scoped key, SA7)
aternos/codex ── Conformist (upstream, read-only) ──▶ codex-pz       (sound, no change)
```

**Deferred (YAGNI):** collapsing the seam (merging codex-pz into iblogs / symlinked workspace) — codex-pz is a healthy, separately-tested, reusable context; SA1+SA2 fix the seam without dissolving it. Symlink path repo — collapses version arbitration. Multi-node Mongo — no finding shows storage scale as the bottleneck; the problem is recompute, not the store.
**Coordinate with:** devops-engineer (stand up the CI pipeline; decide vendor/ policy), data-engineer (cache collection schema-ownership; DE-001 gzip interaction with cache keys).

---

*End of report. Finding IDs (`S#`, `B#`, `C#`, `DE-###`, `OCE-###`, `UX-###`, `R#`, `A#`, `SA#`) are stable for the life of this report — cite them in tickets, ADRs, and follow-up work.*
