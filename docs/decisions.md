# Decisions

Locked decisions for the Bidayaat monorepo. Amendments are appended, never silently edited.

## F0 baseline (from spec)

| # | Decision |
|---|---|
| D1 | Monorepo: `backend/` (Laravel, API only) and `frontend/` (Vue SPA) |
| D2 | ~~Laravel 11+, PHP 8.3, PostgreSQL 16 (Debian image), Redis 7~~ — **amended, see A1** |
| D3 | ~~Local dev with Laravel Sail in `backend/`~~ — **amended, see A1** |
| D4 | Auth: Sanctum SPA cookie auth (stateful), not tokens |
| D5 | Vite proxies `/api` and `/sanctum` to the backend in dev (same-origin) |
| D6 | TypeScript strict mode on the frontend |
| D7 | Locale in the URL: `/ar/...`, `/en/...`. `/` redirects to stored/default locale (`ar`) |
| D8 | API always returns both languages as `{ar, en}`; the frontend picks |
| D9 | Bilingual columns are explicit `_ar`/`_en`, not a translations table |
| D10 | Numerals: Western digits (0-9) in both languages |
| D11 | Fonts: IBM Plex Sans Arabic + IBM Plex Sans, self-hosted via `@fontsource` |
| D12 | Tailwind CSS v4, logical properties only (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`, `text-start`). Forbidden: `ml-`, `mr-`, `pl-`, `pr-`, `left-`, `right-`, `text-left`, `text-right` |
| D13 | Packages installed only when a feature needs them |
| D14 | Every create/update/delete/restore on any tracked model is recorded as an immutable, field-level change record (who, when, model, field, old, new), visible in an admin changelog view. Wired once in F0; every later feature must use it |

**OPEN:** SEO for the public SPA (prerendering vs Inertia SSR vs Nuxt) — decide before F9. F0/F1 keep history-mode routes and stable slugs.

## Amendments

### A1 (F0, 2026-09-19) — Database and dev environment

- **Database: MySQL 8 (via Herd), not PostgreSQL.** Reason: user directive ("use herd and mysql"); Herd already runs MySQL on 127.0.0.1:3306 and Redis on 127.0.0.1:6379, Docker daemon is not running.
- **No Sail / Docker.** Dev services are managed by Herd. `make up` only verifies that MySQL/Redis are listening.
- **PHP 8.4** (Herd's version; satisfies the "PHP 8.3+" floor).
- **CI uses a MySQL 8 service container** instead of Postgres.
- Consequences: F1's `pg_trgm`/GIN trigram search must be re-planned for MySQL (likely a generated fulltext/normalized-column approach) — decision deferred to F1.
