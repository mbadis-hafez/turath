# Contract: Creation-review UI

## Create pages (`ArtistCreatePage.vue`, `ArtworkCreatePage.vue`, `EventEditPage.vue`'s create branch,
`ArchiveEditPage.vue`'s create branch)

1. **No visible change to the create form itself.** The fields collected and the "Save"/"Create" action
   stay the same. What changes is what happens after: the redirect target (the type's curation/edit page)
   now shows a creation-review banner rather than a plain editable record (FR-002).
2. **After saving**, the editor lands on the curation/edit page and sees a banner equivalent to
   `DraftStatusBanner`'s `draft` state — reusing that component/pattern rather than inventing a new one —
   with a "Send for review" action, enabled immediately (there's always content to submit; R3/R4).

## Curation/edit pages, for a record with `creation_approved_at === null`

1. **The creator** sees the same page as always (all fields editable, directly — R3), plus the
   creation-review banner in place of `DraftStatusBanner`'s usual states:
   - `draft` (not yet submitted): "Send for review" available.
   - `pending`: "Awaiting review" (reusing the existing pending state exactly).
   - `changes_requested`: reviewer's note shown, editor can keep editing and resubmit.
2. **Anyone else** with the record type's `*.manage` permission sees the record in a read-mostly,
   pending-review state (reusing `DraftStatusBanner`'s `blocked`-style presentation) — they are not the
   one who can act on it as an ordinary record.
3. **The finalize action** (Verify / Publish button, wherever the type has one) is disabled with an
   inline reason ("Awaiting creation review") whenever `creation_approved_at === null` — the same
   disabled-with-reason pattern already used elsewhere (e.g. a non-grantable permission checkbox in the
   roles editor), not a hidden control.

## Review queue (`ProposalsPage.vue`)

1. A pending creation-review item appears in the same queue, alongside edit proposals, using the same
   `review_type` grouping.
2. Opening it shows the record's current content directly — **no diff view** (R2) — reusing the record's
   existing read/detail presentation rather than the diff renderer used for edits.
3. Approve / Request changes controls are the same controls already used for an edit proposal, with the
   same "can't review your own submission" guard.

## Linked-entity pickers (e.g. `EntityPicker` searching artists)

Unapproved records (`creation_approved_at === null`) do not appear in search results — from the picker's
point of view, they don't exist yet.

## Staff registries (admin list pages)

Unaffected in which records are listed (already show unpublished/draft records). Gains one additional
badge, next to the existing publication/verification badges, for a record still awaiting creation review.
