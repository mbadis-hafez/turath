# Implementation Plan: Creation requires review, same as editing

**Branch**: `005-record-creation-review` | **Date**: 2026-09-23 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/005-record-creation-review/spec.md`

## Summary

Creating a new artist, artwork, event or archive item is currently a direct, unreviewed write — the
creating editor can immediately publish/verify it with no reviewer involved, unlike every subsequent edit
to that same record, which is staged as an `EditProposal` and requires review. This feature closes that
gap by giving every record a `creation_approved_at` timestamp (nullable, set only on reviewer approval)
and auto-creating a creation-flagged `EditProposal` (`is_creation = true`) alongside every new record.
Finalization (publish/verify) is refused until it's approved; the creator can keep editing the live
record directly in the meantime (there's nothing yet to protect from an in-progress edit, unlike an
established record); a reviewer sees the record's content directly (no diff — nothing to diff against)
and approves or requests changes through the same review-queue mechanics already used for edits. All four
record types are delivered as independent slices of the same underlying mechanism (see
[research.md](./research.md) R1–R6).

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 11+), TypeScript (Vue 3 + Vite)

**Primary Dependencies**: Existing `EditProposal`/`ReviewQueueItem`/`ProposalController` stack (no new
package); Spatie Activitylog for the approval audit entry; Pest (backend tests); Vitest + `@vue/test-utils`
(frontend tests)

**Storage**: MySQL — one new nullable timestamp column per record table (`artists`, `artworks`, `events`,
`archive_items`), one new boolean column on `edit_proposals`

**Testing**: `php artisan test` (Pest, `RefreshDatabase`); `npx vitest run`

**Target Platform**: Existing web app (Laravel API + Vue SPA), no new platform surface

**Project Type**: Web application (existing `backend/` + `frontend/` split)

**Performance Goals**: No new perf-sensitive path — one extra row insert at creation time, one extra
column check at finalization time and in picker queries (indexed)

**Constraints**: Must not change behavior for any record created before this feature ships (backfilled
`creation_approved_at`) or for editing an already-approved record (SC-004) — this is an additive gate, not
a rework of the existing edit-review pipeline

**Scale/Scope**: Four record types, each touching: one creation endpoint, one-to-two finalization
endpoints, one linked-entity picker (where one exists), one curation/edit page, the shared review queue
screen

## Constitution Check

*`.specify/memory/constitution.md` is unpopulated (placeholder text only) — as with every prior feature
this session, `docs/decisions.md` and `docs/privacy-rules.md` serve as the project's actual governance for
this check.*

- **Privacy rule 5** ("nothing auto-publishes") and **rule 9** ("every create/update/delete on a tracked
  model is audited") are the operative rules here. This feature is a direct enforcement mechanism for rule
  5 at the one point it wasn't actually enforced (creation), and rule 9 is satisfied by reusing
  `EditProposal`'s existing activity-log integration for the approval entry — no new audit mechanism
  needed.
- **D67** ("contributor edits become `edit_proposals` rows and never touch the live record") does not
  strictly apply here — R1/R3 deliberately let the record's *own creator* edit it directly pre-approval,
  which is a real, reasoned departure from D67's letter (documented in research.md R3) rather than an
  oversight: D67 protects an *established* record's stability from an in-progress edit; there is no
  established state to protect before creation is approved.
- **Gate**: PASS. No violation requiring `Complexity Tracking` justification — this reuses existing
  infrastructure (`EditProposal`, `ReviewQueueItem`, `PublishGate` and its per-type equivalents,
  `DraftStatusBanner`) rather than introducing new architecture.

## Project Structure

### Documentation (this feature)

```text
specs/005-record-creation-review/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md         # Phase 1 output
├── quickstart.md         # Phase 1 output
├── contracts/
│   ├── creation-review-api.md
│   └── creation-review-ui.md
└── tasks.md              # Phase 2 output (/speckit-tasks — not created by /speckit-plan)
```

### Source Code (repository root)

```text
backend/
├── database/migrations/         # + creation_approved_at (4 tables), + edit_proposals.is_creation
├── app/Models/                  # Artist, Artwork, Event, ArchiveItem: expose creation_approved_at;
│                                 # EditProposal: expose is_creation
├── app/Support/Proposals/       # EditorialDraftService (or a small sibling) gets the "create the
│                                 # is_creation proposal alongside the record" + "submit has no
│                                 # nothing-differs check for is_creation" + "approve is_creation = just
│                                 # stamp the timestamp" logic
├── app/Support/Completeness/    # PublishGate (artist/artwork) gains the creation-approved check;
│                                 # Event::publish() and ArchiveItemPublishController's own gate get the
│                                 # same check added at their own enforcement points (data-model.md table)
├── app/Http/Controllers/Api/V1/ # Artist/Artwork/Event/ArchiveItem *CreateController-equivalents (the
│                                 # existing store actions) trigger the is_creation proposal; the picker
│                                 # search endpoints (e.g. artist search) gain the creation_approved_at
│                                 # scope
└── tests/Feature/               # RecordCreationReviewTest.php (new) — one file covering all four
                                  # types' create → review → approve/request-changes cycles, plus the
                                  # finalize-refusal and picker-exclusion checks

frontend/
├── src/pages/admin/             # ArtistCreatePage.vue, ArtworkCreatePage.vue, EventEditPage.vue,
│                                 # ArchiveEditPage.vue (create branches) — redirect target now shows a
│                                 # creation-review banner; each type's curation/edit page shows it for
│                                 # creation_approved_at === null and disables its finalize button with a
│                                 # reason
├── src/components/curation/     # DraftStatusBanner.vue (or a thin variant) reused for the creation-review
│                                 # states, per contracts/creation-review-ui.md
├── src/pages/admin/ProposalsPage.vue  # renders a pending is_creation item without a diff view
├── src/components/curation/EntityPicker.vue-backed searches  # exclude unapproved records
└── src/pages/admin/*.spec.ts    # updated/new specs per page touched
```

**Structure Decision**: No new top-level structure — this slots entirely into the existing
`backend/app/Support/Proposals` + `backend/app/Support/Completeness` + per-type controllers/pages split
already used by the edit-review pipeline this feature extends. Artists are built first as the reference
slice (matches the reported bug and the spec's User Story priority order); artworks, events and archive
items follow the same shape against their own endpoints (`/speckit-tasks` sequences the four).

## Complexity Tracking

*No violations to justify — see Constitution Check.*
