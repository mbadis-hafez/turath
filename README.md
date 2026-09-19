# Bidayaat — بدايات

A bilingual (Arabic RTL-first / English) knowledge base and digital archive of Saudi art history.

Monorepo:

- `backend/` — Laravel (API only), Sanctum SPA cookie auth, PostgreSQL→**MySQL**, Redis, Pest.
- `frontend/` — Vue 3 + Vite + TypeScript SPA, Pinia, vue-i18n, Tailwind CSS v4 (logical properties only), Vitest.
- `docs/` — decisions, privacy rules, idea parking lot.

## Prerequisites

- PHP 8.4+ and Composer (Herd recommended)
- Node 22+
- MySQL and Redis running (managed via **Laravel Herd** — no Docker required)

Create the databases once:

```bash
mysql -h 127.0.0.1 -u root -e "CREATE DATABASE IF NOT EXISTS bidayaat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS bidayaat_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## Setup

```bash
make install   # composer + npm install, .env, app key
make up        # verifies MySQL + Redis are running (Herd manages them)
make migrate   # migrate:fresh --seed
make dev       # API on http://localhost:8000, SPA on http://localhost:5173
```

The Vite dev server proxies `/api` and `/sanctum` to `http://localhost:8000`, so the SPA
is same-origin in dev (session cookies work).

## Seeded accounts (local only)

| Email | Role | Password |
|---|---|---|
| admin@bidayaat.test | admin | password |
| editor@bidayaat.test | editor | password |

## URLs

- SPA: http://localhost:5173 (redirects to `/ar`, toggle to `/en`)
- API: http://localhost:8000/api/v1/health
- Change-log viewer: `/{locale}/admin/activity` (editor/admin only)

## Quality

```bash
make test    # Pest (backend) + Vitest (frontend)
make lint    # Pint, Larastan, ESLint, vue-tsc
```

CI runs both on every push/PR (`.github/workflows/ci.yml`).

## Feature roadmap

F0 foundation → F1 artists → F2 holders/artworks → F3 archive → F4 importers → F5 search →
F6 users/roles → F7 editing workflow → F8 media pipeline → F9 events/timeline → F10 trust → F11 openness.
See `docs/decisions.md` for locked decisions and deviations.
