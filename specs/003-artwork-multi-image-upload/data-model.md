# Phase 1 Data Model: Multiple Image Upload with Primary Image Selection

**No schema migration is required.** Every column this feature needs already exists on
`artwork_images`. What changes is the set of *invariants* enforced over those columns, and where
that enforcement lives.

## Entity: ArtworkImage

Existing table, created in `2026_09_20_000022_create_artwork_images_table.php`. Fields relevant to
this feature:

| Field | Type | Role in this feature |
|---|---|---|
| `id` | bigint PK | Returned per-file so the client no longer has to guess which image it just uploaded |
| `artwork_id` | FK → `artworks`, cascade delete | Scope for both invariants: "one primary" and "no duplicates" are per artwork, not global |
| `path` | string(500) | Private-disk location. Never modified after write (privacy rule 7). Excluded from audit diffs |
| `original_filename` | string(255), nullable | What the registrar is told when a file is rejected or found to be a duplicate |
| `mime_type` | string(120) | Validation surface: JPEG / PNG / WebP only |
| `size_bytes` | unsigned bigint | Validation surface: ≤ 20 MB per file |
| `sha256` | char(64), **indexed** | Duplicate detection key. Compared per artwork on upload |
| `width_px` / `height_px` | unsigned int, nullable | Shown per queued/attached image so registrars can tell frames apart |
| `rights_status` | string(20), default `unknown` | Per-image rights; set at attach time, never auto-cleared |
| `is_final` | boolean, default `false` | **The primary designation.** User-facing name becomes "primary"; column name unchanged (see research R5) |
| `uploaded_by_user_id` | FK → `users`, nullable | Existing audit attribution, unchanged |

Existing indexes — `(artwork_id, is_final)` and `(sha256)` — already support both new queries
("does this artwork have a primary?" and "does this artwork already have this checksum?"). No index
changes needed.

### Relationships

- `Artwork hasMany ArtworkImage`, ordered `is_final DESC, id ASC` (`Artwork::images()`). The
  ordering means the primary image sorts first for free, which is why the panel's main preview and
  the registry thumbnail can both rely on position.
- `ArtworkImage belongsTo Artwork`; `belongsTo User` (uploader).
- Cascade delete on `artwork_id`: deleting an artwork removes its images. Unchanged.

## Invariants

These are the substance of the feature. **I1 and I2 are new**; I3 exists today but only on one path.

### I1 — An artwork with at least one image has exactly one primary

> `count(images) > 0` ⟹ `count(images where is_final) == 1`

Never zero, never more than one. Zero images means no primary, which is legal.

Enforced by `ArtworkImageObserver`:
- **on `created`** — if the parent artwork has no image with `is_final = true`, set it on this one.
  This is what makes the first image attached to an artwork automatically primary (FR-006), on every
  path including the curation screen, imports and seeders.
- **on `deleted`** — if the removed image was primary and siblings remain, promote the oldest
  remaining (lowest `id`) (FR-009).

Bulk queries that bypass model events — notably `ArtworkMerger`'s single-statement
`update(['artwork_id' => …, 'is_final' => false])` — must re-assert I1 explicitly afterwards. See
research R4; today that path leaves a survivor with images and no primary.

### I2 — No two images on one artwork are byte-identical

> for any two images `a`, `b` on the same artwork: `a.sha256 != b.sha256`

Enforced at upload time by comparing each incoming file's SHA-256 against the artwork's existing
image checksums. A match is not attached; it is reported as a duplicate naming the file and the
image it matched (FR-011).

Not enforced by a unique index: MySQL would surface it as a driver integrity error needing
translation back into a per-file message, and it would let the merge path fail on a constraint
(research R2). Scope is deliberately per artwork — the same photograph may legitimately be attached
to two different artworks.

### I3 — Setting a primary clears the previous one

> `PATCH … {is_final: true}` ⟹ every other image on that artwork has `is_final = false`

Already implemented in `ArtworkImageController::update()`, inside a transaction, and already covered
by a test in `ArtworkCurationTest.php`. Unchanged by this feature; listed because I1 depends on it.

### I4 — Attaching never changes publication or rights state

> Attaching an image leaves `artworks.publication_status` untouched, and the new image's
> `rights_status` is whatever was submitted (default `unknown`).

An image becomes publicly readable only when its rights are `licensed`/`public_domain` **and** its
artwork is `published` — checked in `ArtworkImageController::show()`, not stored. Unchanged by this
feature and asserted by FR-015 / privacy rule 5.

## State transitions

### Primary designation, per artwork

```text
        ┌────────────────── no images ──────────────────┐
        │                                              │
        │  first image attached (observer, I1)          │  last image removed
        ▼                                              │
  ┌───────────────────────────────────────────┐         │
  │ exactly one primary  ◀── always true ──── │ ────────┘
  └───────────────────────────────────────────┘
        ▲            │                    ▲
        │            │ registrar picks    │ primary removed →
        │            │ another (I3)       │ promote oldest (I1)
        └────────────┘────────────────────┘
```

There is no "images present, primary unset" state after this feature. Legacy artworks may still be
*stored* in that state (nothing migrates them retroactively, per the spec's Assumptions); reads stay
safe because `ArtworkImageController::primary()` keeps its "primary, else first" fallback, which
becomes a legacy-compatibility path rather than the normal case.

### Pending image lifecycle (create screen only)

```text
selected → validated client-side → queued → (artwork saved) → uploaded → attached
              │                       │                          │
              ├─ rejected: bad type/size (named, siblings kept)   ├─ rejected server-side (named)
              └─ rejected: same name+size+mtime already queued    └─ duplicate: checksum match (named)
```

A `Pending` image exists only in the create screen's memory and carries the same two decisions a
committed image does — `rights` and primary designation — so choices made before saving survive the
save. It is not persisted anywhere; abandoning the screen uploads nothing and orphans nothing.

## Validation rules

Derived from the spec's functional requirements:

| Rule | Source | Where enforced |
|---|---|---|
| JPEG / PNG / WebP only | existing behaviour | `StoreArtworkImagesRequest` (server, authoritative) + `accept` attribute (client hint) |
| ≤ 20 MB per file | existing behaviour | `StoreArtworkImagesRequest` |
| ≤ 20 images per artwork, counting existing + incoming | FR-014 | `StoreArtworkImagesRequest`; mirrored in the panel for early feedback |
| Each file validated independently; valid siblings survive an invalid one | FR-004 | Per-file loop in the controller, not a whole-request `fail-fast` rule |
| Byte-identical duplicate blocked and named | FR-011, privacy rule 7 | Controller checksum comparison (I2) |
| `rights_status` ∈ {unknown, licensed, public_domain, all_rights_reserved} | existing `ArtworkImage::RIGHTS` | `StoreArtworkImagesRequest` |
| Exactly one primary when images exist | FR-005/006/009 | `ArtworkImageObserver` (I1) |
| No publication/rights change on attach | FR-015 | Absence of any such write; asserted by test |

## Audit behaviour

`ArtworkImage` uses `LogsChanges`, so creates, primary flips and deletes are recorded field-level
with causer (privacy rule 9, D14). `path` and `sha256` stay in `excludedFromActivityLog()` so file
locations and checksums never land in the activity log. Observer-driven primary changes are ordinary
model saves and are therefore audited like a human's click — which is desirable: "why did this
become the primary image?" is answerable from the log.
