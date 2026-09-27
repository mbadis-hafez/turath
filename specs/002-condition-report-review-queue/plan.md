# Implementation Plan: Condition Report Review Queue Visibility

**Branch**: `002-condition-report-review-queue` | **Date**: 2026-09-23 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-condition-report-review-queue/spec.md`

## Summary

Archive-sourced submissions never reach a reviewer because the system has two disconnected queues:
"send to review" on an archive item writes a `review_queue_items` row with no `edit_proposal_id`,
while the reviewer's **Review queue** page reads `GET /api/v1/proposals`, which lists only
`edit_proposals`. The one endpoint that would show the row, `GET /api/v1/review-queue`, has no UI
caller.

The fix repoints the reviewer queue at `review_queue_items` — already a strict superset, since every
proposal creates a companion row — so every flow's submissions land in one list. Proposal-backed
entries keep the existing diff viewer and approve/reject routes; standalone entries get real
outcomes through a new `POST /review-queue/{item}/outcome`, backed by three new columns
(`reviewed_by_user_id`, `reviewed_at`, `review_note`) and two new statuses. A shared guard at every
creation site rejects a submission when no user holds the matching review permission, so nothing is
silently dropped. Full reasoning in [research.md](./research.md).

## Technical Context

**Language/Version**: PHP 8.3 (backend), TypeScript 6 (frontend)

**Primary Dependencies**: Laravel 13.17, spatie/laravel-permission 8.3; Vue 3.5, vue-i18n 11,
Tailwind 4, @vueuse/core 15

**Storage**: MySQL (`bidayaat`), Eloquent migrations

**Testing**: Pest 5 (backend feature tests), Vitest 5 + @vue/test-utils + jsdom (frontend)

**Target Platform**: Web application — Laravel API server + SPA admin client

**Project Type**: Web application (separate `backend/` and `frontend/`)

**Performance Goals**: Queue listing paginated at 24/page (max 100), matching the existing
`ReviewQueueIndexController`; no N+1 on `citable`, `submittedBy`, `reviewedBy`

**Constraints**: Bilingual (en/ar) UI strings mandatory; no new roles or permissions (spec
Assumptions); existing review flows must not change behaviour (SC-005); `acknowledge` endpoint must
keep working for existing callers

**Scale/Scope**: One modified table, one widened endpoint, one new endpoint, one shared submission
guard, one reworked reviewer page — roughly 6 backend files and 4 frontend files

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

`.specify/memory/constitution.md` is still the unpopulated template — every principle and section is
a placeholder, so it defines no enforceable gates. **Result: no gate violations, because no gates are
defined.** The project's de facto standards were applied instead and are treated as the gate here:

| De facto standard | How this plan complies |
|---|---|
| Invokable single-action controllers + API Resources | New outcome endpoint is one invokable controller; listing keeps `ReviewQueueItemResource` |
| Form Request / inline `validate()` for input | Outcome body validated before any write |
| Business logic in `Support/` services, not controllers | Reachability guard is one shared class used by all three creation sites |
| Pest feature tests for every endpoint change | Listed in [quickstart.md](./quickstart.md) |
| Bilingual en/ar locale parity | UI contract item 9 |
| Pint + PHPStan + ESLint + typecheck clean | Automated gates in quickstart |

*Post-Phase-1 re-check*: unchanged — the design adds no new project, no new abstraction layer, and
no dependency. The Complexity Tracking table below is therefore empty.

Recommend running `/speckit-constitution` before the next feature so this gate becomes meaningful.

## Project Structure

### Documentation (this feature)

```text
specs/002-condition-report-review-queue/
├── plan.md                        # This file
├── spec.md                        # Feature specification
├── research.md                    # Phase 0 output
├── data-model.md                  # Phase 1 output
├── quickstart.md                  # Phase 1 output
├── contracts/
│   └── review-queue-api.md        # Phase 1 output
└── checklists/
    └── requirements.md
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Enums/
│   │   └── ReviewQueueStatus.php                       # + approved, rejected
│   ├── Models/
│   │   └── ReviewQueueItem.php                         # + reviewedBy, editProposal relations
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── ReviewQueueIndexController.php          # widened listing + filters
│   │   │   ├── ReviewQueueOutcomeController.php        # NEW
│   │   │   ├── ArchiveItemEditController.php           # submitReview → reachability guard
│   │   │   └── MaterialSubmissionController.php        # reachability guard
│   │   └── Resources/
│   │       └── ReviewQueueItemResource.php             # record{}, derived flags, reviewer
│   └── Support/
│       ├── Review/ReviewerReachability.php             # NEW — shared guard
│       └── Proposals/ProposalService.php               # reachability guard on submit
├── database/migrations/
│   └── 2026_09_23_*_add_outcome_to_review_queue_items.php   # NEW
├── routes/api.php                                      # + outcome route
└── tests/Feature/
    ├── ReviewQueueVisibilityTest.php                   # NEW
    └── ReviewQueueOutcomeTest.php                      # NEW

frontend/src/
├── api/reviewQueue.ts                                  # NEW — list + recordOutcome
├── types/reviewQueue.ts                                # NEW
├── components/proposals/
│   └── ReviewQueueEntryCard.vue                        # NEW — standalone entry + outcomes
├── pages/ProposalsPage.vue                             # reads the review queue endpoint
├── pages/ProposalsPage.spec.ts                         # extended
└── i18n/locales/{en,ar}/proposals.json                 # new strings, both locales
```

**Structure Decision**: Existing two-package web-application layout — Laravel API in `backend/`,
Vue SPA in `frontend/`. No new top-level directories; the one new backend namespace is
`App\Support\Review` for the shared reachability guard, following the existing `App\Support\*`
convention.

## Complexity Tracking

No Constitution Check violations to justify — the constitution defines no gates, and the design adds
no new project, abstraction layer, or dependency.
