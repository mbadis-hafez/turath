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

## F3 — Archive Items, Files, Rights & Access (2026-09-20) — Pass 1 (data model + API)

Pass 1 covers `archive_items`/`files`/`archive_item_links`, access control, policies, the read/write/publish/link API, and seeders. The tus upload endpoint, SHA-256 dedupe, and thumbnail queue jobs are Pass 2, deferred so the riskiest new dependencies (tus-php, Imagick/pdftoppm availability) don't block the core.

- **D27 — OPEN item resolved: `post_embargo_access_level` defaults to `public`, and no intermediate "sees description but not files" tier exists.** Per the spec's own fallback, an embargoed item that has lifted falls through to its explicit `post_embargo_access_level` column (set at write time, defaulting to `public`); `researcher`/`institution` role holders get exactly the tier `ArchiveAccessResolver::userTier()` assigns them (registered/researcher/institution_only) with no separate metadata-vs-files split beyond the existing `canViewMetadataOnly()`/`canViewFull()` boundary. Revisit if a human wants the intermediate tier later.
- **D28 — Access tiers map onto existing F0 roles, no new `registered` role added.** `ArchiveAccessResolver::userTier()`: anonymous → `public`; `verified_researcher` role → `researcher`; `institution` role → `institution_only`; any other authenticated user (`reader`, `contributor`, `artist_claimed`, or `editor`/`admin` without `archive.manage`, though those always short-circuit to full access) → `registered`. There's no dedicated `registered` role in `RolesAndPermissionsSeeder` because "authenticated at all" already is that tier.
- **D29 — No DB-level CHECK ties `access_level = embargoed` to `embargo_until`, for the same MySQL 8/FK reason as D23 — actually this one has no FK conflict, so the CHECK is kept.** (`archive_items_embargo_date_check`.) Noted here only to record that, unlike D23's artist_id case, this constraint could be added safely because `access_level`/`embargo_until` don't participate in any foreign key.
- **D30 — Soft-deleting a parent archive item orphans children instead of cascading.** `archive_items.parent_id` is `nullOnDelete()` at the FK level, but that only fires on a hard `DELETE`, never on Eloquent's `SoftDeletes`. `ArchiveItemObserver::deleting()` explicitly sets `children()->update(['parent_id' => null])` before a soft delete, matching the spec's "deleting a parent sets children's parent_id to null, not cascade-deleting children."
- **D31 — Publish validation reads the persisted record, not the request body.** `POST .../publish` takes only an optional `edit_summary`; `ArchiveItemPublishController::assertPublishable()` validates the archive item's already-saved attributes (type, title/description presence, rights-status-vs-access-level, embargo date) so an editor can't bypass the "no unknown-rights material at public access" rule by omitting a field from the publish call itself.
- **D32 — Archive item links reuse the polymorphic `archive_item_links` table via a real model (`ArchiveItemLink`), not Eloquent's built-in morph-pivot sugar**, because the table carries its own `role` column, timestamps, and needs its own `LogsChanges` audit trail — a plain pivot wouldn't get audited. Search-text resync on link add/remove is wired through `ArchiveItemLinkObserver`, mirroring D26's artist-rename resync.
