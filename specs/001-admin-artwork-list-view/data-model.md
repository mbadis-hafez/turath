# Phase 1 Data Model: Admin Artwork List View

**Date**: 2026-09-22
**Feature**: `specs/001-admin-artwork-list-view/spec.md`

## Entities

No new data entities are introduced. The feature reuses the existing `AdminArtworkRow` payload (defined in `frontend/src/types/artworkCuration.ts`) and adds one client-side UI state value.

### Artwork (existing, reused)

| Field | Type | Notes for list view |
|-------|------|---------------------|
| `id` | `number` | Row link target: `admin.artworks.show` route |
| `title` | `Localized` (`{ ar, en }`) | Current-locale primary text; other locale as secondary line (same rule as grid) |
| `artist` | `{ id, name: Localized } \| null` | Rendered when present; row must not collapse when absent |
| `holder` | `{ id, name: Localized } \| null` | Rendered when present |
| `year` | `string \| null` | Rendered when present |
| `thumbnail_url` | `string \| null` | Small fixed-size thumbnail in row; neutral placeholder when null |
| `publication_status` | `ArtworkStatus` (`draft` / `published` / `hidden`) | Status badge, same color classes as grid |
| `flags` | `ArtworkFlag[]` (`untitled`, `missing_dimensions`, `holder_missing`, `year_uncertain`) | Flag badges, same color classes as grid; wrap within row |

Fields already on the payload but not required by the spec for either layout (e.g., `completeness_pct`, `severity`, `pipeline`, `merged_into_id`) remain out of scope for both views.

### View Preference (new, client-side only)

| Attribute | Value |
|-----------|-------|
| Name | `admin-artworks-view` |
| Type | `"grid" \| "list"` |
| Storage | Browser `localStorage` via `useLocalStorage`; default `"grid"` |
| Scope | Per browser profile; never sent to the server |
| Validation | Unknown/corrupted values fall back to `"grid"` |

## Validation Rules

- **VR-01**: A row must render cleanly when any optional field (`artist`, `holder`, `year`, `thumbnail_url`) is null (spec edge case).
- **VR-02**: `publication_status` and each `flags` entry must map to an existing badge style; unknown values render no badge rather than crashing (defensive, matching current grid behavior).
- **VR-03**: Row order in list view must equal `items` array order, identical to grid (FR-005).
- **VR-04**: At viewports < 640px, the row must keep title and status badge visible; other fields may wrap or hide (FR-009).

## State Transitions

- View mode: `grid ↔ list`, triggered only by the toggle; persisted on every change; restored on page load.
- View switching must not alter: current route query (search, filters, page), `items`/`meta`/`loading`/`error` state, or trigger any API call.

## Relationships

- View Preference depends on nothing; the page depends on both Artwork (via `useAdminArtworks`) and View Preference (via `useLocalStorage`).
