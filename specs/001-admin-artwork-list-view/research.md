# Phase 0 Research: Admin Artwork List View

**Date**: 2026-09-22
**Feature**: `specs/001-admin-artwork-list-view/spec.md`

No [NEEDS CLARIFICATION] markers remained in Technical Context — all decisions had clear defaults grounded in the existing codebase. Findings below consolidate the choices.

## Decision 1: View preference storage — client-side `localStorage` via `useLocalStorage`

**Rationale**: The spec (Assumptions) fixes this as client-side per browser profile; no backend change. `@vueuse/core` is already a dependency of the frontend and exports `useLocalStorage`, which gives reactive, SSR-safe-ish persistence with automatic JSON serialization and write-throttle. This matches how the spec's SC-004 is verified (same browser profile, next session).

**Alternatives considered**:
- Server-side user preference column — rejected by spec assumption; adds backend scope for zero user-visible gain over localStorage.
- URL query param (`?view=list`) — would persist per link rather than per user and would interact with the existing URL-driven filter state in `useAdminArtworks`; also leaks layout state into shared links. Rejected.

## Decision 2: Component decomposition — extract presentational card/row/toggle components

**Rationale**: `ArtworksRegistryPage.vue` (153 lines) currently mixes the grid card markup with filters, merge modal, and query wiring. Adding a second layout inline would push it past comfortable size and duplicate badge/status styling logic. Extracting `ArtworkGridCard.vue`, `ArtworkListRow.vue`, and `ViewModeToggle.vue` keeps the page as wiring + layout switch only, and each presentational component becomes independently testable. This follows the project's existing pattern of presentational components under `components/curation/`.

**Alternatives considered**:
- Keep both layouts inline in the page behind `v-if` — fewer files, but duplicates the status/flag badge maps (`STATUS_CLASS`, `FLAG_CLASS`) and grows an already busy page. Rejected.
- Single `ArtworkItem.vue` with a `layout` prop — forces one component to serve two materially different layouts (media-first vs. metadata-first), hurting template clarity. Rejected.

## Decision 3: List row layout — responsive table-like rows using Tailwind grid, not a `<table>`

**Rationale**: Tailwind 4 is the styling system. A CSS-grid row (`grid` with column template on `sm:` and up, wrapping meta below on small screens) satisfies FR-009 (title + status visible at 360px, secondary fields wrap) without `<table>` overflow problems. Row order and content come from the same `items` array, guaranteeing grid/list parity (FR-005).

**Alternatives considered**:
- `<table>` — rigid columns break down at 360px and fight the wrap/hide behavior FR-009 requires. Rejected.

## Decision 4: No refetch on view switch

**Rationale**: `useAdminArtworks` loads `items` reactively and re-fetches only when `route.query` changes. View mode is held in a `useLocalStorage` ref entirely outside that composable, so toggling cannot trigger `watch(() => route.query)`. Rendering both layouts from the same `items` array satisfies the "no network request" edge case and SC-003.

## Decision 5: i18n — new keys under `curation.artworkRegistry.view` in en + ar

**Rationale**: The page is fully localized via vue-i18n; all existing registry strings live under `curation.artworkRegistry` in `frontend/src/i18n/locales/{en,ar}/`. New keys (`view.grid`, `view.list`) plus any list-specific labels follow the same convention. The toggle needs `aria-pressed` labels in both locales.

## Decision 6: Testing — new `ArtworksRegistryPage.spec.ts` following existing conventions

**Rationale**: Existing specs (e.g., `frontend/src/composables/useAdminArtworks.spec.ts`) are colocated `*.spec.ts` files run by Vitest with @vue/test-utils + jsdom. The registry page has no page-level spec today; this feature adds one covering: default grid render, toggle to list, metadata parity, state preservation on toggle, and localStorage persistence. No new test infrastructure needed.

## Resolved unknowns summary

| Unknown | Resolution |
|--------|------------|
| Where to store view preference | `useLocalStorage("admin-artworks-view", "grid")` |
| Component split | `ArtworkGridCard`, `ArtworkListRow`, `ViewModeToggle` in `components/curation/` |
| List layout technique | Responsive Tailwind grid rows |
| Refetch behavior | None — same reactive `items` |
| i18n location | `curation.artworkRegistry.view.*` (en/ar) |
| Test approach | Colocated Vitest page spec |
