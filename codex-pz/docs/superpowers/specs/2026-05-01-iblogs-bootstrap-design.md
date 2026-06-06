# iblogs bootstrap design

> Written 2026-05-01.
> **Scope:** design only. No iblogs code is written by this document; the actual fork, rename, and rewire happen in a follow-up session after this design is approved.

## Summary

iblogs is a Project-Zomboid-first log triage service forked from `aternosorg/mclogs`. It consumes `indifferentketchup/codex` (pinned at `v0.1.0`) for log detection, parsing, and analysis, replacing mclogs's `aternos/codex-minecraft` / `aternos/codex-hytale` / `aternos/sherlock` dependency stack. The data model gains a session entity that wraps the multiple files Project Zomboid produces per server session (eleven file types per session), while mclogs's existing single-paste paths remain alive as legacy routes that map to "session of size 1."

## (a) Fork target verification

| Check | Value |
|---|---|
| Upstream | `github.com/aternosorg/mclogs` |
| Default branch | `main` |
| License | **MIT** (SPDX `MIT`) — compatible with `indifferentketchup/codex`'s MIT |
| Last push | `2026-03-30` (active; ~30 days ago) |
| Last update | `2026-04-26` |
| Archived | no |
| Stars / open issues | 290 / 2 |
| PHP requirement | `>=8.5`, plus `ext-frankenphp`, `ext-mongodb`, `ext-uri`, `ext-zlib`, `ext-mbstring`, `ext-json` |
| Storage | MongoDB |
| Existing codex dep | yes — `aternos/codex-minecraft ^5.0.1` and `aternos/codex-hytale ^2.0` |

**Verdict: GO.** License is compatible. Project is actively maintained. No archival or licensing blockers. The fact that mclogs already integrates Aternos's codex stack tells us the fork's swap surface is well-defined: replace those Composer deps and the codex-facing call sites in `src/Api/Action/AnalyseLogAction.php` / `src/Api/Action/LogInsightsAction.php` / `src/Api/Response/CodexLogResponse.php` / `src/Detective.php` / `src/Log.php`.

The PHP `>=8.5` floor is stricter than codex's `>=8.4` — iblogs inherits the stricter constraint, which is fine. The `ext-frankenphp` requirement means iblogs runs on the FrankenPHP runtime rather than vanilla PHP-FPM; preserving this is the path of least resistance.

`aternos/sherlock` (MIT, "PHP library to apply minecraft mappings to log files") is Minecraft-specific (Mojang obfuscation maps). It is **not needed for PZ** and gets dropped. If iblogs ever adds Minecraft support, it can come back.

## (b) Repo plan

**Primary remote:** Gitea at `git.indifferentketchup.com:2222`. Fork as `indifferentketchup/iblogs`. SSH clone URL: `ssh://git@git.indifferentketchup.com:2222/indifferentketchup/iblogs.git`. Match the codex repo's existing Gitea setup.

**GitHub mirror:** Push-only secondary, configured via Gitea's Mirror feature (Repo Settings → Mirror Settings → Push Mirror). Same pattern any team using Gitea-as-primary uses for visibility.

**Composer dep on codex.** iblogs's `composer.json` gains a `repositories` entry of type `vcs` pointing at the codex Gitea URL (`ssh://git@git.indifferentketchup.com:2222/indifferentketchup/ik-codex.git`), and a `require` entry for `indifferentketchup/codex` pinned to exactly `0.1.0`. The exact pin is preferred over `^0.1.0` for early-version (0.x) releases where minor bumps may carry breaking changes.

**Removed deps:** `aternos/codex-minecraft`, `aternos/codex-hytale`, `aternos/sherlock`. The first two are replaced by `indifferentketchup/codex` (which covers Project Zomboid and ships detective stubs for Minecraft / Hytale / SevenDaysToDie that iblogs will not use in v0.1). The third (Sherlock) is Minecraft-mapping-specific and not relevant to PZ.

**Package name.** `aternos/mclogs` becomes `indifferentketchup/iblogs`. Composer name and the PSR-4 namespace move together: `Aternos\Mclogs\` → `IndifferentKetchup\Iblogs\`.

## (c) Multi-file / session paste model

Project Zomboid produces eleven log files per server session. The data model needs to accommodate this without breaking mclogs's existing single-paste consumers.

### Option (i) — 1 file = 1 paste, sibling-link via shared `session_id`

- **Pros:** minimal schema change. Reuse mclogs's existing `Log` per file. Sibling discovery is a `session_id` index.
- **Cons:** no atomic ingest (zip becomes N independent uploads). Session views require runtime joins. `session_id` propagation through upload UX is fiddly (URL param? cookie? hidden form field?).
- **Effort:** low.

### Option (ii) — zip upload explodes server-side into N linked pastes

- **Pros:** atomic ingest. One endpoint for whole-session upload. Maps cleanly to PZ's natural zip-of-logs deliverable.
- **Cons:** zip-only ingest is restrictive (no single-file paste UX for users with just `DebugLog-server.txt`). Server-side zip extraction is attack surface (zip bombs, path traversal). Doubles upload paths if single-file is also supported.
- **Effort:** medium.

### Option (iii) — session entity wraps N file entities (1:N relation)

- **Pros:** rich session model. Single URL for the whole session; child URLs per file. PZ's eleven-file natural session maps cleanly. mclogs's single-paste maps to "session of size 1," so the model degenerates gracefully into legacy behaviour. Session-level metadata (server name, date range, total size) becomes first-class.
- **Cons:** most schema migration. Two URL types in routing. More concepts in the API.
- **Effort:** medium-high.

### Recommendation: option (iii)

PZ's natural unit IS a session — the server emits all eleven files per restart, ZIP-bundled in production. Single-file uploads (the mclogs default UX) become "session of size 1" with no special-case code; the legacy `/api/1/log` routes return a paste that happens to belong to a singleton session. Cross-file analysis (e.g. correlating a `ServerExceptionProblem` from `DebugLog-server.txt` with a `ConnectionFailureProblem` from `user.txt`) is unlocked because both files share a `session_id`. The 1:N model is the only one that supports cross-file analysers in any future Phase B.4-equivalent on iblogs's side.

## (d) UI changes

**Primary nav: file-type tabs.** Within a session, eleven tabs (one per PZ file type) with a count badge (e.g. `DebugLog (6,998 lines)`, `chat (115)`). Clicking a tab loads that file's content + analysis. Tab order: DebugLog-server first (most useful for triage), then admin, user, chat, item, map, perk, pvp, ClientActionLog, cmd, BurdJournals.

**Secondary nav: session index sidebar.** Lists the user's recent sessions (cookie-driven, like mclogs's history). Less primary than tabs.

**Default view.** `/session/{id}` lands on the DebugLog-server tab by default — that file is what admins want to see when something is broken.

**Redaction toggle.** Per-session checkbox in the toolbar: "Redact PII". Behaviour depends on Step 4 (codex Redactor) status:
- If Redactor ships first: toggle invokes `ProjectZomboidRedactor::redact()` on the rendered file content client-side or server-side (decision for the implementation pass).
- If Redactor is still deferred: toggle is hidden in v0.1 of iblogs. Upload-time PII filtering still happens via the ported `Filter` chain (see `src/Filter/*` upstream — `IPv4Filter`, `IPv6Filter`, `AccessTokenFilter`, `UsernameFilter`).

**Branding.** Drop the "Built for Minecraft & Hytale" tagline and visual cues. Replace `mclo.gs` brand references with whatever short-domain iblogs uses (open question — see (h)). Color palette decision is open; mclogs's green accent (`#5cb85c` in `example.config.json`) is fine to keep or change.

## (e) API surface

Iblogs exposes a session-oriented API on top of the recommended (iii) model, plus the legacy mclogs paths kept alive.

| Path | Method | Purpose |
|---|---|---|
| `/api/session` | POST | Create a session by uploading one zip OR multiple file fields. Returns `session_id` plus a list of `{type, paste_id}` for each contained file. |
| `/api/session/{id}` | GET | Return session metadata + array of contained pastes (`{type, paste_id, line_count, size_bytes}`). |
| `/api/session/{id}/file/{type}` | GET | Return one file's content and its codex analysis result. `{type}` is one of the eleven PZ file-type tokens (`server`, `chat`, `clientaction`, `cmd`, `item`, `map`, `perk`, `pvp`, `admin`, `user`, `burdjournals`). |
| `/api/paste/{id}` | GET | Single-paste back-compat. Returns content + analysis for any paste (whether part of a multi-file session or a singleton). |
| `/api/1/log` | POST | Legacy mclogs path — kept alive. Internally creates a singleton session under the hood and returns the existing-shape mclogs response. |
| `/api/1/log/{id}` | GET | Legacy mclogs path — kept alive. Same as `/api/paste/{id}` with the legacy response shape. |

The legacy paths preserve mclogs's API contract for any third-party clients that already integrate with `mclo.gs` or self-hosted mclogs instances. Upgrading clients to the session-aware API is opt-in.

## (f) String / branding inventory

Producing exact `path:line` references requires the cloned working copy of the fork. This section gives directional pointers from the fetched-but-not-cloned upstream tree at `aternosorg/mclogs:main`. The actual line-precise inventory belongs in a follow-up commit on the iblogs side, after the fork exists and can be `grep`ped.

**Composer / package metadata** — file `composer.json` upstream (no local clone, line refs not yet known):
- `"name": "aternos/mclogs"` → `"indifferentketchup/iblogs"`
- `"description": "Paste, share and analyse Minecraft logs"` → describe iblogs scope (PZ-first, server-log triage)
- `"authors"` block (currently `Matthias Neid <matthias@aternos.org>`) → replace with `indifferentketchup` author
- `require` block:
  - drop `aternos/codex-minecraft`
  - drop `aternos/codex-hytale`
  - drop `aternos/sherlock`
  - add `indifferentketchup/codex` pinned to `0.1.0`
- `autoload.psr-4` mapping `"Aternos\\Mclogs\\": "src/"` → `"IndifferentKetchup\\Iblogs\\": "src/"`
- new top-level `repositories` array entry of type `vcs` pointing at the codex Gitea URL

**Namespace bulk substitution** — every PHP file under `src/` (which is roughly 50+ files based on the upstream tree). The pattern mirrors the codex rename in commit `66a2fcc`: bulk `Aternos\Mclogs` → `IndifferentKetchup\Iblogs` across `namespace`, `use`, fully-qualified refs, and PHPDoc tags. Done as one logical commit on the iblogs side per the codex-side precedent.

**Codex API call sites** — the files mclogs uses to integrate Aternos's codex stack, all under `src/`:
- `src/Detective.php` — likely a wrapper around `aternos/codex-minecraft`'s Detective. Swap to `IndifferentKetchup\Codex\Detective\ProjectZomboid\ProjectZomboidDetective` (or wrap multiple game detectives if iblogs ever supports more games).
- `src/Log.php` — likely a wrapper. Re-point to codex's `Log` hierarchy.
- `src/Api/Action/AnalyseLogAction.php` — the `analyse` endpoint. Update to call codex's `AnalysableLog::analyse()` with the new analyser surface.
- `src/Api/Action/LogInsightsAction.php` — insights endpoint.
- `src/Api/Response/CodexLogResponse.php` — response shape; verify field-by-field against `IndifferentKetchup\Codex\Analysis\AnalysisInterface::jsonSerialize()`.
- `src/Api/Action/CreateLogAction.php` — log creation; integration with codex's `Detective::detect()`.
- `src/Api/Action/RawLogAction.php`, `src/Api/Action/LogInfoAction.php` — verify these don't depend on Minecraft-specific codex behaviour.

**Frontend templates and assets** — file paths only, exact branding strings discovered post-clone:
- `web/frontend/start.php` — landing page; "Paste, share and analyse Minecraft logs" hero copy lives here.
- `web/frontend/api-docs.php` — API documentation page.
- `web/frontend/parts/header.php`, `parts/footer.php`, `parts/head.php` — site title, meta tags, footer links to legal info.
- `web/frontend/log.php` — log view template (probably hardcodes the syntax-highlighting language token — needs to handle multiple PZ file types).
- `web/frontend/404.php` — error page copy.
- `web/public/css/mclogs.css` — file is **renamed** to `iblogs.css` and CSS class names referencing `mclogs` are renamed.
- `web/public/js/start.js`, `web/public/js/log.js` — likely contain text constants and reference `mclogs.css` filename.
- `web/public/img/logo-icon.svg`, `logo.svg`, `favicon.ico` — replaced with iblogs assets.

**Configuration** — file `example.config.json`:
- database name `mclogs` → `iblogs`
- abuse contact `abuse@aternos.org` → iblogs contact (open question — see (h))
- imprint and privacy policy links currently point at `aternos.gmbh` → iblogs equivalents
- `mclo.gs` brand reference in the frontend styling section → new iblogs short-domain (open question)
- worker request limit, ID length, TTL — review for iblogs-appropriate values; PZ sessions are larger than mclogs single pastes so size and line limits may need raising.

**Docker / deployment** — files `Dockerfile`, `docker/Caddyfile`, `docker/compose.production.yaml`, `docker/mclogs.ini`:
- Image label maintainer references
- Caddyfile likely hardcodes `mclo.gs` hostname for TLS certificates → replace with iblogs hostname
- Compose service name `mclogs` → `iblogs`
- File `docker/mclogs.ini` is renamed and its contents updated

**`LICENSE` file** — per MIT requirements, the original Aternos copyright line stays byte-for-byte unchanged. iblogs's LICENSE preserves the upstream copyright header. This mirrors codex's handling of its own upstream LICENSE.

**`README.md`** — full rewrite. Title, description, install line, links to upstream codex repo, scope statement (PZ-first, server-log triage). Drop Minecraft / Hytale framing entirely.

**Filter classes for PZ-specific PII** — upstream's filter chain (`src/Filter/IPv4Filter.php`, `IPv6Filter.php`, `AccessTokenFilter.php`, `UsernameFilter.php`) handles Minecraft-style PII (server access tokens, Minecraft-pattern usernames). For PZ, iblogs may need new filters: `SteamIdFilter`, `WorldCoordinateFilter`, and a PZ-aware username filter (Steam usernames look different from Minecraft ones). These are net-new code, not branding renames.

## (g) Migration

**Keep mclogs's existing single-paste API routes alive as legacy.** Two reasons:
1. mclogs has live API consumers calling `POST /api/1/log` and `GET /api/1/log/{id}` against `mclo.gs` and self-hosted instances. Iblogs's primary value is PZ support, not breaking compat with the broader mclogs ecosystem.
2. Under model option (iii), legacy single pastes are naturally "sessions of size 1." Zero extra schema work to support legacy routes — they just internally create singleton sessions.

**Strip:** `aternos/codex-minecraft`, `aternos/codex-hytale`, `aternos/sherlock` Composer deps; the `Aternos\Mclogs\` namespace; mclogs-specific branding strings; the `mclo.gs` hostname hardcodes; Minecraft-mapping deobfuscation code paths.

**Preserve:** the upstream `Filter` chain (it solves real problems — IP redaction, access tokens, usernames); the FrankenPHP runtime; MongoDB storage layer; the cookie-based session-history UX; the Caddy fronting.

## (h) Open questions

1. **`aternos/sherlock` license confirmation** — verified MIT (this design doc fetched the metadata) but iblogs is dropping it. No issue.
2. **`ext-frankenphp` keep / replace decision** — recommend keep for v0.1 (path of least resistance). Migrating to vanilla nginx+php-fpm is its own project and can come later.
3. **Branding decisions:**
   - Site name: `iblogs` (lowercase) seems chosen given the project mention `indifferentketchup/iblogs`. Confirm.
   - Tagline: needs writing. "Project Zomboid server log triage" is honest; longer-form copy is open.
   - Short-domain: mclogs uses `mclo.gs`. Is there an iblogs equivalent (`iblo.gs`? `ib.gs`?)? Affects Caddyfile, frontend assets, and docs links.
   - Accent / palette: keep mclogs green (`#5cb85c`) or pick a different colour?
4. **Database choice:** keep MongoDB or migrate to PostgreSQL / SQLite? Migrating away from Mongo is a significant project; recommend keep for v0.1.
5. **API URL versioning:** mclogs uses `/api/1/`. Stay with `/api/1/` for legacy paths (compat) and add `/api/session/...` for new endpoints (no version prefix), or use `/api/v2/session/...`? Recommend the former — minimum surface change.
6. **Session-ID generation:** mclogs uses 7-character IDs. For iblogs sessions of N files, pick (a) one session-ID + N independent paste-IDs (richer URLs) or (b) single ID per paste with a sibling `session_id` field (simpler). Affects URL shape.
7. **The codex Redactor utility.** Iblogs's redaction toggle (section d) depends on whether Step 4 (Redactor implementation) ships before or after iblogs scaffolding. **Decision deferred to user (Step 4 of the careful run).**
8. **PZ-specific filter classes** (`SteamIdFilter`, `WorldCoordinateFilter`, etc.) — net-new work for iblogs. Could lift the regex shapes from `docs/superpowers/specs/2026-04-30-redactor-design.md` (they're the same PII categories). Implementation order: iblogs likely wants these for its upload-time filter chain regardless of whether the codex `Redactor` ships.
9. **Multi-game support trajectory.** v0.1 of iblogs is PZ-first. If Minecraft / Hytale / SevenDaysToDie support is on the roadmap, iblogs's Detective wiring needs to be a multi-game dispatcher (not just `ProjectZomboidDetective`). Codex provides the per-game detectives separately; iblogs would compose them. Out of scope for v0.1.
10. **The exact line-precise branding inventory** (every file:line ref of `Minecraft` / `Hytale` / `MC` / `mc` / `mclogs` / `mclo.gs` / `Aternos`). This document gives file-level pointers; the line-precise version is produced as a separate work item once the fork is cloned and grep-able.

## Pointers

- Codex package consumed: `indifferentketchup/codex` v0.1.0, tag SHA `8a89550` (annotated tag) pointing at commit `52ff8cb`.
- Codex Redactor design (deferred): `docs/superpowers/specs/2026-04-30-redactor-design.md`.
- Codex CHANGELOG: `CHANGELOG.md` in this repo.
- Upstream mclogs: `https://github.com/aternosorg/mclogs` (MIT, `main` default branch, last push 2026-03-30).
