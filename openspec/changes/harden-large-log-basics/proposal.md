## Why

The 2026-06-11 architecture analysis found several high-risk issues that are still present in the current tree: clean Composer installs point at a stale codex-pz path, unknown logs can 500 on view, `/1/analyse` bypasses upload limits, worker requests lack an exception boundary, share IDs use `rand()`, and core log-page controls are not real buttons.

This change plans the first small hardening batch before the larger lazy parsing and caching work. It targets cheap correctness and operability fixes that reduce failure modes without changing the log parsing architecture.

## What Changes

- Update the iblogs Composer path repository and lock metadata to resolve `indifferentketchup/codex-pz` from the sibling `../codex-pz` package.
- Null-guard analysis consumers so undetectable logs still render instead of crashing.
- Add a worker-level request boundary so thrown exceptions return an API or frontend error response and per-request reset runs in a documented place.
- Run `/1/analyse` content through the same filter pipeline used for stored uploads.
- Generate public log IDs with `random_int()` instead of `rand()`.
- Convert the log-page scroll/error controls from clickable `div`s to `button`s, wire the error control to jump to the first visible error, and remove mobile pinch-zoom blocking.

## Capabilities

### New Capabilities

- `large-log-basic-hardening`: Covers the first incremental hardening batch for build integrity, unknown-log rendering, bounded analysis intake, request failure isolation, ID entropy, and log-page control accessibility.

### Modified Capabilities

None. There are no existing OpenSpec specs in `openspec/specs/`.

## Impact

- Affected iblogs files:
  - `iblogs/composer.json`
  - `iblogs/composer.lock`
  - `iblogs/src/Log.php`
  - `iblogs/web/frontend/log.php`
  - `iblogs/web/frontend/parts/head.php`
  - `iblogs/web/public/js/log.js`
  - `iblogs/src/Api/Action/AnalyseLogAction.php`
  - `iblogs/src/Id.php`
  - `iblogs/worker.php`
- The change may require Docker-based Composer because host PHP and Composer are intentionally absent per `iblogs/CLAUDE.md`.
- No database migration, new dependency, or public API version change is planned.

