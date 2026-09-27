# Data Model: Creation requires review, same as editing

## New column: `creation_approved_at` (nullable timestamp)

Added to `artists`, `artworks`, `events`, `archive_items`. `NULL` from the moment a record is created
until a reviewer approves its creation-review item (R1). Every existing row (created before this feature
ships) is backfilled to `now()` at migration time — nothing that already exists retroactively needs
review; only records created after this feature ships start `NULL`.

- Indexed alongside the existing `publication_status`/`verified_status` indexes, since every publish/verify
  gate and every visibility scope now also filters on it.
- Not user-editable through any request; only ever set by the approval path (R4).

## `EditProposal` — new column: `is_creation` (boolean, default `false`)

No other schema change to `EditProposal`/`ReviewQueueItem` — a creation-review item is an ordinary
`EditProposal` row with:
- `payload = null`, `field_diffs = []` (R2/R3 — nothing to diff; the live record already holds the
  proposed content).
- `is_creation = true`.
- `review_type` derived once, at submit time, the same way an edit's is (`ProposableFields::reviewTypeFor()`
  against the record's full set of populated columns) — no new `ReviewType` case.
- Same `status` lifecycle as today: `draft` → `pending` → `approved` / `changes_requested` (→ `pending`
  again on resubmit). `rejected` is not reachable for a creation item through the UI described in this
  spec (there is no "reject a whole new record" scenario defined — request-changes is how a reviewer
  sends it back); the column itself doesn't need to forbid it, but no control is added to trigger it.

Created automatically, server-side, in the same transaction as the record itself, at the moment of
creation — the creator never explicitly "starts" a creation-review item the way they explicitly start an
edit draft; it always exists from the record's first moment, in `draft` status.

## Per-type enforcement points (R5/R6 — not uniform, mapped explicitly)

| Type | Direct-write create endpoint today | Finalization action(s) to gate | Existing completeness check |
|---|---|---|---|
| Artist | `POST /artists` (`ArtistCreatePage.vue` → `createArtist()` + follow-up curation/entries/social/portrait/assignment writes) | `PATCH /artists/{id}` setting `publication_status=published`; `POST /artists/{id}/verify` | `PublishGate::assertPublishable()` (publish); `ArtistCurationService::verifyErrors()` (verify) |
| Artwork | `POST /artworks` (`ArtworkCreatePage.vue` → `createArtwork()` + image uploads) | `PATCH /artworks/{id}` setting `publication_status=published` | `PublishGate::assertPublishable()` |
| Event | `POST /events` (`EventEditPage.vue` create branch) | `POST /events/{id}/publish` | Inline completeness check in `EventController::publish()` (not currently routed through the shared `PublishGate`) |
| Archive item | `POST /archive-items` (`ArchiveEditPage.vue` create branch) | `POST /archive-items/{id}/publish` | `ArchiveItemPublishController`'s own local `assertPublishable()` (not the shared `PublishGate` class, despite the same name) |

Each of the four finalization points gets the same one-line addition: refuse with a distinct,
explained error (not folded into the generic completeness-error shape, per FR-006 — "you can't publish
this because it hasn't been reviewed yet" reads differently from "you can't publish this because a field
is missing") when `creation_approved_at IS NULL`.

## Linked-entity pickers scoped to `creation_approved_at IS NOT NULL` (R5, FR-008)

- Artist picker used when linking an artist to an artwork/event/archive item (`EntityPicker` usages
  searching artists) — must exclude unapproved artists, the clearest "treated as established" case named
  in the spec.
- Any equivalent picker for the other three types, if one exists and searches across live records rather
  than a fixed list — scoped identically. (Events and archive items are not typically "linked from"
  elsewhere the way an artist is; confirm actual picker usages during `/speckit-tasks` rather than assume
  parity here.)

Staff-facing **registries** (the admin list pages) are explicitly *not* scoped this way (R5) — they
already show unpublished/draft records for staff to work on, and continue to show unapproved creations
too, now with an added "pending creation review" badge alongside the existing publication/verification
badges.

## State summary (per record, per type)

```
created ──(creation-review item: draft)──┐
                                          │ creator edits the live record directly (R3)
                                          │ "send for review"
                                          ▼
                                       pending ──(reviewer: request changes)──► changes_requested
                                          │                                         │
                                          │ (reviewer: approve)                     │ creator revises,
                                          ▼                                         │ resubmits
                          creation_approved_at = now()                             │
                          record is now ordinary — same                            │
                          edit-review pipeline as any                              │
                          existing record applies from here                        │
                                          ▲─────────────────────────────────────────┘
```

Once `creation_approved_at` is set, the creation-review `EditProposal` is done (closed, same as an
approved edit) and plays no further role — every subsequent change to the record goes through the
existing, unmodified edit-review pipeline (`is_creation = false` proposals, as today).
