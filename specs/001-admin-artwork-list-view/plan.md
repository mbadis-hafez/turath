# Implementation Plan: Admin Artwork List View

**Branch**: `001-admin-artwork-list-view` | **Date**: 2026-09-22 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-admin-artwork-list-view/spec.md`

## Summary

The admin artwork registry page (`frontend/src/pages/admin/ArtworksRegistryPage.vue`) renders artworks only as a grid of image cards. This feature adds a list (row) layout with full metadata parity, a grid/list view toggle, and a client-side persistent view preference. The work is frontend-only: the existing `AdminArtworkRow` API payload already contains every field the list view needs, so no backend or contract changes are required.

Technical approach: extract the existing grid card markup into a presentational `ArtworkGridCard.vue`, add a presentational `ArtworkListRow.vue`, add a `ViewModeToggle.vue` segmented control, and persist the selected mode via `@vueuse/core`'s `useLocalStorage`. Filter/page state stays in the URL via the existing `useAdminArtworks` composable and is untouched by view switching.

## Technical Context

**Language/Version**: TypeScript ~6.0, Vue 3.5 (Composition API, `<script setup lang="ts">`)

**Primary Dependencies**: Vue Router 5, vue-i18n 11, Tailwind CSS 4, `@vueuse/core` 15 (provides `useLocalStorage`), Vitest 5 + @vue/test-utils + jsdom

**Storage**: Browser `localStorage` via `useLocalStorage` for the view-mode preference; no server-side storage

**Testing**: Vitest (`frontend/src/**/*.spec.ts` colocated); run with `npm run test:run` in `frontend/`

**Target Platform**: Modern browsers (desktop + mobile), Arabic/English bilingual (RTL aware)

**Project Type**: web-application (frontend)

**Performance Goals**: View switch re-renders the already-loaded page of results with no network request; rendered in under 1 second (SC-001)

**Constraints**: Must remain usable at 360px viewport with no horizontal scrolling (SC-005); no new data fetch on view switch; existing URL-driven filter/pagination behavior must not regress

**Scale/Scope**: Single admin page; ~3 new components + 1 page modification + i18n keys in `curation.artworkRegistry` (en/ar) + 1 new page spec file

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

`.specify/memory/constitution.md` is an unmodified template — no active principles or gates are defined.

**Result**: PASS (no gates to evaluate; nothing to justify)

## Project Structure

### Documentation (this feature)

```text
specs/001-admin-artwork-list-view/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── ui-contract.md   # Phase 1 output
├── checklists/
│   └── requirements.md  # From /speckit-specify
└── tasks.md             # Phase 2 output (NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
frontend/src/
├── pages/admin/
│   ├── ArtworksRegistryPage.vue      # MODIFIED: add toggle + conditional grid/list rendering
│   └── ArtworksRegistryPage.spec.ts  # NEW: page-level view-mode tests
├── components/registry/              # NEW directory (or components/curation — see Structure Decision)
│   ├── ViewModeToggle.vue            # NEW: grid/list segmented control
│   ├── ArtworkGridCard.vue           # NEW: extracted grid card (moved out of page)
│   └── ArtworkListRow.vue            # NEW: list row layout
├── composables/
│   └── useAdminArtworks.ts           # UNCHANGED (URL state; no view concern)
└── i18n/locales/{en,ar}/curation.json  # MODIFIED: view toggle + list labels
```

**Structure Decision**: Option 2 (web application, frontend-only). Presentational artwork row/card components live alongside the other admin-curation components. Existing artwork grid markup lives inline in `ArtworksRegistryPage.vue` today alongside merge-modal and filter logic in `components/curation/`; the extracted card/row/toggle components follow that convention and go in `components/curation/` (no new directory) unless the implementation phase finds a stronger existing grouping. No backend changes.

## Complexity Tracking

> Constitution Check passed with no violations — section intentionally empty.
