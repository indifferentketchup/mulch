## Context

`ARCHITECTURAL-ANALYSIS-2026-06-11.md` identifies a larger performance problem around eager parse/analyse work and missing cache usage. The first implementation batch intentionally avoids that larger architecture change and focuses on high-impact fixes that are small enough to verify independently.

Current source evidence:

- `iblogs/composer.json:13` points the codex-pz path repository at `/opt/ik-codex`.
- `iblogs/composer.lock` records the same stale path in the codex-pz dist entry.
- `iblogs/src/Log.php:504` and `iblogs/web/frontend/log.php:66` call `getAnalysis()->...` without null guards.
- `iblogs/src/Api/Action/AnalyseLogAction.php:80` passes raw parsed content to `Log::setContent()`.
- `iblogs/worker.php:22` runs route dispatch without a `try/catch/finally`.
- `iblogs/src/Id.php:119` uses `rand()`.
- `iblogs/web/frontend/log.php:49`, `:54`, and `:217` render clickable `div` controls.
- `iblogs/web/frontend/parts/head.php:28` includes `maximum-scale=1`.

Host PHP and Composer are absent by design. `iblogs/CLAUDE.md:33` documents the Docker Composer command for one-off Composer work.

## Goals / Non-Goals

**Goals:**

- Make clean installs resolve the local sibling codex-pz package.
- Prevent non-analysable logs from crashing the log view.
- Apply the existing upload filter pipeline to `/1/analyse`.
- Add a worker boundary that logs thrown exceptions and returns 500 responses.
- Replace ID generation randomness with `random_int()`.
- Improve log-page controls without redesigning the page.

**Non-Goals:**

- Do not implement lazy parsing, rendered-output caching, cache invalidation, or streaming.
- Do not redesign codex-pz parser interfaces.
- Do not change MongoDB schema or retention policy.
- Do not solve all accessibility findings from the architecture report.
- Do not remove dead IP filters or rework supplier-side redaction regexes in this batch.

## Decisions

### Keep the first batch scoped to small hardening fixes

Implement only the quick fixes called out in the audit checkpoint. This reduces the chance of mixing behavior fixes with the larger parse/cache redesign. Alternative considered: include lazy parsing now. Rejected because it touches `Log` lifecycle, raw endpoint behavior, printer behavior, and future cache keying.

### Regenerate Composer lock with Docker Composer

Use the documented pattern from `iblogs/CLAUDE.md`:

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest update indifferentketchup/codex-pz
```

Run it from `iblogs/` after changing the path repository. If Composer blocks on application-only extensions that are not present in the generic Composer image, pass `--ignore-platform-req=ext-frankenphp --ignore-platform-req=ext-mongodb --ignore-platform-req=ext-uri`; do not ignore the PHP version because the image already provides PHP 8.5. Alternative considered: hand-edit `composer.lock`. Rejected because the architecture report explicitly calls for lock regeneration, and hand edits can leave hidden Composer metadata inconsistent.

### Null analysis means empty analysis projections

In `Log::getPageDescription()`, treat null analysis as an empty problem list. In `web/frontend/log.php`, treat null analysis as an empty information list. Alternative considered: force every codex log type to implement `AnalysableLogInterface`. Rejected because fallback base logs are a valid state and should render.

### Reuse the existing filter chain for `/1/analyse`

Call `Filter::filterAll($content)` in `AnalyseLogAction` before `setContent()`. This gives `/1/analyse` the same byte, line, and PII filtering as stored uploads. Alternative considered: add a new `BoundedLogInput` value object now. Rejected as a larger intake abstraction better paired with lazy parsing work.

### Use a worker-level exception boundary

Wrap URL routing in `try/catch(Throwable)` and keep per-request reset in `finally`. Use route type to choose JSON API error or a simple frontend 500 response, and `error_log()` for observability. Alternative considered: catch in every Action. Rejected because it duplicates policy and does not protect frontend routing.

### Keep log-page control styling while changing semantics

Change the three clickable controls to `<button type="button">` and keep existing classes so CSS remains stable. Add a small `log.js` handler for `#error-toggle` that scrolls to the first `.entry-error`. Alternative considered: redesign the controls and problems panel. Rejected as beyond this batch.

## Risks / Trade-offs

- [Risk] Docker Composer may need network access to pull `composer:latest` or resolve Packagist dependencies. -> Mitigation: use the documented repo workflow and stop if Composer cannot regenerate the lock.
- [Risk] Docker Composer lacks FrankenPHP, MongoDB, or URI extensions required by the app runtime. -> Mitigation: ignore only those extension platform requirements during lock regeneration.
- [Risk] Worker 500 response helpers may conflict with existing response abstractions. -> Mitigation: use minimal headers and plain JSON/HTML fallback in `worker.php`.
- [Risk] Filtering `/1/analyse` can change API output for clients that expected raw analysis. -> Mitigation: this is intentional safety behavior, matching upload semantics.
- [Risk] `random_int()` can throw `Random\RandomException`. -> Mitigation: allow it to propagate to the worker exception boundary rather than silently producing weak IDs.
- [Risk] Button default styles may affect layout. -> Mitigation: retain existing `btn` classes and set `type="button"`.

## Deferred (YAGNI)

- Lazy parsing and cross-request analysis/render cache. Reopen when this basic hardening batch is merged or when large-log view latency is being implemented directly.
- Supplier-side `ProjectZomboidRedactor` failure handling. Reopen as a separate codex-pz change because it needs redactor tests and may alter rejection semantics.
- Full keyboard fold-bar behavior and anchor auto-reveal. Reopen after the control semantics quick win or as a focused UX accessibility change.

## Implementation notes

- Task 1.2 failed with the planned Docker Composer command because it mounts only `iblogs/` into `/app`, so the path repository `../codex-pz` does not exist inside the container. The command must mount the monorepo root and run Composer from `/app/iblogs`, or use an equivalent container layout where `/app/codex-pz` exists next to `/app/iblogs`.
