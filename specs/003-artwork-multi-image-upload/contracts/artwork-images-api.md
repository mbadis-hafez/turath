# Contract: Artwork Images API

Existing routes (`backend/routes/api.php`). Only `POST` changes; `PATCH`, `DELETE` and `GET …/file`
are documented here as the unchanged context the feature relies on.

All write routes sit behind Sanctum SPA cookie auth and the `artworks.manage` permission.
`{artwork}` and `{image}` are constrained to numbers by `whereNumber`, per D19.

---

## POST `/api/v1/artworks/{artwork}/images` — attach one or many images

**Changed by this feature.** Accepts the existing single-file shape *and* a new batch shape.

### Request

`multipart/form-data`

| Field | Type | Required | Notes |
|---|---|---|---|
| `images[]` | file × 1..N | one of `images[]` / `image` | New. JPEG, PNG or WebP, ≤ 20 MB each |
| `image` | file | one of `images[]` / `image` | Existing single-file field, still accepted |
| `rights_status` | string | no | One of `unknown` \| `licensed` \| `public_domain` \| `all_rights_reserved`. Defaults to `unknown`. Applies to every file in the batch |
| `edit_summary` | string ≤ 255 | no | Existing audit note field |

Sending both `images[]` and `image` is a validation error — the caller should pick one shape.

### Validation

Rejected at the **request** level (422, nothing attached):

- neither `images[]` nor `image` present
- `rights_status` not in the allowed set
- `existing image count + incoming count > 20` (FR-014)

Rejected **per file** (200/201 with a `duplicate`/`rejected` entry in `results`, siblings still
attached — FR-004):

- wrong mime type or extension
- over the per-file size limit
- byte-identical SHA-256 to an image already on this artwork (FR-011, invariant I2)

### Response `201 Created`

```json
{
  "data": [
    {
      "id": 41,
      "url": "/api/v1/artworks/7/images/41/file",
      "filename": "AR013_Radwi_ARW001_front.jpg",
      "width_px": 4000,
      "height_px": 3000,
      "size_bytes": 5242880,
      "rights_status": "unknown",
      "is_final": true
    }
  ],
  "results": [
    { "filename": "AR013_Radwi_ARW001_front.jpg", "status": "attached",  "image_id": 41 },
    { "filename": "AR013_Radwi_ARW001_back.jpg",  "status": "attached",  "image_id": 42 },
    { "filename": "AR013_Radwi_ARW001_front.jpg", "status": "duplicate", "image_id": 41,
      "message": "Already attached to this artwork." },
    { "filename": "notes.pdf",                    "status": "rejected",
      "message": "Must be a JPEG, PNG or WebP image." }
  ]
}
```

- **`data`** — the artwork's complete image list after the call, in `is_final DESC, id ASC` order.
  Shape and ordering unchanged from today, so existing callers (the curation screen's single-file
  path, current Pest and Vitest assertions) keep working untouched.
- **`results`** — **new.** One entry per submitted file, in submission order. `status` is one of
  `attached` \| `duplicate` \| `rejected`. `image_id` is present for `attached` (the row created) and
  for `duplicate` (the row it matched), absent for `rejected`. `message` is human-readable and
  localisable; it is present for everything except `attached`.

`results` is additive — callers that ignore it behave exactly as before.

### Side effects

- If the artwork had no primary image, the first successfully attached file becomes primary
  (invariant I1, via `ArtworkImageObserver`). Visible as `is_final: true` in `data`.
- Attaching **never** changes `publication_status` on the artwork, and never changes any other
  image's `rights_status` (FR-015).
- Each attach is audited field-level, with `path` and `sha256` excluded from the diff.

### Status codes

| Code | When |
|---|---|
| 201 | At least one file attached (even if others were rejected or duplicates) |
| 422 | Request-level validation failed, or **every** file failed — nothing attached |
| 403 | Caller lacks `artworks.manage` |
| 404 | Artwork does not exist |

---

## PATCH `/api/v1/artworks/{artwork}/images/{image}` — set rights / designate primary

**Unchanged.** Documented because the primary invariant depends on it.

```json
{ "rights_status": "licensed", "is_final": true, "edit_summary": "optional" }
```

All three fields optional. `is_final: true` clears the flag on every other image of that artwork
inside a transaction (invariant I3, already implemented and already tested).

Response `200`: `{ "data": [ …full image list… ] }`.

> The field stays `is_final` on the wire. Only user-facing wording becomes "primary" (research R5),
> so no client contract breaks.

---

## DELETE `/api/v1/artworks/{artwork}/images/{image}` — remove an image

**Behaviour extended by the observer, contract unchanged.** Deletes the file from the private disk
and the row.

New side effect: if the removed image was the primary and siblings remain, the oldest remaining
image becomes primary (invariant I1, FR-009). Reflected in the returned list.

Response `200`: `{ "data": [ …full image list… ] }`.

---

## GET `/api/v1/artworks/{artwork}/images/{image}/file` — stream the bytes

**Unchanged, and deliberately so.** The only public read path for image bytes. Returns the file only
when the caller holds `artworks.manage`, **or** all of: the artwork is not soft-deleted, its
`publication_status` is `published`, and the image's `rights_status` is `licensed` or
`public_domain`. Otherwise 404.

This feature adds no new read path and does not relax this check (privacy rules 5 and 6).

---

## Consumed by

| Caller | Shape used |
|---|---|
| `frontend/src/api/artworkCuration.ts` → `uploadArtworkImages()` | new `images[]` batch shape, reads `results` |
| `ArtworkCreatePage.vue` | batch upload after artwork creation; uses `results[].image_id` instead of the current `Math.max(id)` guess |
| `ArtworkCurationPage.vue` | batch shape via the shared panel |
| `AdminArtworkIndexController` | not a caller — derives `thumbnail_url` from `ArtworkImageController::primary()`, which benefits from I1 without changing |
