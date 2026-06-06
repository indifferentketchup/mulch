# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`indifferentketchup/iblogs` — PHP web app for pasting, sharing, and analysing game-server logs. FrankenPHP runtime, MongoDB storage, MIT license. Forked from `aternosorg/mclogs`; namespace was renamed in-tree (`Aternos\Mclogs` → `IndifferentKetchup\Iblogs`) and all `mclogs` / `MCLOGS_*` identifiers were rebranded to `iblogs` / `IBLOGS_*`. Only the `LICENSE` retains the original Aternos GmbH copyright line, which must remain byte-for-byte (MIT requires it). The footer carries an upstream credit pointing at `aternosorg/mclogs` and the Aternos org.

Default branch is **`main`** (not `master` like ik-codex-pz). Production deployment lives at `bosslogs.indifferentketchup.com`.

## Sibling repo: ik-codex-pz

iblogs depends on `indifferentketchup/codex-pz` (sibling repo at `/opt/ik-codex-pz`, package source `https://git.indifferentketchup.com/indifferentketchup/ik-codex-pz`) via a Composer `vcs` repository entry. Current constraint in `composer.json`: `^0.5.0`.

**Cross-repo sync rule.** Changes that affect both repositories must be committed *and pushed* together. The most common shape: a public-API change in ik-codex-pz's `src/{Detective,Log,Printer,Util}/*.php` or `src/Analysis/*.php` is consumed at iblogs's `src/{Detective.php,Log.php,Printer/Printer.php,Printer/FormatModification.php,Api/Response/CodexLogResponse.php}` and the stub at `src/Data/Deobfuscator.php`. When working on such a change:

1. Make + commit the ik-codex-pz side first (cut a tag if it's a release).
2. Bump iblogs's `composer.json` codex constraint and adjust call sites.
3. Push both branches **in the same operation** — never leave iblogs requiring an ik-codex-pz version that isn't on the remote, and never tag ik-codex-pz with breaking changes without the matching iblogs adjustment ready to push.

If a change is purely internal to one repo (refactor inside ik-codex-pz with no public-API delta, or an iblogs-only feature like a new Filter), the rule doesn't apply.

## Local environment

PHP and Composer are **not** installed on the host. The dev environment runs entirely in Docker via `dev/compose.yaml` (FrankenPHP + MongoDB):

```
cd dev && docker compose up
```

Local web server binds to **port 4217** (compose.yaml maps `4217:80`). Open `http://localhost:4217`. The compose file mounts the repo into `/app` so source edits hot-reload via FrankenPHP's worker mode.

For one-off Composer commands (e.g. adding a dep, refreshing the lockfile), use the `composer:latest` Docker image the same way ik-codex-pz does:

```
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest <subcommand>
```

`git` and `ca-certificates` are installed in the production `Dockerfile` so Composer can resolve the ik-codex-pz VCS dependency at build time.

## Common commands

- Start dev stack: `cd dev && docker compose up` (see above)
- Stop and clean: `cd dev && docker compose down -v` (the `-v` drops the `mongo` volume)
- Refresh autoloader after editing `composer.json`: `docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest dump-autoload`
- Pull a new ik-codex-pz tag: bump constraint in `composer.json`, then `composer update indifferentketchup/codex-pz` via the same Docker invocation

There is no test suite in this repo currently (`composer test` is unwired). Smoke-test via the dev stack and exercise the routes manually.

## Architecture

```
HTTP request
    │
    ▼
worker.php  ──  Router::route()
    │              ├─ ApiRouter        (matches /api/* prefix)
    │              └─ FrontendRouter   (everything else)
    │
    ▼  Action dispatch (one Action class per route)
src/Api/Action/*Action.php           e.g. CreateLogAction, RawLogAction
src/Frontend/Action/*Action.php      e.g. StartAction, ViewLogAction
    │
    ▼  Storage / parsing
src/Storage/MongoDBClient.php        Mongo CRUD for Log documents
src/Log.php                          Log domain object (id, content, metadata, token)
    │
    ▼  Render / response
src/Frontend/Assets/AssetLoader.php  Compiles + caches CSS/JS assets at build time (build.php)
src/Printer/Printer.php              Wraps codex's printer with FormatModification
src/Api/Response/*Response.php       JSON shapes for the public API
```

- **Filters at upload time.** `src/Filter/*Filter.php` runs over raw log content before storage: `IPv4Filter`, `IPv6Filter`, `UsernameFilter`, `AccessTokenFilter`, `LimitBytesFilter`, `LimitLinesFilter`, `TrimFilter`. These are PII / safety filters distinct from ik-codex-pz's `RedactorInterface` (which is a render-time scrubber over already-stored content).
- **Detective bridge.** `src/Detective.php` extends `IndifferentKetchup\CodexPz\Detective\Detective` and registers per-game detectives from codex (`Minecraft`, `Hytale`, and `ProjectZomboid` — all three live since ik-codex-pz 0.4.0). The Detective is the entry point that maps a stored Log to a codex `Log` subclass for analysis.
- **Frontend rendering.** `web/frontend/*.php` are the per-route templates loaded by frontend actions. Shared parts under `web/frontend/parts/` (`head.php`, `header.php`, `footer.php`, `favicon.php`). Static assets in `web/public/`; `iblogs.css` is the main stylesheet.

## Configuration

- `IBLOGS_*` env vars (preferred for prod) or `config.json` at repo root (preferred for local).
- Defaults documented in `example.config.json` and the README env-var table.
- Mongo defaults to `mongodb://mongo` (compose service name) with database `iblogs`.
- Frontend colours, name, and legal URLs are all `IBLOGS_FRONTEND_*` / `IBLOGS_LEGAL_*`.

## External dependencies (do not rename)

The `aternos/*` references that remain in the codebase are **external libraries**, not project identity. Do not touch:

- `aternos/codex-*` and `aternos/sherlock` in `composer.lock` (transitive deps of ik-codex-pz's transitive deps, or stale lock entries — verify before touching)
- `Aternos\Codex\…`, `Aternos\Sherlock\…` namespace imports — these resolve to vendor classes, not iblogs code
- The `Aternos GmbH` copyright line in `LICENSE`
- The footer credit linking `github.com/aternosorg/mclogs` and `github.com/aternosorg`

If a future change drops one of these external deps entirely, the lockfile entry can go with it.

## Pitfalls

1. **PSR-4 namespace casing is `IndifferentKetchup` (capital K).** The autoloader prefix in `composer.json` is `IndifferentKetchup\\Iblogs\\`. Composer's PSR-4 lookup is case-sensitive — `Indifferentketchup` (lowercase k) anywhere in source will silently fail to autoload.
2. **Dev port is 4217, not 80.** `dev/compose.yaml` maps `4217:80`. Hardcoded `localhost:80` in scripts or local notes will hit nothing.
3. **`build.php` runs at Docker build time** to populate the asset cache (`AssetLoader::writeCache()`). Editing CSS/JS during a running container needs an explicit cache rebuild — restart the compose stack rebuilds the image and re-runs build.
4. **Codex-pz constraint is `^0.5.0`.** A v0.6.x cut on the ik-codex-pz side will require widening this constraint in `composer.json` and re-running `composer update`. See the Cross-repo sync rule above.

## Workflow conventions

- **Feature branches.** Substantive feature work lands on a `<feature>-bootstrap`-style branch off `main`, merged `--ff-only` (or `--no-ff` for explicit history) after review. The `iblogs-bootstrap` branch (now merged) set the precedent.
- **One commit per concern.** Conventional-commit prefixes: `chore:`, `refactor:`, `docs:`, `feat:`, `fix:`. Keep unrelated changes (e.g. a Docker tweak alongside a UI change) in separate commits.
- **Pre-destructive checkpoint pattern** (matches ik-codex-pz): before bulk renames or moves, `git commit --allow-empty -m "pre-X checkpoint"` as a revert anchor.
- **Don't push without confirmation.** The default after a commit or merge is to leave the branch ahead of origin until the user explicitly says to push.

## Privacy / data rules

- `Logs.zip` (when present at the repo root) holds real log data. Treat it as gitignored / out-of-scope; do not commit.
- Test fixtures, when added, must be **synthetic** — placeholder identifiers only, mirroring the ik-codex-pz convention (`76561198000000001/2/3` for Steam IDs, `Player1`/`Player2`/`AdminUser` for player names, generic coords).
