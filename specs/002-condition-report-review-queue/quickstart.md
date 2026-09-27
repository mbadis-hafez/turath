# Quickstart Validation: Condition Report Review Queue Visibility

**Feature**: 002-condition-report-review-queue | **Date**: 2026-09-23

Proves the reported bug is fixed end-to-end and that no existing review flow regressed.
Entity fields referenced here are defined in [data-model.md](./data-model.md); endpoint shapes in
[contracts/review-queue-api.md](./contracts/review-queue-api.md).

## Prerequisites

- `backend/.env` pointing at the local MySQL `bidayaat` database, dependencies installed
  (`composer install`, `npm ci` in `frontend/`).
- Migrations applied: `php artisan migrate`.
- Two seeded users: one holding `archive.manage` + `proposals.submit` (the submitter, "Fatma"),
  one holding `review_queue.archivist_review` and nothing else (the reviewer, "Samar").

## Automated gates

Run from the repository root; all four must exit zero.

```bash
cd backend  && ./vendor/bin/pest
cd backend  && ./vendor/bin/pint --test
cd frontend && npm run typecheck && npm run lint
cd frontend && npm run test:run && npm run build
```

Expected new coverage:

- Feature test: archive submit-review creates exactly one pending entry, and a reviewer holding
  only `review_queue.archivist_review` receives it from `GET /review-queue` (FR-001, SC-001).
- Feature test: a second `submit-review` on the same archive item does not create a second entry.
- Feature test: recording an outcome sets status, `reviewed_by_user_id`, and `reviewed_at`, and the
  entry disappears from the pending listing (FR-005).
- Feature test: a proposal-backed entry rejects `POST /review-queue/{id}/outcome` with `422`, while
  the proposal approve route still cascades its queue status (SC-005).
- Feature test: submitting when no user holds the review permission returns `422` and creates
  nothing (SC-004).
- Feature test: a reviewer whose permission is revoked while an item is pending gets a clean
  listing without the item, and other eligible reviewers still see it (FR-008).
- Vitest: the review queue page renders a standalone entry with its outcome actions, a
  proposal-backed entry with the diff viewer, an unavailable-record row, and a
  changed-since-submission row.

## Manual scenarios

1. **Reported reproduction (SC-003)** — As Fatma: import a condition report from the archive, link
   it to the artwork "Easter", press *Send to review*. Log in as Samar, open **Review queue**. The
   submission appears with document title, "Fatma", the submission date, "Easter", and the
   *Archivist review* type.
2. **Outcome (FR-004, FR-005)** — As Samar, open that entry, inspect the linked artwork, and record
   *Approve*. The row leaves the pending list and the header count decrements. Reload: it stays gone.
3. **Reject requires a note** — Repeat with a second submission and choose *Reject*. Submitting with
   an empty note is blocked; with a note it succeeds and the note is stored.
4. **Submitter visibility (FR-006)** — As Fatma, reopen the archive item / artwork screen. Before
   step 2 it shows *Pending review*; after step 2 it shows the recorded outcome and Samar's name.
5. **No eligible reviewer (SC-004)** — Temporarily revoke `review_queue.archivist_review` from every
   user, then attempt *Send to review*. Fatma sees an immediate error naming the review type and no
   queue entry is created. Restore the permission afterwards.
6. **Merged/deleted link target** — Merge "Easter" into another artwork while an entry is pending.
   The entry still lists, showing *linked record unavailable* instead of a dead link.
7. **Changed since submission** — Edit the archive item while its entry is pending. The row shows
   the *changed since submission* marker.
8. **Bilingual** — Switch to Arabic and repeat scenario 1. All queue labels, the review-type name,
   and the outcome actions are translated, with no raw i18n keys visible.

## Regression checklist (SC-005)

- Curation drafts and field proposals still reach reviewers, still open the existing diff viewer,
  and approve/reject still applies field changes and writes a revision.
- The *request changes* action on drafts still works for review-queue permission holders.
- Material intake submissions still appear on the material submissions screen.
- The dashboard completion sidebar's pending-review indicator is unchanged.
- `POST /review-queue/{id}/acknowledge` still returns `200` for existing callers.
