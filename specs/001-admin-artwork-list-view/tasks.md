---

description: "Task list for Admin Artwork List View"
---

# Tasks: Admin Artwork List View

**Input**: Design documents from `/specs/001-admin-artwork-list-view/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Test tasks included — the plan mandates a new colocated Vitest spec (`ArtworksRegistryPage.spec.ts`) and quickstart.md validates with `npm run test:run`.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- Web app: `backend/`, `frontend/` at repository root; all frontend paths below are relative to `frontend/`
- `ArtworksRegistryPage.vue` currently lives at `frontend/src/pages/admin/ArtworksRegistryPage.vue`
- Presentational curation components follow the existing convention in `frontend/src/components/curation/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Verify frontend dependencies and test tooling needed before any story work

- [X] T001 Verify `@vueuse/core` is installed in `frontend/package.json` and provides `useLocalStorage` (install if missing)
- [X] T002 [P] Verify Vitest + @vue/test-utils + jsdom run correctly in `frontend/` (`npm run test:run` passes on the existing `src/composables/useAdminArtworks.spec.ts` with no changes)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Component extraction and i18n groundwork that blocks all user stories

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T003 Extract the existing artwork grid card markup from `frontend/src/pages/admin/ArtworksRegistryPage.vue` into a presentational component `frontend/src/components/curation/ArtworkGridCard.vue` (props: single `AdminArtworkRow` item from `frontend/src/types/artworkCuration.ts`; keeps `data-testid="artwork-card"` and the link to the `admin.artworks.show` route with the artwork `id`; move the grid's status/flag badge class maps along with it)
- [X] T004 Update `frontend/src/pages/admin/ArtworksRegistryPage.vue` to render `ArtworkGridCard` for each item instead of the inline card markup (zero visual/behavioral change; page compiles and grid renders as before)
- [X] T005 [P] Add i18n keys `curation.artworkRegistry.view.grid` (en: "Grid", ar: "شبكة") and `curation.artworkRegistry.view.list` (en: "List", ar: "قائمة") to `frontend/src/i18n/locales/en/curation.json` and `frontend/src/i18n/locales/ar/curation.json` per contracts/ui-contract.md

**Checkpoint**: Foundation ready — card extracted, page renders unchanged, toggle labels available in both locales

---

## Phase 3: User Story 1 - Switch Between Grid and List Views (Priority: P1) 🎯 MVP

**Goal**: A visible grid/list toggle on the admin artwork registry page that re-renders already-loaded results in the selected layout without reload, refetch, or losing search/filter/page state

**Independent Test**: Open the artwork registry page, switch to list via the toggle, verify artworks render as rows (list layout may be minimal in this phase), switch back — route query and state unchanged (contracts/ui-contract.md behavior 1–3)

### Tests for User Story 1

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [X] T006 [P] [US1] Create `frontend/src/pages/admin/ArtworksRegistryPage.spec.ts`: test that the page defaults to grid with `view-toggle-grid` having `aria-pressed="true"` (ui-contract behavior 1)
- [X] T007 [P] [US1] Extend `ArtworksRegistryPage.spec.ts`: test that clicking `view-toggle-list` switches the rendered layout and makes no API request (assert API call count unchanged; ui-contract behavior 2)

### Implementation for User Story 1

- [X] T008 [US1] Create `frontend/src/components/curation/ViewModeToggle.vue`: two-option segmented control with `data-testid="view-toggle-grid"` and `data-testid="view-toggle-list"`, each a `button` with `aria-pressed` reflecting the active mode, labels via `curation.artworkRegistry.view.grid` / `.list` (depends on T005)
- [X] T009 [US1] Add view-mode state and conditional rendering to `frontend/src/pages/admin/ArtworksRegistryPage.vue`: render `ViewModeToggle` in the toolbar; when list mode is active, render the same `items` array through the list layout (built in US2) instead of `ArtworkGridCard`; view switching must not touch route query, `useAdminArtworks` state, or trigger any fetch (depends on T004, T008)
- [X] T010 [US1] Create a minimal responsive list-row shell `frontend/src/components/curation/ArtworkListRow.vue` (props: single `AdminArtworkRow`; `data-testid="artwork-card"`; link to `admin.artworks.show` with the artwork `id`; title + status only in this phase — full metadata lands in US2) so US1 is independently testable before US2
- [X] T011 [US1] Ensure empty, loading, and error states render identically regardless of active view mode in `frontend/src/pages/admin/ArtworksRegistryPage.vue` (state blocks stay outside the view-mode conditional; FR-008)

**Checkpoint**: Toggle switches layouts with zero refetch and preserved state; US1 spec acceptance scenarios 1–3 pass

---

## Phase 4: User Story 2 - List View Shows Key Metadata per Row (Priority: P2)

**Goal**: Each list row surfaces full metadata parity with the grid card: thumbnail (or placeholder), localized title + secondary-language title, artist, year, holder, publication status, and all data-quality flags

**Independent Test**: Switch to list view and compare a row against the same artwork's grid card — no essential field missing; artwork with null `artist` / `holder` / `year` / `thumbnail_url` renders cleanly (spec acceptance scenarios 1–3, VR-01)

### Tests for User Story 2

- [X] T012 [P] [US2] Extend `ArtworksRegistryPage.spec.ts`: metadata parity test — for one artwork, assert both layouts expose title (localized + secondary language), artist, year, holder, status label, and all flags (ui-contract behavior 4)
- [X] T013 [P] [US2] Extend `ArtworksRegistryPage.spec.ts`: null-field test — artwork with `artist`, `holder`, `year`, `thumbnail_url` all `null` renders without crash and without collapsed row height
- [X] T014 [P] [US2] Extend `ArtworksRegistryPage.spec.ts`: narrow-viewport test — at 360px width, each `artwork-card` in list view shows its title and status badge (ui-contract behavior 7; FR-009 / VR-04)

### Implementation for User Story 2

- [X] T015 [US2] Implement full metadata row layout in `frontend/src/components/curation/ArtworkListRow.vue`: fixed-size thumbnail with neutral placeholder when `thumbnail_url` is null (row height must not collapse); localized title (current locale first) with other-language title as secondary text — same rule as grid; artist, year, holder rendered when present; status badge and flag badges reusing the exact same color classes as `ArtworkGridCard.vue`; flag badges wrap within the row without overflow; whole row links to `admin.artworks.show` (spec FR-003/FR-004/FR-005)
- [X] T016 [US2] Apply responsive Tailwind grid layout in `frontend/src/components/curation/ArtworkListRow.vue`: at `sm:` and up, a column-template row; below 640px, secondary fields wrap or hide while title and status badge stay visible with no horizontal scrolling (research Decision 3; SC-005)
- [X] T017 [US2] Add defensive badge rendering in `frontend/src/components/curation/ArtworkListRow.vue`: unknown `publication_status` or `flags` values render no badge rather than crashing (VR-02)

**Checkpoint**: Row order identical between views; full metadata parity verified; null-data and 360px cases clean

---

## Phase 5: User Story 3 - View Preference Persists Across Sessions (Priority: P3)

**Goal**: The chosen view mode is written to `localStorage` on every toggle change and restored on the next page mount, with corrupted values falling back to grid

**Independent Test**: Select list view, reload the page, verify list view is still active; inspect DevTools → Application → Local Storage → `admin-artworks-view` = `"list"` (spec acceptance scenario 1, quickstart scenario 3)

### Tests for User Story 3

- [X] T018 [US3] Extend `ArtworksRegistryPage.spec.ts`: persistence test — after selecting list view, a fresh component mount (same localStorage) renders in list view (ui-contract behavior 6)
- [X] T019 [US3] Extend `ArtworksRegistryPage.spec.ts`: corrupted-value test — a stored value other than `"grid"`/`"list"` falls back to grid on mount (data-model.md View Preference validation rule)

### Implementation for User Story 3

- [X] T020 [US3] Back the view-mode state in `frontend/src/pages/admin/ArtworksRegistryPage.vue` with `useLocalStorage<'grid' | 'list'>('admin-artworks-view', 'grid')` from `@vueuse/core`, normalizing any non-`grid`/`list` stored value to `'grid'` (data-model.md View Preference entity; local-storage contract in ui-contract.md)

**Checkpoint**: Persistence works across reloads and sessions; corrupted values degrade to grid

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Full validation suite and regression checks across all stories

- [X] T021 Verify no regressions: pagination, merge action, "Add artwork" action, and all filter/search controls remain functional and unchanged in both views in `frontend/src/pages/admin/ArtworksRegistryPage.vue` (FR-007, SC-003)
- [X] T022 [P] Run quickstart.md automated validation in `frontend/`: `npm run typecheck`, `npm run lint`, `npm run test:run`, `npm run build` — all four exit zero
- [X] T023 Perform quickstart.md manual validation scenarios 1–7 (toggle, state preserved, persistence, empty state, missing data, narrow screen, bilingual) and regression checklist

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup — blocks all user stories (T003/T004 same file, sequential; T005 parallel)
- **User Stories (Phase 3–5)**: All depend on Foundational; US2 and US3 depend on US1 (they extend the page/row built in US1)
- **Polish (Phase 6)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational — no dependencies on other stories; delivers MVP
- **User Story 2 (P2)**: Depends on US1 (extends `ArtworkListRow.vue` created in T010)
- **User Story 3 (P3)**: Depends on US1 (wraps the view-mode ref created in T009); small enough to land with US1 if desired

### Within Each User Story

- Tests MUST be written and FAIL before implementation
- Components before page wiring
- Story complete before moving to next priority

### Parallel Opportunities

- T001 / T002 run in parallel
- T005 runs in parallel with T003–T004
- T006 / T007 (and T012–T014, T018–T019) can be written in parallel
- No other parallelism: the page and row components are shared files

---

## Parallel Example: User Story 1

```bash
Task: "Create ArtworksRegistryPage.spec.ts default-grid test"
Task: "Create ArtworksRegistryPage.spec.ts no-refetch toggle test"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: toggle works, no refetch, state preserved (quickstart scenario 1–2)

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. US1 → validate toggle (MVP!)
3. US2 → validate metadata parity, null data, 360px
4. US3 → validate persistence across reloads
5. Polish → full quickstart suite

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- All tasks are frontend-only; no backend or contract changes per plan.md
- Verify tests fail before implementing
- Commit after each task or logical group
