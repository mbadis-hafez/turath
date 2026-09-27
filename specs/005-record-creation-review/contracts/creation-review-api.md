# Contract: Creation-review API

Builds on the existing edit-proposal endpoints (`GET/PATCH /proposals/{proposal}`,
`POST /proposals/{proposal}/approve`, `POST /proposals/{proposal}/request-changes`) rather than adding a
parallel set — a creation-review item is an `EditProposal` with `is_creation = true` (data-model.md).

## Changed: `POST /artists`, `POST /artworks`, `POST /events`, `POST /archive-items`

No change to the request shape. Response shape unchanged. **New side effect**: creates a creation-review
`EditProposal` (`status: draft`, `is_creation: true`) in the same transaction as the record, and the
returned record now carries `creation_approved_at: null`.

## New: `POST /records/{type}/{id}/creation/submit` (or equivalent — reuses `EditorialDraftController`'s
routing shape)

Sends the record's open creation-review item to the review queue. Distinguishes itself from
`POST /records/{type}/{id}/draft/submit` (the existing edit-draft submit endpoint) because a creation
item has no `payload` to diff — no "nothing differs" check applies; submitting is always valid once the
item exists (it always does, from the moment of creation).

| Condition | Status | Why |
|---|---|---|
| No open creation-review item for this record (already approved, or already pending) | 422 | Nothing to submit |
| Caller is not the record's creator | 403 | Same "own proposal" rule as editing |
| Success | 200 | `is_creation` item flips `draft` → `pending`, appears in the review queue |

## Unchanged, but now also serves creation items: `POST /proposals/{proposal}/approve`

For `is_creation = true`: no field values to apply (`field_diffs` is empty) — approval sets
`creation_approved_at = now()` on the record, closes the queue item, and logs an activity entry (causer:
reviewer; subject: the record; note: creation approved). For `is_creation = false` (the existing case):
unchanged.

## Unchanged, but now also serves creation items: `POST /proposals/{proposal}/request-changes`

No change — a creation item's `changes_requested` state behaves exactly like an edit's.

## Changed: every finalization endpoint (data-model.md's table)

| Endpoint | New refusal |
|---|---|
| `PATCH /artists/{id}` (setting `publication_status: published`) | **422**, `completeness.creation_review`: "This record hasn't been reviewed yet." — refused before the existing completeness check runs |
| `POST /artists/{id}/verify` | Same, before `ArtistCurationService::verifyErrors()` runs |
| `PATCH /artworks/{id}` (setting `publication_status: published`) | Same shape as artist publish |
| `POST /events/{id}/publish` | Same shape, before the event's own inline completeness check |
| `POST /archive-items/{id}/publish` | Same shape, before `ArchiveItemPublishController`'s own `assertPublishable()` |

## Changed: linked-entity read endpoints used by pickers (e.g. artist search used by `EntityPicker`)

Scoped to exclude records with `creation_approved_at IS NULL`, in addition to whatever scoping they
already apply. Does not affect the staff registry list endpoints (`GET /artists` etc. under `manage`
scope), which continue to return unapproved records for curation purposes.

## Response shape addition

Every record resource (`ArtistResource`, equivalent for the other three types) gains
`creation_approved_at: string | null` (ISO 8601), following the same pattern as `verified_at`/
`reviewed_at` elsewhere.
