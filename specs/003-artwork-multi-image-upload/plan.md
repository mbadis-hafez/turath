# Implementation Plan: Multiple Image Upload with Primary Image Selection

**Branch**: `003-artwork-multi-image-upload` (spec directory; work is currently uncommitted on `main`) | **Date**: 2026-09-23 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-artwork-multi-image-upload/spec.md`

## Summary

Let registrars attach many images to an artwork in one file-chooser interaction, and make the
"primary" image an explicit, always-present, always-consistent designation rather than an
implicit "whichever was marked, else whichever was first" fallback.

Technical approach, in four parts:

1. **One batch upload request** instead of N single-file requests. The existing
   `POST /api/v1/artworks/{id}/images` endpoint grows an `images[]` form field alongside the
   current single `image` field, and returns a per-file outcome list so each file's success,
   rejection or duplicate status can be reported individually.
2. **The primary invariant moves into the model layer.** A new `ArtworkImageObserver` enforces
   "an artwork with images has exactly one primary" on create and delete, so the API, the
   merger, seeders and imports all get the same guarantee instead of each re-implementing it.
3. **Duplicate blocking is split by authority.** The browser catches the obvious
   same-file-picked-twice case instantly and cheaply; the backend is authoritative, comparing the
   SHA-256 it already computes and rejecting byte-identical images with a named error.
4. **The rename is presentation-only.** The eight `curation.artworkImages.*` translation strings
   (Arabic and English) move from "final" to "primary" wording. The stored column stays
   `is_final`, deliberately, so no migration and no merge/registry/checklist read paths are touched.

## Technical Context

**Language/Version**: PHP 8.4 (backend), TypeScript 5.x in strict mode (frontend)

**Primary Dependencies**: Laravel (API-only) with Sanctum SPA cookie auth; Vue 3 SFCs + Vite +
Pinia + vue-i18n + Tailwind CSS v4 (logical properties only, per D12)

**Storage**: MySQL 8 via Herd. Image bytes live on the private `local` disk under
`artwork-images/` and are streamed through the API — never served directly, never public by path.

**Testing**: Pest (backend feature tests), Vitest + jsdom (frontend component tests). Quality
gates: Pint, Larastan, ESLint, `npm run typecheck`. `make test` and `make lint` run both sides.

**Target Platform**: Modern evergreen browsers; bilingual RTL-first (Arabic default, English
secondary) admin screens behind the `artworks.manage` permission.

**Project Type**: Web application in a monorepo — `backend/` (Laravel API) + `frontend/` (Vue SPA).

**Performance Goals**: A 10-image attach completes in one request and one round trip. Cap of 20
images per artwork at up to 20 MB each bounds a worst-case batch; no client-side hashing of file
bytes, so queueing 20 large files stays instant.

**Constraints**:
- Per-file validation must be independent — one bad file may not discard its valid siblings (FR-004).
- Byte-identical duplicates must be blocked *and named*, never silently dropped (FR-011, and
  `docs/privacy-rules.md` rule 7, which forbids silent rejection).
- Attaching images must not change publication or rights state (FR-015; privacy rule 5,
  "nothing auto-publishes").
- Arabic and English translation keys must stay at parity — the suite asserts it.
- Tailwind logical properties only; `ml-`/`mr-`/`left-`/`right-`/`text-left`/`text-right` are forbidden.
- No thumbnailing or resizing: originals are served as-is (unchanged by this feature).

**Scale/Scope**: Admin-only surface, a handful of concurrent registrars, ~950 artworks currently
seeded. Two screens affected (artwork create, artwork curation) via one shared component; one API
endpoint extended; one observer added; 8 translation strings × 2 languages reworded.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**⚠️ `.specify/memory/constitution.md` is unpopulated.** Every section of it is still the
scaffold's placeholder tokens (`[PRINCIPLE_1_NAME]`, `[GOVERNANCE_RULES]`, …), so **no
project-specific gates can be derived from it.** This plan does not fabricate a pass against
principles that have not been written. Running `/speckit-constitution` is recommended, and was
started in an earlier session but interrupted before writing.

In its absence, this feature is gated against the governance the project *has* written down, which
is substantive and load-bearing:

| Source | Rule | Applies here? | Verdict |
|---|---|---|---|
| `docs/privacy-rules.md` #5 | Every file carries `rights_status`; default is restrictive; **nothing auto-publishes** | Yes — new images carry per-image rights | **PASS** — FR-015 forbids any publication/rights change on attach; new images default to `unknown` rights and stay non-public |
| `docs/privacy-rules.md` #6 | Access levels enforced in policies and resources, not just stored | Yes — images stream through the API | **PASS** — unchanged; existing `ArtworkImageController::show` rights/publication check is not touched |
| `docs/privacy-rules.md` #7 | **Immutable originals**; SHA-256 per file; duplicates "detected by checksum and reported, never silently rejected" | Yes — directly | **PASS** — R2 makes the backend authoritative on SHA-256 and requires a named error; originals are never modified |
| `docs/privacy-rules.md` #9 | Every create/update/delete on a tracked model is audited field-level | Yes — `ArtworkImage` uses `LogsChanges` | **PASS** — observer-driven primary flips are ordinary model writes and stay audited; `path`/`sha256` remain excluded from diffs |
| `docs/decisions.md` D12 | Tailwind logical properties only | Yes — UI work | **PASS** — enforced by lint; no physical-direction utilities introduced |
| `docs/decisions.md` D16 | Statuses are string columns + PHP enums, not MySQL ENUM | No new status columns | **N/A** |
| Repo convention (D26, D30) | Cross-cutting model invariants live in observers | Yes | **PASS** — R3 follows it (`ArtworkImageObserver`, registered via `static::observe()` in `booted()`) |

**Gate result: PASS**, with one flagged process gap (the empty constitution) rather than a silent
one. No violations to justify, so Complexity Tracking below is empty.

**Post-Phase-1 re-check**: still PASS. The design adds one observer, one optional request field,
one response field and eight reworded strings. It introduces no new storage, no new permission, no
new public read path, and no change to publication or rights semantics.

## Project Structure

### Documentation (this feature)

```text
specs/003-artwork-multi-image-upload/
├── plan.md              # This file
├── research.md          # Phase 0 output — the five design decisions and what was rejected
├── data-model.md        # Phase 1 output — ArtworkImage invariants and state transitions
├── quickstart.md        # Phase 1 output — how to validate this end to end
├── contracts/
│   ├── artwork-images-api.md   # HTTP contract for the images endpoints
│   └── images-panel-ui.md      # Component contract for the shared images panel
├── checklists/
│   └── requirements.md  # Spec quality checklist (16/16 passing)
└── tasks.md             # Phase 2 output — NOT created by /speckit-plan
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Models/
│   │   ├── ArtworkImage.php                     # register observer in booted()
│   │   └── Observers/
│   │       └── ArtworkImageObserver.php         # NEW — the "exactly one primary" invariant
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   └── ArtworkImageController.php       # batch store(), duplicate rejection, per-file results
│   │   └── Requests/Artwork/
│   │       └── StoreArtworkImagesRequest.php    # NEW — per-file rules, cap, single+batch shapes
│   └── Support/Curation/
│       └── ArtworkMerger.php                    # stop clearing every primary on merge
└── tests/Feature/
    ├── ArtworkCurationTest.php                  # extend: existing one-primary assertions
    └── ArtworkImageUploadTest.php               # NEW — batch, duplicate, cap, invariant, merge

frontend/
├── src/
│   ├── api/artworkCuration.ts                   # uploadArtworkImages(): many files, per-file results
│   ├── components/curation/
│   │   └── ArtworkImagesPanel.vue               # multiple input, primary badge/action, drop zone (P3)
│   ├── pages/admin/
│   │   ├── ArtworkCreatePage.vue                # queue many; drop the Math.max(id) guess
│   │   └── ArtworkCurationPage.vue              # batch upload path
│   ├── types/artworkCuration.ts                 # per-file upload result type
│   └── i18n/locales/{ar,en}/curation.json       # artworkImages.*: "final" → "primary" wording
└── src/pages/admin/
    ├── ArtworkCreatePage.spec.ts                # extend: multi-select, duplicate, primary
    └── ArtworkCurationPage.spec.ts              # extend: batch upload path
```

**Structure Decision**: The existing monorepo split is used unchanged — `backend/` for the Laravel
API and `frontend/` for the Vue SPA. No new top-level directories. The only new backend files are
one observer and one Form Request, both landing in the directories their siblings already occupy
(`app/Models/Observers/`, `app/Http/Requests/Artwork/`). On the frontend, all UI change
concentrates in the one shared `ArtworkImagesPanel.vue` component, which is why the create and
curation screens both inherit the feature without duplicated work.

## Complexity Tracking

> No Constitution Check violations, so nothing to justify here.

The one judgement call worth recording is a deliberate *reduction* in scope: the stored column
keeps its `is_final` name while the UI says "primary". Renaming the column would have pulled in a
migration plus the merger, admin registry, curation checklist and completeness read paths — a
materially larger change than the feature itself, for no user-visible gain, since the field name is
internal. This was confirmed with the user (Q2 → option A) and is recorded in the spec's
Assumptions.
