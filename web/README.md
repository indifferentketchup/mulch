# mulch web

The mulch web application: a [Next.js 16](https://nextjs.org) (React 19) frontend
and API that stores logs in MongoDB and delegates parsing/analysis to the PHP
analyzer microservice in [`analyze/`](analyze/).

For the project overview and architecture, see the [repository README](../README.md).

## Layout

```
src/app/        routes: paste UI (page.tsx), log viewer ([id]), REST API (api/*)
src/components/ React components (layout, log viewer, problem panels)
src/lib/        domain logic: log-service, mongodb, filters, severity, rate-limit
analyze/        PHP analysis microservice (its own Dockerfile + bundled codex-pz)
compose.yaml    full stack: web + analyzer + mongo
```

Upload-time PII filters (`src/lib/filters.ts`) strip IP addresses, usernames, and
access tokens before a log is persisted. `src/lib/log-service.ts` validates and
runs that pipeline, so the route handlers stay thin.

## Running the stack (Docker)

The compose file brings up the web app, the analyzer, and MongoDB:

```bash
docker compose up          # http://localhost:4217
docker compose down -v     # stop and drop the mongo volume
```

| Service | Port | Purpose |
|---------|------|---------|
| web | `4217` → 3000 | Next.js app and API |
| analyzer | `4219` → 8080 | PHP service wrapping codex-pz |
| mongo | internal | log + metadata storage |

## Developing the frontend directly

The Next.js app alone (you'll still need a MongoDB and the analyzer reachable via
the env vars below):

```bash
npm install
npm run dev      # http://localhost:3000
npm run build
npm run lint
```

> Note: this repo pins a Next.js version with breaking changes from older
> releases. See [`AGENTS.md`](AGENTS.md) before editing app code.

## Configuration

All settings are environment variables (defaults in parentheses):

| Variable | Default | Purpose |
|----------|---------|---------|
| `IBLOGS_MONGODB_URL` | `mongodb://mongo:27017` | MongoDB connection string |
| `IBLOGS_MONGODB_DATABASE` | `iblogs` | Database name |
| `ANALYZER_URL` | `http://analyzer:8080` | Base URL of the PHP analyzer service |
| `IBLOGS_ID_LENGTH` | `7` | Length of generated short IDs |
| `IBLOGS_STORAGE_LIMIT_BYTES` | `52428800` (50 MB) | Max upload size |
| `IBLOGS_STORAGE_LIMIT_LINES` | `1000000` | Max upload line count |
| `IBLOGS_STORAGE_TTL` | `7776000` (90 days) | Log retention, in seconds |
| `IBLOGS_RATE_LIMIT_MAX` | `30` | Requests per window per client |
| `IBLOGS_RATE_LIMIT_WINDOW_MS` | `60000` | Rate-limit window, in ms |
| `IBLOGS_REDACT_DISABLED` | unset | Set to `1` to skip render-time redaction |
| `IBLOGS_LEGAL_ABUSE` | unset | Abuse/legal contact shown in the footer |

## API

The REST API lives under `src/app/api/`. The stable surface is `/api/v1/*`
(`log`, `insights`, `limits`); `/api/new` and `/api/[id]` are the internal
routes the UI uses. Interactive docs render at `/api`.
