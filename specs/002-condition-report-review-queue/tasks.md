---

description: "Task list for 002-condition-report-review-queue"
---

# Tasks: Condition Report Review Queue Visibility

**Input**: Design documents from `/specs/002-condition-report-review-queue/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/review-queue-api.md](./contracts/review-queue-api.md), [quickstart.md](./quickstart.md)

**Tests**: Included — [quickstart.md](./quickstart.md) enumerates required Pest and Vitest coverage as an automated gate.

**Organization**: Tasks are grouped by user story so each can be implemented, tested, and delivered independently.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2)
- Exact file paths are included in every task

## Path Conventions

Web app: Laravel API in `backend/`, Vue SPA in `frontend/src/` (per plan.md Structure Decision).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm the two test suites run green before any change, so later failures are attributable.

- [ ] T001 Verify the backend suite passes unchanged from `backend/`: `./vendor/bin/pest` exits zero
- [ ] T002 [P] Verify the frontend suite passes unchanged from `frontend/`: `npm run test:run` exits zero

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, enum, model, serializer, and client plumbing that BOTH user stories read.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T003 Create migration `backend/database/migrations/2026_09_23_000001_add_outcome_to_review_queue_items.php` adding to `review_queue_items`: `reviewed_by_user_id` (foreignId → `users`, nullable, null on delete), `reviewed_at` (timestamp, nullable), `review_note` (text, nullable), plus index `(status, submitted_at)`; `down()` drops the three columns and the index (data-model.md "Modified: review_queue_items")
- [ ] T004 [P] Add cases `Approved = 'approved'` and `Rejected = 'rejected'` to `backend/app/Enums/ReviewQueueStatus.php`, keeping `Pending` and `Acknowledged` unchanged; add an `isTerminal(): bool` helper returning true for `approved` and `rejected` (data-model.md enum table)
- [ ] T005 Add to `backend/app/Models/ReviewQueueItem.php`: a `reviewedBy(): BelongsTo` relation on `reviewed_by_user_id`, an `editProposal(): BelongsTo` relation on `edit_proposal_id`, and `'reviewed_at' => 'datetime'` in `casts()`
- [ ] T006 Widen `backend/app/Http/Resources/ReviewQueueItemResource.php` to the entry shape in contracts/review-queue-api.md: keep `id`, `review_type`, `status`, `submitted_at`, `submitted_by`, `note`, `title`; add `record` (`{type, id, title{ar,en}, available}` where `available` is false when `citable` no longer resolves), `is_proposal_backed` (`edit_proposal_id !== null`), `edit_proposal_id`, `changed_since_submission` (record's latest revision `applied_at`, else `updated_at`, `> submitted_at`), `can_record_outcome` (caller holds `ReviewType::permission()` for the entry AND status is non-terminal), `reviewed_by` (`{id, name}` or null), `reviewed_at`, `review_note` (data-model.md "Derived (not persisted)")
- [ ] T007 [P] Create `frontend/src/types/reviewQueue.ts` with a `ReviewQueueEntry` interface mirroring the T006 response shape and a `ReviewOutcome = "approved" | "rejected"` union
- [ ] T008 [P] Create `frontend/src/api/reviewQueue.ts` exporting `listReviewQueue(params, signal)` for `GET /api/v1/review-queue` (params `review_type`, `status`, `record_type`, `mine`, `page`, `per_page`; blank values stripped, as `frontend/src/api/proposals.ts` does) and `recordOutcome(id, { outcome, review_note })` for `POST /api/v1/review-queue/{id}/outcome` (depends on T007)

**Checkpoint**: Schema, serializer, and client are in place — both stories can now proceed.

---

## Phase 3: User Story 1 — Reviewer Sees and Can Process Archive-Sourced Submissions (Priority: P1) 🎯 MVP

**Goal**: An archive-sourced condition report sent to review appears in the reviewer's single Review queue with full context, and the reviewer can record a real approve/reject outcome on it.

**Independent Test**: Import an archive document, link it to an artwork, send it to review; log in as a user holding only `review_queue.archivist_review`; the item is listed with document title, submitter, date, linked artwork, and review type, and an outcome can be recorded that removes it from the pending list.

### Tests for User Story 1 ⚠️

> Write these first and confirm they fail before implementing T014–T019.

- [ ] T009 [P] [US1] Create `backend/tests/Feature/ReviewQueueVisibilityTest.php`: archive `submit-review` creates exactly one pending `review_queue_items` row, and `GET /api/v1/review-queue` returns it for a user holding only `review_queue.archivist_review` with `title`, `submitted_by`, `submitted_at`, `record.type`/`record.id`/`record.title`, and `review_type` populated (FR-001, FR-003, SC-001)
- [ ] T010 [P] [US1] Extend `ReviewQueueVisibilityTest.php`: a second `submit-review` on the same archive item creates no second entry; a user holding no review permission gets `200` with empty `data`; an unauthenticated request gets `403` (spec Edge Cases, contract visibility rule)
- [ ] T011 [P] [US1] Extend `ReviewQueueVisibilityTest.php`: with one item pending, revoking `review_queue.archivist_review` from one reviewer leaves the item visible to every other holder and returns a clean empty listing (no error) for the reviewer who lost it (FR-008)
- [ ] T012 [P] [US1] Create `backend/tests/Feature/ReviewQueueOutcomeTest.php`: `POST /review-queue/{id}/outcome` with `approved` returns `200`, sets `status`, `reviewed_by_user_id`, and `reviewed_at`, and removes the entry from the pending listing; `rejected` without a `review_note` returns `422` while `rejected` with one is stored; repeating an outcome on a terminal entry returns `409`; an entry with a non-null `edit_proposal_id` returns `422`; a caller lacking `review_queue.{review_type}` returns `403` (FR-004, FR-005, data-model validation rules)
- [ ] T013 [P] [US1] Extend `frontend/src/pages/ProposalsPage.spec.ts`: the page lists entries from `listReviewQueue`; a standalone entry renders its outcome actions, a proposal-backed entry renders `ProposalDiffViewer`, an entry with `record.available: false` renders the "linked record unavailable" note instead of a link, and an entry with `changed_since_submission: true` renders the changed marker (ui-contract items 1–3, 6, 7)

### Implementation for User Story 1

- [ ] T014 [US1] Widen `backend/app/Http/Controllers/Api/V1/ReviewQueueIndexController.php` per contracts/review-queue-api.md: validate and apply `review_type`, `status` (default `pending`), `record_type` (citable segment via `CitableTypeResolver`), `mine` and `page`; keep `per_page` default 24, max 100; return an entry when the caller holds `review_queue.{review_type}` **or** `mine=1` and the caller is the submitter; eager-load `submittedBy`, `reviewedBy`, `citable`, `editProposal` to avoid N+1 (depends on T006)
- [ ] T015 [US1] Create `backend/app/Http/Controllers/Api/V1/ReviewQueueOutcomeController.php` as an invokable controller: validate `outcome` in `approved|rejected` and `review_note` (`required` with min 3, max 5000, when `outcome` is `rejected`); `403` unless the caller holds the entry's `ReviewType::permission()`; `409` when the entry's status is already terminal; `422` when `edit_proposal_id` is non-null; on success set `status`, `reviewed_by_user_id`, `reviewed_at` together and return the entry through `ReviewQueueItemResource` (depends on T004, T005)
- [ ] T016 [US1] Register `Route::post('review-queue/{reviewQueueItem}/outcome', ReviewQueueOutcomeController::class)` in `backend/routes/api.php` beside the existing `review-queue` routes, leaving the `acknowledge` route untouched (depends on T015)
- [ ] T017 [P] [US1] Create `frontend/src/components/proposals/ReviewQueueEntryCard.vue` for standalone entries: shows document title, submitter, submission date, linked record link, and localized review type; Approve and Reject actions calling `recordOutcome`, with Reject requiring a note before it submits; renders "linked record unavailable" when `record.available` is false and a "changed since submission" marker when `changed_since_submission` is true (depends on T008)
- [ ] T018 [US1] Rework `frontend/src/pages/ProposalsPage.vue` to load from `listReviewQueue` with `status=pending` instead of `listProposals` for reviewers; render `ReviewQueueEntryCard` when `is_proposal_backed` is false and keep the existing `ProposalDiffViewer` path plus its approve/reject calls when true; after an outcome is recorded, drop the row from the list and decrement the header count without a reload (depends on T014, T017; ui-contract items 1–5)
- [ ] T019 [P] [US1] Add the new queue strings to `frontend/src/i18n/locales/en/proposals.json` and `frontend/src/i18n/locales/ar/proposals.json` — outcome actions, reject-note label and validation message, "linked record unavailable", "changed since submission", and the submitted-document/linked-record labels — with identical key sets in both files (ui-contract item 9)

**Checkpoint**: The reported bug is fixed — the reproduction in quickstart.md scenario 1 and the outcome flow in scenario 2 both pass.

---

## Phase 4: User Story 2 — Submitter Can Track the Review State of Their Submission (Priority: P2)

**Goal**: The submitter sees a pending indicator for their submission, sees the recorded outcome and reviewer afterwards, and is warned at submission time when no reviewer can receive it.

**Independent Test**: Send a linked document to review and confirm the record screen shows "pending review", then shows the outcome and reviewer name once processed; with the review permission held by nobody, the send is refused immediately and no entry is created.

### Tests for User Story 2 ⚠️

- [ ] T020 [P] [US2] Create `backend/tests/Feature/ReviewerReachabilityTest.php`: when no user holds `review_queue.archivist_review`, `POST /archive-items/{id}/submit-review` returns `422` with a `review_type` error naming the review type and creates no `review_queue_items` row; the same guard fires for material intake and proposal submission; when a holder exists, all three behave exactly as before (FR-006, SC-004, SC-005)
- [ ] T021 [P] [US2] Extend `backend/tests/Feature/ReviewQueueVisibilityTest.php`: `GET /review-queue?mine=1` returns the caller's own submissions — including one already processed — with `reviewed_by`, `reviewed_at`, and `status` populated, for a caller holding no review permission (FR-006, contract visibility rule)
- [ ] T022 [P] [US2] Extend `frontend/src/pages/admin/ArchiveEditPage.spec.ts`: the page shows a pending-review indicator while an entry is pending and shows the recorded outcome with the reviewer's name once terminal

### Implementation for User Story 2

- [ ] T023 [US2] Create `backend/app/Support/Review/ReviewerReachability.php` with a method that, given a `ReviewType`, reports whether any user holds `ReviewType::permission()` (directly or through a role) and a guard that throws a `ValidationException` on the `review_type` key with the message "No reviewer can receive this submission." when none does (research Decision 3)
- [ ] T024 [US2] Call the T023 guard before the entry is created in `ArchiveItemEditController::submitReview` (`backend/app/Http/Controllers/Api/V1/ArchiveItemEditController.php`), preserving the existing pending-duplicate guard so an already-pending entry is reused rather than duplicated (depends on T023)
- [ ] T025 [P] [US2] Call the T023 guard before `ReviewQueueItem::create` in `backend/app/Http/Controllers/Api/V1/MaterialSubmissionController.php` (depends on T023)
- [ ] T026 [P] [US2] Call the T023 guard inside the transaction in `ProposalService::submit` (`backend/app/Support/Proposals/ProposalService.php`) before the proposal and queue row are written, so a refused submission leaves nothing behind (depends on T023)
- [ ] T027 [US2] Extend the `under_review` field in `ArchiveItemEditController::bundle` (`backend/app/Http/Controllers/Api/V1/ArchiveItemEditController.php`) into a review-state object carrying `status`, `submitted_at`, `reviewed_by` (`{id, name}` or null), `reviewed_at`, and `review_note` from the item's latest `review_queue_items` row (FR-006)
- [ ] T028 [US2] Render the review state from T027 on `frontend/src/pages/admin/ArchiveEditPage.vue` — a pending-review indicator, replaced by the outcome and reviewer name once terminal — and add its strings to `frontend/src/i18n/locales/en/*.json` and `.../ar/*.json` in both locales (depends on T027)
- [ ] T029 [US2] Give the non-reviewer branch of `frontend/src/pages/ProposalsPage.vue` a read-only "my submissions" list from `listReviewQueue` with `mine=1`, showing pending or outcome state and no outcome actions (depends on T018; ui-contract item 8)

**Checkpoint**: Both user stories work independently — quickstart.md scenarios 1–5 pass.

---

## Phase 5: Polish & Cross-Cutting Concerns

- [ ] T030 [P] Confirm every new string key exists in both `frontend/src/i18n/locales/en/` and `frontend/src/i18n/locales/ar/` with no raw keys rendered, per quickstart.md manual scenario 8
- [ ] T031 [P] Verify the edge-case rows end to end: merge the linked artwork and confirm the entry still lists as unavailable, and edit the archive item while pending and confirm the changed marker appears (quickstart.md manual scenarios 6–7)
- [ ] T032 Run the quickstart.md regression checklist: curation drafts and field proposals still reach reviewers and still apply field changes on approve, request-changes still works, material submissions still appear on their screen, the dashboard completion sidebar is unchanged, and `POST /review-queue/{id}/acknowledge` still returns `200` (SC-005)
- [ ] T033 Run the quickstart.md automated gates — `./vendor/bin/pest` and `./vendor/bin/pint --test` in `backend/`; `npm run typecheck`, `npm run lint`, `npm run test:run`, and `npm run build` in `frontend/` — all exiting zero
- [ ] T034 Perform quickstart.md manual scenarios 1–8 and record the results

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS both user stories
- **User Story 1 (Phase 3)**: Depends on Phase 2 only
- **User Story 2 (Phase 4)**: Depends on Phase 2; only T029 depends on US1 (it reuses the page reworked in T018)
- **Polish (Phase 5)**: Depends on both stories

### User Story Dependencies

- **US1 (P1)**: Independent once Phase 2 is done — delivers the reported fix on its own
- **US2 (P2)**: Independently testable once Phase 2 is done. Its backend work (T020–T028) shares no file with US1; T029 is the single cross-story task and is sequenced last within the story

### Within Each User Story

- Tests are written first and must fail before the matching implementation task
- Migration and enum before model, model before resource, resource before controllers
- Backend endpoints before the frontend that calls them
- Story complete before moving to the next priority

### Parallel Opportunities

- T001 and T002 (different suites)
- T004, T007, T008 in Phase 2 (different files; T008 after T007)
- All US1 tests T009–T013 together
- T017 and T019 alongside the US1 backend work (different files)
- All US2 tests T020–T022 together
- T025 and T026 together once T023 lands (different files)
- T030 and T031 in Phase 5
- With two developers, US1 and US2 backend work can run in parallel after Phase 2

---

## Parallel Example: User Story 1

```bash
# Launch all US1 tests together:
Task: "Create backend/tests/Feature/ReviewQueueVisibilityTest.php (T009)"
Task: "Extend ReviewQueueVisibilityTest.php with duplicate/no-permission cases (T010)"
Task: "Extend ReviewQueueVisibilityTest.php with the permission-revocation case (T011)"
Task: "Create backend/tests/Feature/ReviewQueueOutcomeTest.php (T012)"
Task: "Extend frontend/src/pages/ProposalsPage.spec.ts (T013)"

# Then the independent US1 pieces:
Task: "Create frontend/src/components/proposals/ReviewQueueEntryCard.vue (T017)"
Task: "Add queue strings to en/proposals.json and ar/proposals.json (T019)"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1: Setup (T001–T002)
2. Phase 2: Foundational (T003–T008) — CRITICAL, blocks both stories
3. Phase 3: User Story 1 (T009–T019)
4. **STOP and VALIDATE**: run quickstart.md scenarios 1–3; the reported bug (SC-003) is fixed
5. Ship — reviewers stop losing archive-sourced submissions

### Incremental Delivery

1. Setup + Foundational → schema and plumbing ready
2. US1 → validate → ship (MVP: submissions reach reviewers and can be processed)
3. US2 → validate → ship (submitter feedback and no-reviewer warning)
4. Polish → regression checklist and full quickstart pass

### Parallel Team Strategy

1. Both developers complete Phase 2 together
2. Developer A takes US1 (T009–T019); Developer B takes US2 backend (T020–T028)
3. Developer B picks up T029 after T018 lands
4. Both converge on Phase 5

---

## Notes

- [P] tasks = different files, no dependencies
- Each user story is independently completable and testable
- Verify each test fails before implementing against it
- Commit after each task or logical group
- Stop at any checkpoint to validate a story on its own
