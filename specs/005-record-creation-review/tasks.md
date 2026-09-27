# Tasks: Creation requires review, same as editing

**Input**: Design documents from `/specs/005-record-creation-review/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/)

**Tests**: included — this repo's convention (see feature 003/004) is Pest + Vitest coverage per phase.

**Delivery order**: artists first (Phases 3–5, the reported bug, full MVP), then the same mechanism applied
to artworks, events and archive items in sequence (Phases 6–8) — per spec.md FR-009 and the
"independently testable slice" framing.

## Phase 1: Setup

- [X] T001 [P] Create migration `backend/database/migrations/2026_09_24_000001_add_creation_approved_at.php`: nullable `creation_approved_at` timestamp on `artists`, `artworks`, `events`, `archive_items`, each indexed; backfill every existing row to `now()` in the same migration (data-model.md — nothing that already exists needs review)
- [X] T002 [P] Create migration `backend/database/migrations/2026_09_24_000002_add_is_creation_to_edit_proposals.php`: `is_creation` boolean, default `false`, on `edit_proposals`
- [X] T003 Run `php artisan migrate` against the dev DB and confirm both migrations apply cleanly and are reversible (`migrate:rollback` then re-`migrate`). Fixed one real bug found here: the down() migration's `dropIndex()` call was passed an already-composed index name inside an array, which Laravel treats as a *column list* and re-derives a (wrong, doubled) default name from — fixed by passing the column name and letting Laravel compute the standard index name itself

---

## Phase 2: Foundational (blocking prerequisites for every user story and every record type)

**⚠️ CRITICAL**: No user-story work below can begin until this phase is complete.

- [X] T004 Add `creation_approved_at` to the `@property` docblocks and `casts()` (datetime) of `backend/app/Models/Artist.php`, `Artwork.php`, `Event.php`, `ArchiveItem.php`. Only `Artist.php` needed the explicit `@property` docblock (Larastan didn't resolve the new column via `casts()` alone, unlike its other plain columns — empirically the docblock was needed regardless of `parseModelCastsMethod`); Artwork/Event/ArchiveItem get theirs in their own phases below
- [X] T005 Add `is_creation` to `backend/app/Models/EditProposal.php`'s `casts()` (boolean)
- [X] T006 Create `backend/app/Support/Proposals/CreationReviewService.php` — implemented as specified, with one addition: `review_type` is actually derived in `startFor()`, not left null until submit, because `edit_proposals.review_type` is a NOT NULL column and a creation item's field set (unlike an edit draft's partial payload) is fully known at creation time, so there's no reason to defer it
- [X] T007 In `ProposalController.php::approve()`, branches on `$proposal->is_creation` exactly as specified
- [X] T008 Create `backend/app/Support/Completeness/CreationReviewGate.php` exactly as specified, using `$record->getAttribute('creation_approved_at')` rather than magic-property access so the gate can stay typed as generic `Model` without a Larastan property error (avoided a `HasCreationReview` marker-interface approach that didn't resolve cleanly through the `Model&Interface` intersection type)
- [X] T009 [P] Added `is_creation` to `Proposal` in `frontend/src/types/proposal.ts`, and `creation_approved_at` + `creation_review` (object: proposal_id/status/review_note/created_by_user_id — added beyond the original task scope, since the curation page needs to know *whose* draft it is to distinguish creator vs. non-creator, per US3) to `ArtistCuration`
- [X] T010 [P] Added `isCreation` prop to `DraftStatusBanner.vue`, reusing its existing `data-state` values (`draft`/`pending`/`changes_requested`/`blocked`) rather than inventing new ones — simpler than the originally-sketched new `data-state` values, and the existing visual states already fit
- [X] T011 Added `backend/tests/Feature/CreationReviewFoundationTest.php`, 6 tests, all passing

**Checkpoint**: The mechanism exists and is tested in isolation; no record type's user-facing flow uses it yet.

---

## Phase 3: User Story 1 — An editor creates a new artist and sends it for review (Priority: P1) 🎯 MVP

**Goal**: The reported bug — creating an artist no longer bypasses review, and "send for review" works.

**Independent Test**: Create a new artist as an editor; confirm Verify is refused until reviewed; submit; confirm it reaches the review queue.

### Tests for User Story 1

- [X] T012 [P] [US1] `backend/tests/Feature/RecordCreationReviewTest.php` (new): asserts `POST /api/v1/artists` creates a `creation_approved_at: null` artist plus an open `is_creation` `EditProposal` in `draft` status
- [X] T013 [P] [US1] Same file: asserts submitting the creation item flips it to `pending`, creates a `ReviewQueueItem`, and requires no prior edit — the exact reported bug, now passing
- [X] T014 [P] [US1] Same file: asserts verify and publish both refuse with `completeness.creation_review` while unapproved, for both an editor and a superadmin

### Implementation for User Story 1

- [X] T015 [US1] `ArtistStoreController.php` calls `CreationReviewService::startFor()` in the same transaction. **Real gap found and fixed beyond the task's original scope**: the create endpoint also accepted `publication_status: published` directly in the payload, bypassing review entirely in a single call — forced to always `draft` at creation regardless of what's submitted (confirmed with the user before making this change, since it required updating ~1 existing test's expectations)
- [X] T016 [US1] Added `POST records/{type}/{id}/creation/submit` as `EditorialDraftController::submitCreation()`, reusing its existing `resolve()` helper
- [X] T017 [US1] `ArtistVerifyController.php` gated as specified
- [X] T018 [US1] `ArtistUpdateController.php` gated as specified
- [X] T019 [US1] Added to `ArtistResource.php`, gated behind `artists.manage` (same visibility rule as the existing `verified_by` field — internal workflow detail, not for public API consumers)
- [X] T020 [US1] Added as `submitCreationReview(type, id)` in `frontend/src/api/editorial.ts` (not `artistCuration.ts` — it's generic across record types, alongside `submitDraft`)
- [X] T021 [US1] No change needed to `ArtistCreatePage.vue` — confirmed
- [X] T022 [US1] Implemented in `ArtistCurationPage.vue`. **Also found and fixed a real design gap**: `save()`'s `draftMode` branch would have routed a pre-approval edit through the section-diff pipeline (creating a *second*, ordinary `is_creation=false` proposal on top of the still-open creation item) — fixed so `draftMode` only applies once `creation_approved_at` is set; pre-approval, the creator's Save goes through the same direct-write path the create form itself uses (R3). Also disabled the Save button entirely for a non-creator while unapproved
- [X] T023 [P] [US1] Added to `draft.json` (not `curation.json` — matches where the sibling `draft.*` strings already live), both locales
- [X] T024 [P] [US1] Added 4 new tests to `ArtistCurationPage.spec.ts` (banner + disabled Verify, zero-edit submit, non-creator blocked state — the last one is US3's T033/T035, done here since it fell out naturally); all pass

**Checkpoint**: US1 independently shippable — the reported bug is fixed for artists; a reviewer step exists to advance to, built next.

---

## Phase 4: User Story 2 — A reviewer approves or requests changes to a brand-new artist (Priority: P1)

**Goal**: The submitted creation actually reaches a decision.

**Independent Test**: As a reviewer, open a pending artist creation, see its content directly (no diff), approve it; confirm Verify becomes reachable afterward under the normal completeness rules.

### Tests for User Story 2

- [X] T025 [P] [US2] `RecordCreationReviewTest.php`: passes
- [X] T026 [P] [US2] Same file: passes, including a real resubmit-after-changes-requested cycle
- [X] T027 [P] [US2] Same file: passes, both actions refused for the creator

### Implementation for User Story 2

- [X] T028 [US2] Implemented in `ProposalDiffViewer.vue` (not `ProposalsPage.vue` directly — that's where the per-row detail actually renders): for `is_creation`, shows an explanatory notice plus a link to the record's own curation/edit page, instead of attempting to render a diff against a fabricated empty baseline (research.md R2). `ProposalsPage.vue`'s row label also changed from "N fields" to "New record" for a creation row
- [X] T029 [US2] Confirmed unmodified — `approve`/`reject`/`request-changes` and the own-proposal guard already work for `is_creation` rows with no code change, since they're driven by `EditProposal`/`ReviewQueueItem` fields that exist identically on both kinds. One dedicated test added (`ProposalDiffViewer.spec.ts`) for the no-diff rendering itself

**Checkpoint**: US1 + US2 — the full artist creation-review loop works end to end.

---

## Phase 5: User Story 3 — Nobody outside the editor and reviewers can see/act on an unapproved artist (Priority: P2)

**Goal**: Close the loophole, not just the happy path.

**Independent Test**: An unapproved artist doesn't appear in the artist picker; a different editor opening its curation page sees a pending-review state, not a plain editable record.

### Tests for User Story 3

- [X] T030 [P] [US3] `RecordCreationReviewTest.php`: passes against the real `linkable_only` scoping (see T032 — the picker and the registry turned out to share one endpoint, `GET admin/artists`, so this needed a query-flag distinction rather than two separate endpoints)
- [X] T031 [P] [US3] Same file: passes — the registry (no flag) still lists the unapproved artist with `creation_approved_at: null` visible

### Implementation for User Story 3

- [X] T032 [US3] Found: the artist-linking picker (`searchArtistOptions` in `ArtworkPickers.ts`, used by `ArtworkFormSections.vue` and `SubmissionDetail.vue`) and the staff registry (`ArtistsRegistryPage.vue`) both call the same `listAdminArtists()` → `GET admin/artists`. Added a `linkable_only` query flag to `AdminArtistIndexController.php` (excludes `creation_approved_at IS NULL` when set) and passed it only from `searchArtistOptions()`, leaving the registry's own listing untouched
- [X] T033 [US3] Implemented — non-creator sees the existing `blocked` banner state and the Save button is disabled (bundled into T022's `draftLocked` change)
- [X] T034 [US3] Added to `ArtistsRegistryPage.vue`, next to the verified-status badge
- [X] T035 [P] [US3] `ArtistCurationPage.spec.ts` covers the non-creator blocked state (added under T024). New `ArtistsRegistryPage.spec.ts` (this page had no test file at all before — added a minimal, focused suite covering only what this feature touches, 2 tests) covers the badge

**Checkpoint**: Artist slice (US1–US3) fully complete — this is the reference implementation the other three types replicate.

---

## Phase 6: Extend to artworks (US1–US3, same mechanism)

- [ ] T036 [P] `backend/tests/Feature/RecordCreationReviewTest.php`: repeat T012–T014, T025–T027, T030–T031's assertions for `Artwork`
- [ ] T037 In `backend/app/Http/Controllers/Api/V1/ArtworkStoreController.php`, call `CreationReviewService::startFor()`
- [ ] T038 Add `POST records/artworks/{artwork}/creation/submit` (same shape as T016)
- [ ] T039 In `backend/app/Http/Controllers/Api/V1/ArtworkUpdateController.php`, call `CreationReviewGate::assertApproved()` before `PublishGate::assertPublishable()`
- [ ] T040 Add `creation_approved_at` to `backend/app/Http/Resources/ArtworkResource.php`
- [ ] T041 [P] `frontend/src/api/artworkCuration.ts`: `submitArtworkCreation()`
- [ ] T042 `frontend/src/pages/admin/ArtworkCurationPage.vue`: creation-review banner + disabled-publish-with-reason + non-creator blocked state (mirrors T022/T033)
- [ ] T043 Scope the artwork search/picker (if one exists — confirm during implementation whether artworks are ever picked the way artists are) to exclude unapproved records
- [ ] T044 [P] `frontend/src/pages/admin/ArtworksRegistryPage.vue`: pending-review badge
- [ ] T045 [P] i18n + spec updates mirroring T023/T024/T035, for artwork pages

**Checkpoint**: Artwork slice complete, independently shippable.

---

## Phase 7: Extend to events (US1–US3, same mechanism)

- [ ] T046 [P] `RecordCreationReviewTest.php`: repeat the assertion set for `Event`
- [ ] T047 In `EventController::store()`, call `CreationReviewService::startFor()`
- [ ] T048 Add `POST records/events/{event}/creation/submit`
- [ ] T049 In `EventController::publish()`, call `CreationReviewGate::assertApproved()` before its existing inline completeness check (data-model.md notes this check isn't currently routed through the shared `PublishGate` class — leave that as-is, just add the new gate ahead of it)
- [ ] T050 Add `creation_approved_at` to the event resource/bundle shape
- [ ] T051 [P] `frontend/src/api/*` equivalent submit call for events
- [ ] T052 `frontend/src/pages/admin/EventEditPage.vue`: creation-review banner (in its existing `isNew`-aware structure) + disabled-publish-with-reason + non-creator blocked state
- [ ] T053 [P] `frontend/src/pages/admin/EventsRegistryPage.vue`: pending-review badge
- [ ] T054 [P] i18n + spec updates for event pages

**Checkpoint**: Event slice complete, independently shippable.

---

## Phase 8: Extend to archive items (US1–US3, same mechanism)

- [ ] T055 [P] `RecordCreationReviewTest.php`: repeat the assertion set for `ArchiveItem`
- [ ] T056 In `backend/app/Http/Controllers/Api/V1/ArchiveItemStoreController.php`, call `CreationReviewService::startFor()`
- [ ] T057 Add `POST records/archive-items/{archiveItem}/creation/submit`
- [ ] T058 In `backend/app/Http/Controllers/Api/V1/ArchiveItemPublishController.php`, call `CreationReviewGate::assertApproved()` before its own local `assertPublishable()` (data-model.md — this is its own method, not the shared `PublishGate` class, despite the similar name)
- [ ] T059 Add `creation_approved_at` to `backend/app/Http/Resources/ArchiveItemResource.php`
- [ ] T060 [P] `frontend/src/api/*` equivalent submit call for archive items
- [ ] T061 `frontend/src/pages/admin/ArchiveEditPage.vue`: creation-review banner (in its existing `isNew`-aware structure) + disabled-publish-with-reason + non-creator blocked state
- [ ] T062 [P] `frontend/src/pages/admin/ArchiveRegistryPage.vue`: pending-review badge
- [ ] T063 [P] i18n + spec updates for archive item pages

**Checkpoint**: All four record types complete.

---

## Phase 9: Polish & Cross-Cutting Concerns

- [ ] T064 Run every scenario in [quickstart.md](./quickstart.md) against `make dev`, using an editor and a reviewer account, for all four record types
- [ ] T065 Confirm SC-004 (regression check): editing an already-existing, already-approved record of any type behaves identically to before this feature, via the full existing test suite
- [ ] T066 Run `make lint` (Pint, Larastan, ESLint, `npm run typecheck`) and fix every finding this feature introduces
- [ ] T067 Run `make test` and confirm the whole suite is green against the Phase 1 baseline
- [ ] T068 Append a decision entry to `docs/decisions.md` recording: the `creation_approved_at` + `is_creation` mechanism and why a nullable column beats a shadow table (R1); why the creator edits directly pre-approval rather than through the section-diff pipeline, and the documented departure from D67 this represents (R3); why no diff view is built for creation review (R2); the per-type table of exactly which endpoint enforces what (data-model.md), since it is **not** uniform across the four types

---

## Dependencies & Execution Order

```text
Phase 1 (Setup)
   └─> Phase 2 (Foundational — CreationReviewService, CreationReviewGate, ProposalController branch)
          └─> Phase 3 (US1, P1 — artist creation + submit + finalize-refusal) 🎯 MVP
                 └─> Phase 4 (US2, P1 — reviewer approve/request-changes for artists)
                        └─> Phase 5 (US3, P2 — artist visibility/picker scoping)
                               ├─> Phase 6 (artworks, same shape)
                               ├─> Phase 7 (events, same shape)
                               └─> Phase 8 (archive items, same shape)
                                      └─> Phase 9 (Polish)
```

Phases 6–8 depend on Phase 2 (the shared mechanism) and benefit from Phase 3–5 existing as a working
reference implementation, but are not blocked on each other — they can proceed in any order, or in
parallel, once the artist slice has proven the mechanism out.

## Implementation Strategy

### MVP First (Phases 1–5, artists only)

This fixes the actual reported bug and proves the mechanism end-to-end for one record type. Stop and
validate here before extending to the other three — cheaper to catch a design problem against one type
than after replicating it three more times.

### Incremental delivery

Phases 6, 7, 8 each replicate the same ~10-task shape against a different type's own endpoints, gates and
pages (data-model.md's per-type table — the mechanism is uniform, the enforcement points are not). Each
is independently shippable once done.
