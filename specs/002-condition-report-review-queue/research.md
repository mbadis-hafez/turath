# Phase 0 Research: Condition Report Review Queue Visibility

**Feature**: 002-condition-report-review-queue | **Date**: 2026-09-23

## Root Cause Investigation

The reported bug is not a permission or filtering defect — it is two disconnected queues.

| Submission flow | What it writes | What the reviewer UI reads |
|---|---|---|
| Suggest-edit / curation draft | `EditProposal` **+** a `ReviewQueueItem` with `edit_proposal_id` set (`ProposalService::submit`, `EditorialDraftService`) | `GET /api/v1/proposals` → `ProposalsPage.vue` ("Review queue") |
| Archive item "send to review" | `ReviewQueueItem` only, `edit_proposal_id` **null** (`ArchiveItemEditController::submitReview`) | *(nothing)* |
| Material intake | `ReviewQueueItem` only (`MaterialSubmissionController`) | `MaterialSubmissionsPage.vue` (separate screen) |

`GET /api/v1/review-queue` exists (`ReviewQueueIndexController`) and correctly filters by
`ReviewType::permission()`, but no page in `frontend/src/` calls it — only the dashboard
`CompletionSidebar` touches review-queue data. So Fatma's archive-sourced condition report
lands in `review_queue_items` and is never rendered anywhere Samar looks.

A second, compounding gap: `ReviewQueueStatus` only models `pending` / `acknowledged`, and
`review_queue_items` carries no reviewer identity, timestamp, or note. Even if the entry were
displayed, FR-004 (same outcome actions as other pending items) and FR-005 (store reviewer +
timestamp) could not be satisfied.

## Decision 1 — `review_queue_items` becomes the single queue source of truth

**Decision**: Point the reviewer queue at `review_queue_items` (via a widened
`GET /api/v1/review-queue`) rather than at `edit_proposals`. Proposal-backed entries embed their
proposal so the existing diff viewer keeps working; standalone entries render a context card.

**Rationale**: `review_queue_items` is already a strict superset — every `EditProposal` creates a
companion row, and the archive and material flows create rows without one. Reading the superset
table is the smallest change that satisfies FR-002's "single review queue ... regardless of which
screen or flow produced the submission", and it needs no backfill or dual-write.

**Alternatives considered**:
- *Make archive submissions create an `EditProposal`*: rejected. An `EditProposal` requires a
  non-empty `field_diffs` and a rationale; `ProposalService::submit` explicitly throws when nothing
  differs. A "please review this document" submission proposes no field change, so it would need a
  sentinel empty diff — corrupting the proposal/revision model that `approve()` applies verbatim.
- *Add a second reviewer page for `/review-queue`*: rejected. Directly violates FR-002 and
  reproduces the reported failure mode for the next flow that is added.
- *Union the two tables in the API response*: rejected as redundant given the superset property,
  and it would double-list every proposal.

## Decision 2 — Outcomes on standalone entries, delegation for proposal-backed ones

**Decision**: Extend `ReviewQueueStatus` with `approved` and `rejected`, add
`reviewed_by_user_id` / `reviewed_at` / `review_note` to `review_queue_items`, and add
`POST /api/v1/review-queue/{item}/outcome`. For an entry with an `edit_proposal_id`, the outcome
endpoint is not used — the UI keeps calling the existing proposal approve/reject routes, which
already cascade the queue row's status (`ProposalService` line 198, `EditorialDraftService` line 480).

**Rationale**: FR-004 forbids limiting archive-sourced submissions to acknowledge-only, and FR-005
requires storing reviewer identity and timestamp. Keeping proposal outcomes on the proposal routes
avoids two code paths that can apply field changes, which is the one thing that must stay
single-sourced. Existing `acknowledge` stays as-is so nothing that calls it breaks.

**Alternatives considered**: reusing `acknowledged` as a generic "resolved" — rejected because
FR-005 requires a distinguishable recorded outcome, and `CompletenessCalculator` already reads
`status = 'pending'` semantics.

## Decision 3 — Reviewer-reachability check at submission time

**Decision**: Before creating a queue entry, check whether any user holds the entry's
`ReviewType::permission()`; when none does, return a `422` naming the review type, and do not
create the entry. Applied at every creation site (archive submit, material intake, proposal
submit) through one shared guard.

**Rationale**: FR-006 / SC-004 require the submitter be warned at submission time rather than the
item being silently dropped. Spatie's permission package can answer this with a single
`Permission::findByName(...)->users()`-style existence check plus the roles that hold it.

**Alternatives considered**: warning asynchronously after submission — rejected, SC-004 demands
zero silent drops at the moment of the action.

## Decision 4 — Edge-case handling

- **Link target merged/deleted**: `citable` morphs to null. The resource already tolerates this via
  `CitableTypeResolver`; the entry stays listed and the UI shows an explicit "record unavailable"
  state instead of hiding the row (spec Edge Cases, FR-008).
- **Duplicate submission**: `ArchiveItemEditController::submitReview` already guards with a pending
  existence check. Keep that guard and surface the existing pending entry rather than creating a
  second one.
- **Permission change while pending**: visibility is computed per-request from the caller's
  permissions with no assignee column, so remaining eligible reviewers are unaffected and a reviewer
  who lost access simply stops matching the `whereIn` — no error path. Already satisfied; covered by
  a regression test only.
- **Record edited after submission**: compare the record's latest revision/`updated_at` against
  `submitted_at` and render a "changed since submission" marker. No schema change needed.

## Decision 5 — Stack conventions (no NEEDS CLARIFICATION remain)

Backend is Laravel 13 / PHP 8.3 with Pest 5, spatie/laravel-permission 8, MySQL. Frontend is Vue
3.5 + TypeScript 6 + Tailwind 4 + vue-i18n 11, tested with Vitest 5. All new API work follows the
existing invokable-controller + API-Resource pattern seen in `ReviewQueueIndexController`; all new
UI strings are added to both `en/` and `ar/` locale files per the bilingual convention.
