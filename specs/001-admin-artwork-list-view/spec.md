# Feature Specification: Admin Artwork List View

**Feature Branch**: `[001-admin-artwork-list-view]`

**Created**: 2026-09-22

**Status**: Draft

**Input**: User description: "The artwork list page admin side has only grid view it needs to have list view also"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Switch Between Grid and List Views (Priority: P1)

An admin (curator) browsing the artwork registry currently sees artworks only as a grid of image cards. The admin needs an alternative list (table/row) layout that shows more artworks per screen and displays key metadata (status, flags, year, artist, holder) in compact rows, so they can scan and manage large catalogs efficiently.

**Why this priority**: This is the core ask — the page offers only one layout today, and list view is the primary alternative layout admins expect for data-dense management pages.

**Independent Test**: Can be fully tested by opening the admin artwork registry page, switching to list view via a view-mode toggle, and verifying artworks render as rows with their key metadata visible.

**Acceptance Scenarios**:

1. **Given** the admin is on the artwork registry page, **When** the page loads, **Then** the default view is the existing grid view and a clearly visible toggle offers both "Grid" and "List" options.
2. **Given** the admin is viewing the grid, **When** they select "List", **Then** all currently loaded artworks are re-rendered as list rows without reloading the page or losing the current filter/search/page state.
3. **Given** the admin is viewing the list, **When** they select "Grid", **Then** artworks return to the grid card layout and all current filter/search/page state is preserved.
4. **Given** the admin switches views, **When** they interact with an artwork (open its detail page) and navigate back, **Then** the chosen view and scroll/filter state are still applied.

---

### User Story 2 - List View Shows Key Metadata per Row (Priority: P2)

In list view, each artwork row must surface the same core information the grid cards show — thumbnail, title (localized, with the other-language title), artist, year, holder, publication status, and data-quality flags — plus quick access to the artwork's detail/edit page, so the admin loses no information by switching layouts.

**Why this priority**: A list view that omits metadata would be useless for curation work; information parity between layouts is required for the feature to deliver value.

**Independent Test**: Can be tested by switching to list view and comparing a row's content against the same artwork's grid card, confirming no essential field is missing.

**Acceptance Scenarios**:

1. **Given** an artwork with a thumbnail, title, artist, year, holder, status, and flags, **When** it renders in list view, **Then** all of these are visible in its row.
2. **Given** an artwork missing optional data (e.g., no year, no holder, or no thumbnail), **When** it renders in list view, **Then** the row renders cleanly with graceful placeholders instead of broken layout.
3. **Given** an artwork title in Arabic and English, **When** it renders in list view, **Then** the title follows the same localization rules as the grid (current locale first, other language as secondary text).

---

### User Story 3 - View Preference Persists Across Sessions (Priority: P3)

The admin's chosen view mode is remembered, so on their next visit to the registry page the previously selected layout is shown instead of always defaulting to grid.

**Why this priority**: A quality-of-life improvement; the page remains fully functional without it, but frequent users switching to list would be annoyed to re-select it every visit.

**Independent Test**: Can be tested by selecting list view, leaving the page (or reloading), returning, and verifying list view is still active.

**Acceptance Scenarios**:

1. **Given** the admin selected list view, **When** they revisit the artwork registry page in a later session, **Then** the list view is shown by default.
2. **Given** a different admin on the same machine/browser profile, **When** they open the registry page, **Then** the view mode applies per current browser profile behavior (stored locally, not on the server).

---

### Edge Cases

- What happens when the artwork list is empty? The existing empty state must show in both views, with the "clear filters" action intact.
- What happens when an artwork has no thumbnail? Both views show a neutral placeholder; list view must not collapse row height.
- What happens on narrow (mobile) screens? The list view must remain usable — columns may wrap or hide non-critical fields, but title and status stay visible.
- What happens when many artworks carry flags? Rows must wrap flag badges without overflowing or pushing actions out of reach.
- What happens on slow networks? Switching views must not trigger a new data fetch; the already-loaded page of results is re-rendered.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The admin artwork registry page MUST provide a view-mode toggle with two options: Grid (existing layout) and List (new layout).
- **FR-002**: Selecting a view mode MUST re-render the current results in that layout without a page reload and without resetting active search terms, filters, or the current page.
- **FR-003**: The list view MUST display, for each artwork, at minimum: thumbnail (or placeholder), localized title with secondary-language title, artist name, year, holder name, publication status, and data-quality flags.
- **FR-004**: The list view MUST provide a direct link from each row to the artwork's admin detail/edit page, matching the grid behavior.
- **FR-005**: Every artwork row/card MUST link to the same detail page in both views, and row order MUST be identical between views.
- **FR-006**: The selected view mode MUST persist across sessions for the same browser profile and be restored on the next visit.
- **FR-007**: Pagination, the merge action, the add-artwork action, and all filter controls MUST remain available and functional in both views.
- **FR-008**: The empty, loading, and error states MUST render identically in both view modes.
- **FR-009**: The list view MUST remain usable at small viewport widths: title and status remain visible even if secondary fields wrap or are hidden.

### Key Entities

- **Artwork (row/card)**: Existing registry entity — thumbnail URL, localized title (ar/en), artist, year, holder, publication status (draft/published/hidden), data-quality flags (untitled, missing_dimensions, holder_missing, year_uncertain).
- **View Preference**: The admin's selected layout mode (grid/list), stored per browser profile.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admins can switch between grid and list view in a single click, with the new layout rendering in under 1 second for a full page of results.
- **SC-002**: 100% of the metadata fields visible on a grid card are also visible or accessible within one interaction in list view.
- **SC-003**: Zero regressions: all existing registry capabilities (search, filters, pagination, merge, add artwork) work identically in both view modes.
- **SC-004**: Admins who choose list view find it still selected on their next visit (100% persistence across sessions).
- **SC-005**: The list view is fully usable on viewports as narrow as 360px with no horizontal scrolling.

## Assumptions

- "Admin side artwork list page" refers to the artwork registry page in the admin area (the existing grid page), not the public artworks page.
- Grid remains the default view for first-time visitors; persistence only changes the default for returning admins.
- The view preference is stored client-side (per browser profile), not as a server-side user setting — no backend changes are required.
- No new artwork data fields are introduced; list view surfaces only fields already returned by the existing registry data source.
- Localization (Arabic/English) and the existing visual design language (colors, badges, typography) are reused for the list view.
