# Quickstart: Validating the Admin Artwork List View

**Date**: 2026-09-22
**Feature**: `specs/001-admin-artwork-list-view/spec.md`
**Contract**: [contracts/ui-contract.md](contracts/ui-contract.md)

Guide for proving the feature works end-to-end after implementation. Implementation details belong in `tasks.md`.

## Prerequisites

- Repo dependencies installed: `composer install` in `backend/`, `npm install` in `frontend/`
- Backend API reachable (e.g., `php artisan serve` in `backend/`) with seeded artworks, or a deployed environment
- Admin user with the `artworks.manage` permission

## Automated Validation

```bash
cd frontend
npm run typecheck    # no type errors
npm run lint         # no lint errors
npm run test:run     # all specs pass, including new ArtworksRegistryPage.spec.ts
npm run build        # production build succeeds
```

Expected outcome: all four commands exit zero.

## Manual Validation Scenarios

1. **Toggle works**: Sign in as admin, open the artwork registry page (`/admin/artworks`). Confirm grid is default; click "List" — artworks become rows with thumbnail, title, artist, year, holder, status, and flags. Click "Grid" — cards return.
2. **State preserved**: With a search term and a status filter applied, switch views — the search box, filters, and page number stay as they were, and no loading spinner appears (no refetch).
3. **Persistence**: Select list view, reload the page — list view is still active. (Check DevTools → Application → Local Storage → key `admin-artworks-view` = `"list"`.)
4. **Empty state**: Apply filters matching nothing — the empty state shows identically in both views, with the "clear filters" action working.
5. **Missing data**: Confirm artworks with no thumbnail / no year / no holder render cleanly in list view.
6. **Narrow screen**: Resize to 360px (or use device emulation) — title and status badge remain visible without horizontal scrolling.
7. **Bilingual**: Switch locale between English and Arabic — toggle labels, titles, and badge text localize correctly in both layouts.

## Regression Checklist

- Pagination works in both views.
- Merge modal and "Add artwork" button remain functional in both views.
- Row order is identical between views.
