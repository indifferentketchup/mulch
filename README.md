# mulch

**logs. broken down.**

Paste a game-server log and mulch breaks it down: detected problems, severity,
mod attribution, and stack traces. Built for server operators mid-incident.
Strip the PII, get a short URL to share, and go from a crash report to a
diagnosis you can act on.

Supports **Minecraft**, **Hytale**, and **Project Zomboid** logs.

## Repository layout

This is a monorepo. The product is the Next.js app in `web/`; everything else is
the analysis framework it builds on, plus read-only upstream references. The
subdirectories were merged in with their `.git` histories stripped. Original
remotes are recorded in [`ORIGINS.md`](ORIGINS.md).

| Directory | Role |
|-----------|------|
| `web/` | **The product.** Next.js 16 + React 19 frontend and API, MongoDB storage, and a PHP analysis microservice (`web/analyze/`) |
| `codex-pz/` | `indifferentketchup/codex-pz`, a PHP log parsing/analysis framework (fork of `aternos/codex`); most complete for Project Zomboid |
| `iblogs/` | Previous PHP (FrankenPHP) implementation, superseded by `web/`; kept for reference |
| `codex/`, `codex-minecraft/`, `codex-hytale/` | Upstream `aternos/*` codex framework and plugins (read-only reference) |
| `sherlock/` | `aternos/sherlock`, a Minecraft stack-trace deobfuscation library (read-only reference) |

## How it works

```
Browser
   │
   ▼
web/ (Next.js app) ──────────► MongoDB        store logs + metadata
   │   paste UI, log viewer,
   │   REST API, PII filters
   │
   └──────► web/analyze/ (PHP) ──► codex-pz
            POST /analyze, /redact, GET /health    parse + analyse
```

- **`web/`** serves the paste UI and the log viewer, exposes the REST API under
  `/api/v1/*`, persists logs to MongoDB, and proxies analysis to the analyzer
  service. Upload-time filters strip PII (IP addresses, usernames, access
  tokens) before anything is stored.
- **`web/analyze/`** is a small PHP service wrapping `codex-pz`: `POST /analyze`
  runs detection and analysis, `POST /redact` applies the render-time redactor,
  `GET /health` is a liveness probe.
- **`codex-pz/`** does the actual work. It runs the `Log → Parser → Analyser →
  Analysis` pipeline, detecting the log type and emitting structured
  `Information` and `Problem` insights with attached solutions.

## Running locally

Requires Docker. The stack (web app, analyzer, MongoDB) is defined in
[`web/compose.yaml`](web/compose.yaml):

```bash
cd web
docker compose up          # http://localhost:4217
docker compose down -v     # stop and drop the mongo volume
```

This brings up three services: the Next.js app (`4217`), the PHP analyzer
(`4219`), and MongoDB. See [`web/README.md`](web/README.md) for development
without Docker and environment variables.

## API

mulch exposes a stable v1 REST API. Interactive docs live at `/api`.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/v1/log` | Create a log; returns its `id`, share `url`, and raw URL |
| `GET` | `/api/v1/log/{id}` | Fetch a stored log |
| `DELETE` | `/api/v1/log/{id}` | Delete a log (owner only) |
| `GET` | `/api/v1/insights/{id}` | Analysis insights for a log |
| `GET` | `/api/v1/limits` | Current upload size and line limits |

## License

MIT. Each merged subdirectory keeps its own `LICENSE` file; the `aternos/*`
references retain the original Aternos GmbH copyright as required.
