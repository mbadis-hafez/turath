# Phase 1 Data Model: Condition Report Review Queue Visibility

**Feature**: 002-condition-report-review-queue | **Date**: 2026-09-23

Only one table changes. Everything else in this feature is read-path and UI work over existing
entities.

## Modified: `review_queue_items`

Existing columns (unchanged): `id` (uuid pk), `citable_type`, `citable_id`, `edit_proposal_id`
(nullable fk → `edit_proposals`, cascade delete), `review_type` (string 30), `submitted_by_user_id`
(nullable fk → `users`, null on delete), `submitted_at`, `note` (string 255), `status` (string 20).

New columns:

| Column | Type | Null | Notes |
|---|---|---|---|
| `reviewed_by_user_id` | foreignId → `users` | yes | null on delete; set when an outcome is recorded (FR-005) |
| `reviewed_at` | timestamp | yes | set with the outcome (FR-005) |
| `review_note` | text | yes | reviewer's note; required when the outcome is `rejected` |

New index: `(status, submitted_at)` — the queue's default listing is pending, newest first.

### `ReviewQueueStatus` (enum, `App\Enums\ReviewQueueStatus`)

| Value | Meaning |
|---|---|
| `pending` | awaiting a reviewer — the only value that appears in the queue listing |
| `acknowledged` | legacy "seen" marker; retained so existing callers keep working |
| `approved` | outcome recorded, submission accepted |
| `rejected` | outcome recorded, submission declined |

State transitions:

```text
pending ──acknowledge──► acknowledged
pending ──outcome(approved)──► approved      (terminal)
pending ──outcome(rejected)──► rejected      (terminal)
acknowledged ──outcome(...)──► approved | rejected
```

Validation rules:

- An outcome may only be recorded on an entry in `pending` or `acknowledged`; re-recording on a
  terminal entry is a `409`.
- `rejected` requires a non-empty `review_note` (max 5000).
- `reviewed_by_user_id` and `reviewed_at` MUST both be set whenever `status` is terminal, and MUST
  both be null otherwise.
- An entry whose `edit_proposal_id` is non-null MUST NOT accept a direct outcome — its status is
  driven by the proposal approve/reject routes (research Decision 2). Attempting one is a `422`.

## Derived (not persisted)

| Field | Derivation | Serves |
|---|---|---|
| `is_proposal_backed` | `edit_proposal_id !== null` | UI picks diff viewer vs. context card |
| `record_available` | `citable` resolves to a live model | merged/deleted link target edge case |
| `changed_since_submission` | record's latest revision `applied_at` (or `updated_at`) `> submitted_at` | "changed since submission" marker |
| `can_record_outcome` | caller holds `ReviewType::permission()` for the entry's type **and** entry is non-terminal | FR-004, FR-008 |

## Unchanged entities referenced

- **`ArchiveItem`** — the submitted document (condition report). Its `links` (`ArchiveItemLink`,
  morphs to `Artwork`) supply the linked-record context required by FR-003.
- **`EditProposal`** — backs proposal-sourced entries; its `field_diffs` feed the existing diff viewer.
- **`MaterialSubmission`** — backs material-intake entries; already listed by `ReviewQueueItemResource`.
- **`User`** — submitter and reviewer. Eligibility is by permission only; no assignee column exists
  or is introduced (FR-008, spec Assumptions).
