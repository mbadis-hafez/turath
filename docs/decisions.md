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

## F1 — Artists (2026-09-19)

- **D15 — Search: LIKE on pre-normalized columns, not `pg_trgm`.** (Fulfils the A1 deferral.) `artists.search_text` and `artists.search_compact` are written at save time by `ArtistSearchTextBuilder` from `ArabicNormalizer`-normalized names + name variants; a query is split into tokens and every token must `LIKE '%tok%'` the search_text (AND), OR the whole normalized query matches inside `search_compact` (handles cross-token substrings and missing tatweel). Wildcards are escaped; a punctuation-only query matches everything. Normalization: NFKC, case-fold, Arabic diacritics (U+064B–065F, U+0670) and tatweel stripped, letter folding via explicit map (أإآ→ا, ة→ه, ى→ي, ؤ→و, ئ→ي), Eastern digits → Western, punctuation → spaces. Rationale: MySQL has no pg_trgm; a generated-column fulltext index was rejected because Arabic fulltext tokenization is poor and exact control over normalization was required. Trade-off: tokens are substring matches, so very short tokens can over-match; mitigated by AND-ing tokens.
- **D16 — Statuses are string columns + PHP enums, not MySQL ENUM.** `living_status`, `verified_status`, `publication_status`, variant `type`/`language`, date `calendar`/`certainty` are `VARCHAR` checked by CHECK constraints (raw `DB::statement`, Laravel 13 Blueprint has no `check()`) and backed by `App\Enums\*` string enums. Rationale: adding a value never needs a table rebuild; CHECK keeps the DB honest even outside Eloquent.
- **D17 — Sorting: `COALESCE(name_ar, name_en)` + `id` tiebreaker, whitelisted.** All four list sorts (`name_ar`, `name_en`, plus `-` desc prefixes) order by `orderByRaw('COALESCE(ar, en)')` with a deterministic `id` tiebreaker; anything not in the match whitelist falls back to the default sort. Rationale: either language may be null (D9), and offset pagination requires a stable total order.
- **D18 — Partial dates are five real columns behind one virtual attribute.** `birth_*`/`death_*` column groups (`_date_display`, `_year_from`, `_year_to`, `_calendar`, `_certainty`) are mapped by `PartialDateCast` onto a `PartialDate` value object exposed as a single `birth`/`death` attribute (registered via a `partialDate()` Blueprint macro). Rationale: partial dates must be queryable/sortable later (decades, century filters in F6) without JSON extraction; the VO keeps the API and forms simple.
- **D19 — Two parallel route shapes: `/artists/{slug}` (public GET) vs `/artists/{id}` (writes).** IDs are bound with `whereNumber` on every protected route so a slug can never reach an `{id}` parameter; the public GET-by-slug uses an explicit `[a-z0-9-]+` regex. Consequence: requests like `PATCH /artists/{slug}` 405 (GET-only match) and `POST /artists/{slug}/verify` 404 (no match).
- **D20 — Policy signatures must absorb Gate-stripped class-name arguments.** Laravel's `can:` middleware strips `App\Models\Artist` class-string arguments before calling the policy, so `create`/`restore` are declared as `create(User $user)` / `restore(User $user, ?Artist $artist = null)` while resource-instance checks keep the model parameter.
- **D21 — Rate limiters are disabled under `runningUnitTests()`.** F1's test matrix issues many api-group requests per test; the array-store 120/min limiter persists per process and would flake the suite. Production/local limits are unchanged.
- **D22 — Known limitation: hard-deleted children drop out of parent activity history.** `SubjectActivityController` unions child rows by `subject_id IN (subquery)`; once a row is hard-deleted the subquery no longer returns its id, so only its activity (not the child's identity/subject) remains reachable. Soft-deleted parents are fine (`withTrashed`). Accepted for F1; revisit if a feature needs undeleted history of hard-deleted rows.

## F2 — Holders + Artworks (2026-09-20)

- **D23 — No DB-level CHECK ties `attribution_certainty` to `artist_id`.** MySQL 8 rejects a CHECK constraint on a column that participates in a foreign key's `ON DELETE SET NULL` action (error 3823: "Column 'artist_id' cannot be used in a check constraint ... needed in a foreign key constraint ... referential action"), which `artworks.artist_id` does. The invariant (`unattributed` ⇔ `artist_id IS NULL`) is enforced instead in `ArtworkPayloadRequest::validateAttribution()`. Consequence: a raw SQL write or a hard-delete cascade could in theory leave the two out of sync; accepted since all app writes go through the Form Request and artists are never hard-deleted through normal app flow.
- **D24 — Dimension parsing lives in one reusable class, called from the request layer, not an observer.** `DimensionParser::parse()` is invoked by `ArtworkPayloadRequest::mapDimensions()` when a client sends `dimensions.raw` (or `frame_dimensions.raw`) without also sending structured `height_cm`/`width_cm`/`depth_cm`. If structured values are sent directly, they win and `dimensions_raw` is left untouched (preserves the original source string as an audit trail even after a manual correction). The same class is reused unmodified by `ArtworkSeeder` and is the one F4's importer must reuse.
- **D25 — Holder privacy is enforced in the resource layer via `HolderDisplayResolver`, not by hiding rows.** `is_public_name = false` holders are still stored with their real name and returned from every endpoint, but `HolderResource`/`ArtworkResource` always render the resolved display name (real name, "Private collection, {city}", "Family collection", or "Artist's estate" per `HolderDisplayResolver::resolve()`) and only include the raw `name_ar`/`name_en` and `internal_notes` when the requester has `holders.manage`. There is no public holder list endpoint in F2 (only single-record lookup through an artwork), so private collectors can't be enumerated even in aggregate.
- **D26 — `search_text` on an artwork is resynced when its artist's name changes.** `ArtistObserver::saved()` now also walks `$artist->artworks()` and calls `ArtworkSearchTextBuilder::rebuildQuietly()` on each when `name_ar`/`name_en` changed, so renaming an artist doesn't leave stale search text on their linked artworks.
