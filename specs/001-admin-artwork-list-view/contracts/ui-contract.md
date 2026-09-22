# UI Contract: Admin Artwork Registry View Modes

**Date**: 2026-09-22
**Feature**: `specs/001-admin-artwork-list-view/spec.md`
**Scope**: `frontend/src/pages/admin/ArtworksRegistryPage.vue` and its new child components

This contract defines the stable, test-facing surface of the view-mode feature. Test IDs and behaviors below are the acceptance interface — implementation may change internals but must keep this contract green.

## Elements & Test IDs

| Element | Test ID | Contract |
|---------|---------|----------|
| View toggle, grid option | `view-toggle-grid` | `button` with `aria-pressed="true"` when grid active |
| View toggle, list option | `view-toggle-list` | `button` with `aria-pressed="true"` when list active |
| Artwork item (either layout) | `artwork-card` | One per rendered artwork, same order as API `items`, in both views |
| Artwork link (either layout) | — | Every `artwork-card` contains a link to `admin.artworks.show` with the artwork `id` |

## Behavior Contract

1. **Default render**: On first visit (no stored preference), grid layout is active and `view-toggle-grid` has `aria-pressed="true"`.
2. **Switch to list**: Clicking `view-toggle-list` re-renders all current `artwork-card` items as list rows; no network request is made (assert API call count unchanged).
3. **Switch to grid**: Clicking `view-toggle-grid` restores the card layout; filters, search term, and current page are unchanged (assert route query unchanged).
4. **Metadata parity**: For a given artwork, both layouts expose title (localized + secondary language), artist, year, holder, status label, and all flags.
5. **Empty/loading/error states**: Render identically regardless of active view mode.
6. **Persistence**: After selecting list view, a fresh page mount (new component instance, same localStorage) renders in list view.
7. **Narrow viewport**: At 360px width, each `artwork-card` shows its title and status badge without horizontal page scrolling.

## i18n Keys (new)

| Key | en | ar |
|-----|----|----|
| `curation.artworkRegistry.view.grid` | Grid | شبكة |
| `curation.artworkRegistry.view.list` | List | قائمة |

Both keys are required in `frontend/src/i18n/locales/en/curation.json` and `frontend/src/i18n/locales/ar/curation.json` and are used as the toggle's accessible labels.

## Local Storage Contract

| Key | Values | Default |
|-----|--------|---------|
| `admin-artworks-view` | `"grid"`, `"list"` | `"grid"` |

Written on every toggle change; read on page mount; unknown values fall back to `"grid"`.
