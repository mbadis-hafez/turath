# Contract: Review Queue API

**Feature**: 002-condition-report-review-queue | Base: `/api/v1`, session-authenticated, JSON.

## `GET /review-queue`

Lists every pending submission the caller may act on, whatever flow produced it (FR-001, FR-002).

**Query params**: `review_type` (optional, one of `ReviewType`), `status` (optional, default
`pending`), `record_type` (optional citable segment), `mine` (optional `1` — entries the caller
submitted, for FR-006), `page`, `per_page` (default 24, max 100).

**Visibility rule**: an entry is returned iff the caller holds `review_queue.{review_type}` for
that entry, or `mine=1` and the caller is the submitter. No assignee filtering (FR-008).

**200 response** — `data[]` + standard pagination `meta`. Each entry:

```jsonc
{
  "id": "uuid",
  "review_type": "archivist_review",
  "status": "pending",
  "submitted_at": "2026-09-22T10:14:00+00:00",
  "submitted_by": { "id": 4, "name": "Fatma" },          // null if the user was deleted
  "note": "Imported from the 1998 archive box",
  "title": { "ar": "…", "en": "Condition report — Easter" },  // the submitted document (FR-003)
  "record": {                                             // the linked record (FR-003)
    "type": "artworks",
    "id": 312,
    "title": { "ar": "…", "en": "Easter" },
    "available": true                                     // false once merged/deleted
  },
  "is_proposal_backed": false,
  "edit_proposal_id": null,                               // non-null → render the diff viewer
  "changed_since_submission": false,
  "can_record_outcome": true,
  "reviewed_by": null,                                    // populated once terminal (FR-005)
  "reviewed_at": null,
  "review_note": null
}
```

For a proposal-backed entry, `edit_proposal_id` is set and `record` describes the proposal's
citable; the client fetches `GET /proposals/{id}` for the diffs exactly as it does today.

**403** when unauthenticated. An authenticated caller with no review permission and no own
submissions gets `200` with an empty `data`.

## `POST /review-queue/{reviewQueueItem}/outcome`

Records a review outcome on a standalone (non-proposal) entry (FR-004, FR-005).

**Body**: `{ "outcome": "approved" | "rejected", "review_note": "string|null" }` —
`review_note` required (min 3, max 5000) when `outcome` is `rejected`.

**Effects**: sets `status` to the outcome, `reviewed_by_user_id` to the caller, `reviewed_at` to
now; the entry leaves the pending listing.

| Status | Condition |
|---|---|
| `200` | outcome recorded; returns the updated entry in the shape above |
| `403` | caller lacks `review_queue.{review_type}` for this entry |
| `409` | entry already terminal (`approved` / `rejected`) |
| `422` | entry is proposal-backed (use the proposal routes), or body fails validation |

## `POST /review-queue/{reviewQueueItem}/acknowledge` *(existing, unchanged)*

Retained for existing callers. Sets `status` to `acknowledged` without recording an outcome.

## Submission-time reachability guard

Applies to every endpoint that creates a queue entry — `POST /archive-items/{id}/submit-review`,
material intake, and proposal submission (FR-006, SC-004).

When no user holds the entry's `review_queue.{review_type}` permission, the request fails with
`422` and **no entry is created**:

```json
{ "message": "No reviewer can receive this submission.",
  "errors": { "review_type": ["No user holds the archivist_review review permission."] } }
```

Otherwise behaviour is unchanged, including the existing duplicate guard on
`submit-review` (an already-pending entry is reused, not duplicated).

## UI contract — Review queue page (`/:locale/proposals`)

1. The page lists entries from `GET /review-queue` with `status=pending`; a reviewer with
   `review_queue.archivist_review` sees an archive-sourced condition report alongside proposal
   entries in the same list (FR-002).
2. Each row shows document title, submitter name, submission date, linked record title, and a
   localized review-type label (FR-003).
3. A proposal-backed row expands into the existing `ProposalDiffViewer` and uses the existing
   approve/reject actions; a standalone row expands into a context card with the same two outcome
   actions, wired to `POST /review-queue/{id}/outcome` (FR-004).
4. Rejecting requires a note in both row kinds; approving does not.
5. After an outcome is recorded the row leaves the pending list without a full page reload, and the
   count in the header decrements (FR-005).
6. A row whose `record.available` is `false` still renders, with an explicit "linked record
   unavailable" note in place of the record link (Edge Cases).
7. A row with `changed_since_submission` shows a "changed since submission" marker (Edge Cases).
8. A contributor without any review permission sees only their own submissions (`mine=1`) with a
   read-only pending/outcome state — the current "my suggestions" behaviour, now including archive
   submissions (FR-006).
9. All new strings exist in `frontend/src/i18n/locales/en/*.json` and `…/ar/*.json`.
