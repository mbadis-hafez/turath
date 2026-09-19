# Bidayaat Backend

Laravel 13 API-only backend (no Blade UI, no frontend build). All endpoints live
under `/api/v1` and speak JSON only.

## Stack

- PHP 8.4, Laravel 13, Sanctum (SPA session auth), spatie/laravel-permission,
  spatie/laravel-activitylog v5
- MySQL 8 only (never sqlite) — `bidayaat` (dev) / `bidayaat_test` (tests,
  per-process `bidayaat_test_test_N` under `--parallel`)
- Redis for session/cache/queue in local; array/sync in tests

## Commands

```sh
composer install
php artisan migrate:fresh --seed   # local: also creates the @bidayaat.test users
php artisan test                   # or: php artisan test --parallel
./vendor/bin/pint --test           # ./vendor/bin/pint to fix
./vendor/bin/phpstan analyse       # level 5, app/ only
```

Seeded local users: `admin@bidayaat.test` / `editor@bidayaat.test`,
password `password` (only when `APP_ENV=local`).

## Conventions

- Controllers are invokable, thin, and namespaced `App\Http\Controllers\Api\V1`.
- API responses are wrapped resources: `{'data': ...}` with paginator
  `links`/`meta` (default per_page 24, max 100).
- `ForceJsonResponse` + `SetLocale` run on the whole `api` group; locale is
  `ar`/`en` from `Accept-Language` (default `ar`). Validation translations in
  `lang/ar`.
- Rate limiters: `api` 120/min per IP, `login` 5/min per email+IP.
- Audit: models use the `App\Concerns\LogsChanges` trait (dirty-only diffs,
  timestamps stripped, `log_name` = table, optional `edit_summary` request
  input, causer = auth user or null). Read API behind `can:activity.view`
  (editor/admin only). Subject labels via `activitySubjectLabel()` and
  `activityFieldLabels()` model conventions.
- Do NOT commit `.env`. `phpunit.xml` is committed despite the root
  `.gitignore` rule (force-add when it changes).
