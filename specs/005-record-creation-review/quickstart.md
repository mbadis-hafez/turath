# Quickstart: Validating creation-requires-review

Scenarios map to the spec's user stories; shapes live in [contracts/](./contracts/) and
[data-model.md](./data-model.md) rather than being repeated here.

## Prerequisites

```bash
make install
make migrate         # migrate:fresh --seed
make dev             # API on :8000, SPA on :5173
```

Two accounts: an **editor** (`fidha.fatma@hafezgallery.com`) and a **reviewer** (`samar@hafezgallery.com`).

## Automated checks

```bash
cd backend  && php artisan test --filter=RecordCreationReviewTest
cd frontend && npx vitest run src/pages/admin/ArtistCreatePage.spec.ts src/pages/admin/ArtistCurationPage.spec.ts
cd backend  && ./vendor/bin/phpstan analyse --no-progress
cd frontend && npm run typecheck
```

Baseline before this feature: 354 Pest / 380 Vitest green (T001 of `/speckit-tasks` pins the exact count).

## Scenario 1 — Creating an artist no longer bypasses review (US1, SC-001)

1. Sign in as the editor, create a new artist with a name and a bit of content.
2. **Expect**: landing on the artist's curation page, a "send for review" banner is visible and enabled
   immediately — not a hint that there's nothing to submit (the reported bug).
3. **Expect**: the Verify button is disabled, with a reason distinct from "record incomplete."
4. Send it for review.
5. **Expect**: the artist now shows "awaiting review," and further edits are still possible (R3).

## Scenario 2 — A reviewer approves a brand-new artist (US2, SC-002, SC-003)

1. Sign in as the reviewer, open the review queue.
2. **Expect**: the new artist's creation appears there, in the same queue as edit proposals.
3. Open it.
4. **Expect**: the proposed content renders directly — no diff view.
5. Approve it.
6. **Expect**: `creation_approved_at` is now set; the artist is a fully ordinary record — Verify becomes
   available (subject to the existing completeness rules), and any further edit by the editor goes
   through the normal edit-review pipeline exactly as it would for a long-existing artist.

## Scenario 3 — Request changes on a new artist (US2)

1. As the editor, create and submit a second new artist.
2. As the reviewer, request changes with a note.
3. **Expect**: the editor sees the note and a "changes requested" state; can revise and resubmit without
   losing what they already entered.

## Scenario 4 — Nobody can shortcut review (US3, SC-002)

1. As the editor, create a new artist but do not submit it for review.
2. Attempt to publish/verify it directly via the API.
3. **Expect**: refused, explained as awaiting creation review, regardless of what permissions the caller
   holds.
4. Attempt to select this artist from the artist picker on a new artwork.
5. **Expect**: it does not appear in search results.
6. As a different editor (not the creator), open its curation page.
7. **Expect**: a pending-review state, not a plain editable record.

## Scenario 5 — Applies to artworks, events and archive items too (US1–US3, FR-009)

Repeat scenarios 1–4 for each of the other three record types, substituting each type's own creation
form, curation/edit page, and finalize action (Publish rather than Verify for artwork/event/archive item).
**Expect** identical behavior, using the same review queue and the same `EditProposal`/`ReviewQueueItem`
mechanics — no separate queue or review experience per type.

## Scenario 6 — Nothing about editing an already-approved record changes (SC-004)

1. Pick any artist/artwork/event/archive item that already existed before this feature shipped (backfilled
   `creation_approved_at`).
2. Edit it as an editor, send for review, have a reviewer approve or request changes.
3. **Expect**: identical behavior to before this feature — this is a regression check, not new behavior.
