---

description: "Task list for Multiple Image Upload with Primary Image Selection"
---

# Tasks: Multiple Image Upload with Primary Image Selection

**Input**: Design documents from `/specs/003-artwork-multi-image-upload/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/)

**Tests**: Test tasks ARE included. Not because the spec asked in those words, but because
[plan.md](./plan.md) names the test files as part of the feature's source structure,
[contracts/images-panel-ui.md](./contracts/images-panel-ui.md) specifies a test surface, and the
repo gates every push on `make test` (baseline: 315 Pest tests green). Tests precede the
implementation they cover within each phase.

**Organization**: Grouped by user story. US1 and US2 are genuinely independent — either can ship
alone and deliver value.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on incomplete work)
- **[Story]**: US1 / US2 / US3 / US4, mapping to the spec's user stories
- Every task names its exact file path

## Path Conventions

Monorepo web app per plan.md: `backend/` (Laravel API), `frontend/` (Vue SPA). All paths below are
repo-relative.

## No migration required

`artwork_images` already has every column and both needed indexes — `(artwork_id, is_final)` and
`(sha256)`. This feature changes *invariants over* existing columns, not the schema. Do not write a
migration.

---

## Phase 1: Setup (Baseline)

**Purpose**: Establish a known-good starting point so regressions are attributable.

- [X] T001 Record the baseline by running `make test` and `make lint` from the repo root; note the Pest and Vitest counts so any later failure is traceable to this feature (expected baseline: 315 Pest tests passing) — **baseline: 315 Pest tests / 351 Vitest tests, all green**
- [X] T002 [P] Confirm no schema work is needed by checking `backend/database/migrations/2026_09_20_000022_create_artwork_images_table.php` still declares `sha256` char(64), `is_final` boolean default false, and the indexes `['artwork_id', 'is_final']` and `'sha256'` — confirmed, no migration needed

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: One breaking change to the shared component contract that both screens must absorb
together. Leaving this half-applied breaks the artwork create AND curation screens simultaneously.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

**Note**: This feature has no other foundational blocker — US1 (batch upload) and US2 (primary
invariant) touch disjoint code and are independently shippable. Phase 2 is deliberately thin rather
than padded.

- [X] T003 Change the `upload` event signature in `frontend/src/components/curation/ArtworkImagesPanel.vue` from `upload: [file: File, rights: ImageRights]` to `upload: [files: File[], rights: ImageRights]`, and add the optional `max?: number` prop, per [contracts/images-panel-ui.md](./contracts/images-panel-ui.md) — `max` prop declared, unused until T041 (kept `atCap`/`onDrop` out for now to avoid unused-var lint failures ahead of their phases)
- [X] T004 Update the `upload` handler in `frontend/src/pages/admin/ArtworkCreatePage.vue` (`addImage`) to accept `File[]` and append each file to `pending`, preserving the existing first-is-primary and remove-promotes behaviour
- [X] T005 Update the `upload` handler in `frontend/src/pages/admin/ArtworkCurationPage.vue` to accept `File[]` — loops single-file `uploadArtworkImage` for now; swapped to the batch call in T020
- [X] T006 Run `cd frontend && npm run typecheck` to confirm the signature change is fully propagated (bare `vue-tsc --noEmit` is vacuous in this repo — use the npm script) — clean; both affected Vitest specs (16 tests) still pass

**Checkpoint**: Both screens compile and behave exactly as before, now over an array of one.

---

## Phase 3: User Story 1 — Attach a whole shoot in one action (Priority: P1)

**Goal**: A registrar selects many image files in one file-chooser interaction and all of them
attach to the artwork, with each file's outcome reported individually.

**Independent test**: Create an artwork, select four image files in a single file-chooser
interaction, confirm all four queue with filename and dimensions, save, and confirm all four are
attached to the saved artwork.

### Tests for US1

- [X] T007 [P] [US1] Create `backend/tests/Feature/ArtworkImageUploadTest.php` with a test that posting three files as `images[]` attaches all three and returns a `results` array of three `attached` entries each carrying an `image_id`, following the existing upload-test pattern in `ArtworkCurationTest.php:154` (`Storage::fake('local')` plus `UploadedFile::fake()->image()`; there is no `ArtworkImageFactory`)
- [X] T008 [P] [US1] Add a test to `backend/tests/Feature/ArtworkImageUploadTest.php` asserting the cap: with existing images, a batch where `existing count + incoming count > 20` is rejected 422 with nothing attached
- [X] T009 [P] [US1] Add a test to `backend/tests/Feature/ArtworkImageUploadTest.php` asserting a byte-identical file is NOT attached and comes back as `status: "duplicate"` naming the `image_id` it matched, while its non-duplicate siblings in the same batch still attach
- [X] T010 [P] [US1] Add a test to `backend/tests/Feature/ArtworkImageUploadTest.php` asserting mixed validity: a batch of two valid images plus one non-image returns 201, attaches both images, and reports the non-image as `status: "rejected"` with a message
- [X] T011 [P] [US1] Add a test to `backend/tests/Feature/ArtworkImageUploadTest.php` asserting the legacy single-`image` field still attaches and still returns the existing `data` shape unchanged (backward compatibility, per [contracts/artwork-images-api.md](./contracts/artwork-images-api.md)) — plus an extra test for the "both `images[]` and `image` sent" rejection case, not originally enumerated but required by the contract

### Implementation for US1

- [X] T012 [US1] Create `backend/app/Http/Requests/Artwork/StoreArtworkImagesRequest.php` validating: exactly one of `images[]` or `image` present (sending both is an error); `rights_status` nullable and in `unknown|licensed|public_domain|all_rights_reserved` (`ArtworkImage::RIGHTS`); `edit_summary` nullable string max 255; and request-level rejection when `existing image count + incoming count > 20`
- [X] T013 [US1] Rework `store()` in `backend/app/Http/Controllers/Api/V1/ArtworkImageController.php` to normalise both shapes into a file list and loop per file, validating each independently (`jpg,jpeg,png,webp`, max 20480 KB) so one bad file never discards its valid siblings
- [X] T014 [US1] In the same `store()` loop, compare each file's `hash_file('sha256', …)` against the `sha256` of images already on that artwork (and against files already accepted earlier in this same batch); skip matches and record them as `duplicate` with the matched `image_id` — implemented as a single fresh DB query per file rather than a separate in-memory "seen" map: each successful `create()` is immediately visible to the next iteration's query, so one code path catches both cases (see the method doc comment)
- [X] T015 [US1] Build the `results` array in `store()` — one entry per submitted file in submission order, `status` of `attached|duplicate|rejected`, `image_id` for attached and duplicate, human-readable `message` for everything except attached — and return it alongside the unchanged `data` list; return 422 when every file failed
- [X] T016 [P] [US1] Add the per-file upload result type to `frontend/src/types/artworkCuration.ts` (`status: "attached" | "duplicate" | "rejected"`, optional `image_id`, optional `message`, `filename`)
- [X] T017 [US1] Replace `uploadArtworkImage` with `uploadArtworkImages(id, files: File[], rights)` in `frontend/src/api/artworkCuration.ts`, appending each file as `images[]` and returning `{ data, results }`
- [X] T018 [US1] Add `multiple` to the file input in `frontend/src/components/curation/ArtworkImagesPanel.vue` and emit every selected file in one `upload` event (replacing `files?.[0]`), still clearing the input afterwards so re-picking the same file fires a fresh `change`
- [X] T019 [US1] Change the post-save upload in `frontend/src/pages/admin/ArtworkCreatePage.vue` to one batch `uploadArtworkImages` call, and designate the primary using the matching `results[].image_id` — deleting the `Math.max(...list.map((i) => i.id))` guess on line 100 — **scope note beyond the task text**: pending images can carry per-item `rights_status` (set via the row selector before save), but the batch endpoint takes one `rights_status` per call, so `submit()` groups pending images by their rights value and issues one batch call per group (one call in the common case, where nothing overrides the default) rather than the literal single call the task described, to avoid silently dropping FR-012's per-image rights on save
- [X] T020 [US1] Route the curation screen's upload in `frontend/src/pages/admin/ArtworkCurationPage.vue` through `uploadArtworkImages`
- [X] T021 [P] [US1] Add a test to `frontend/src/pages/admin/ArtworkCreatePage.spec.ts` asserting a single `change` carrying three files queues three pending images, and that save issues exactly one batch upload call
- [X] T022 [P] [US1] Update `frontend/src/pages/admin/ArtworkCurationPage.spec.ts` for the batch call signature (it currently asserts `api.updateArtworkImage` at line 126 and the single-file upload path)

**Verified**: 13 new Pest tests in `ArtworkImageUploadTest.php` (10 pass at this checkpoint, 3 are
US2/Phase-4 primary-invariant tests included here since they were natural to write alongside —
correctly still red); `ArtworkCurationTest.php`'s pre-existing upload test needed a fixture fix
(two same-dimension fakes are byte-identical, so the new duplicate check correctly blocked the
second one — updated to distinct dimensions, not a behavior change). Frontend: 17/17 Vitest tests
green across both spec files, `npm run typecheck` clean, Pint/Larastan clean on all new/changed
backend files.

**Checkpoint**: US1 is independently shippable — many files in one action, per-file outcomes,
duplicates blocked, cap enforced.

---

## Phase 4: User Story 2 — Decide which image represents the artwork (Priority: P1)

**Goal**: An artwork with images always has exactly one primary image, the registrar controls which,
and the choice is what shows everywhere the artwork is represented by one picture.

**Independent test**: Attach three images one at a time, mark the third primary, save, then confirm
the third is what appears in the registry listing and on the detail screen.

**Why this is separate from US1**: the create screen already gets primary semantics right for
unsaved images; nothing else does. Uploads from the curation screen currently produce an artwork with
**zero** primary images. This phase fixes that at the model layer, for every write path.

### Tests for US2

- [X] T023 [P] [US2] Add a test to `backend/tests/Feature/ArtworkImageUploadTest.php` asserting the first image uploaded to an artwork with no images is automatically `is_final = true`, on the plain single-file path (invariant I1, FR-006) — written alongside T007-T011, red until T028/T029
- [X] T024 [P] [US2] Add a test to `backend/tests/Feature/ArtworkImageUploadTest.php` asserting that deleting the primary image promotes the oldest remaining image to primary, and that deleting the last image leaves the artwork with no images and no primary (FR-009) — same as above; the "promotes oldest" test had its own bug (asserted against `data.0` instead of `data.1` post-upload, since `images()` sorts `is_final DESC` and `$first` was still primary at that point) — fixed once red-for-the-right-reason was confirmed
- [X] T025 [P] [US2] Add a test to `backend/tests/Feature/ArtworkImageUploadTest.php` asserting that a batch upload to an empty artwork produces exactly one primary among the attached images, not several — written alongside T007-T011, red until T028/T029
- [X] T026 [US2] Add a merge regression test to `backend/tests/Feature/ArtworkCurationTest.php`: merging an image-bearing duplicate into an image-less survivor leaves the survivor holding the images with **exactly one** primary (this fails before T029 — see [research.md](./research.md) R4) — confirmed red (0 primary, expected 1) before T028-T030, green after
- [X] T027 [US2] Update the existing `it('uploads artwork images privately, marks one final, …')` test at `backend/tests/Feature/ArtworkCurationTest.php:154` for the new invariant — the first upload is now primary on arrival, so any assumption that a freshly uploaded image starts non-primary must be rewritten (the closing `has_final_hr_image` and single-primary assertions should still hold) — no rewrite was actually needed for the primary-invariant reason (the test never asserted an initial non-primary state); it **did** need a different fix from US1 (T014's duplicate blocking): the two fakes shared dimensions and were byte-identical, so the second upload is now correctly blocked as a duplicate — fixed by varying the width

### Implementation for US2

- [X] T028 [US2] Create `backend/app/Models/Observers/ArtworkImageObserver.php` implementing invariant I1: on `created`, set `is_final` if the parent artwork has no primary image; on `deleted`, promote the oldest remaining image (lowest `id`) if the removed one was primary and siblings remain. Expose the promotion as a reusable public method so the merger can call it (`ArtworkImage` has no SoftDeletes, so `deleted` is a hard delete)
- [X] T029 [US2] Register the observer in `backend/app/Models/ArtworkImage.php` by adding a `booted()` method calling `static::observe(Observers\ArtworkImageObserver::class)`, matching the pattern in `Artwork.php:147` and `Artist.php:168` (the model currently has no `booted()`)
- [X] T030 [US2] Fix `backend/app/Support/Curation/ArtworkMerger.php` line 44: after the bulk `update(['artwork_id' => …, 'is_final' => false])` — which bypasses model events by design — re-assert the invariant so the survivor ends with exactly one primary, reusing the observer's promotion method rather than duplicating the rule — added `ArtworkImageObserver::promoteOldest($survivor->id)` right after the bulk update

**Verified**: all 13 `ArtworkImageUploadTest` tests green, all 14 `ArtworkCurationTest` tests green
(including the new merge regression test), Pint and Larastan clean on every touched backend file,
full backend suite 329/329.
- [X] T031 [P] [US2] Reword the five "final"-bearing strings in `frontend/src/i18n/locales/en/curation.json` under `artworkImages` — `finalCaption`, `noFinalCaption`, `final`, `makeFinal` — to "primary" wording. Keep the key names (they are internal) and change only the displayed values (FR-016) — **found a 6th and 7th string outside this task's list** while sweeping for stray "final" wording: `curation.artworkDetail.finalImage`/`noFinalImage` (used by `ArtworkFormSections.vue`'s detail-page field), reworded too — a full-repo grep for "final"/"Final" (EN) and "نهائي" (AR) confirms zero remaining user-facing occurrences; only internal identifiers (`is_final`, `isFinal`, `makeFinal` handler name, `final-image` prop name) remain, which is correct per R5's scope
- [X] T032 [P] [US2] Apply the matching Arabic rewording to the same keys in `frontend/src/i18n/locales/ar/curation.json`; both locale files must move together or the existing i18n parity test fails
- [X] T033 [US2] Update `frontend/src/components/curation/ArtworkImagesPanel.vue` so the primary image is distinguishable at a glance — badge on the designated row, main preview showing it — using the reworded keys (FR-008). `main()` on line 19 keeps its `?? props.images[0]` fallback for legacy artworks — the badge and main-preview logic already existed and needed no structural change once the keys were reworded; only addition was a `data-testid="primary-badge"` for stable testing (T035)
- [X] T034 [US2] Update the `final-image` prop label passed from `frontend/src/pages/admin/ArtworkCurationPage.vue` line 318 to the new "primary" wording; the prop name and `curation.has_final_hr_image` API field stay as they are — no code change needed; the label already flows through the now-reworded `curation.artworkDetail.finalImage`/`noFinalImage` keys
- [X] T035 [P] [US2] Add a test to `frontend/src/pages/admin/ArtworkCreatePage.spec.ts` asserting the first queued image is primary, that designating another moves the badge, and that removing the primary promotes another

**Checkpoint**: US2 is independently shippable — every write path maintains exactly one primary, and
the UI says "primary" in both languages.

**Verified**: full backend suite 329/329, full frontend suite 353/353 (351 baseline + 2 new),
`npm run typecheck` and `npm run lint` clean, i18n parity test green, Pint/Larastan clean on every
touched backend file.

---

## Phase 5: User Story 3 — Fix an attachment mistake before committing (Priority: P2)

**Goal**: Before saving, the registrar can remove a queued image, set per-image rights, change which
is primary, and see exactly which files failed if part of a batch did not attach.

**Independent test**: Queue five images, remove two, change one's rights, change which is primary,
save, and confirm the saved artwork reflects precisely those decisions.

### Tests for US3

- [X] T036 [P] [US3] Add a test to `frontend/src/pages/admin/ArtworkCreatePage.spec.ts` asserting that re-selecting an already-queued file (same `name` + `size` + `lastModified`) does not queue it twice and surfaces an "already attached" message
- [X] T037 [P] [US3] Add a test to `frontend/src/pages/admin/ArtworkCreatePage.spec.ts` asserting that when a batch upload returns mixed `results`, every `rejected`/`duplicate` filename is surfaced, the successfully attached images are kept, and the user still lands on the saved artwork (FR-013) — **the task text's "lands on the saved artwork" was ambiguous against the spec's own AC3 wording ("taken to the saved artwork rather than losing their work") vs. the pre-existing, still-current behavior of staying on the create page with a link**; I kept the existing stay-and-link behavior (matches T039's framing as "replacing the warning", not "changing navigation") and wrote the test against that, rather than the more literal reading — flagging in case that reading was intended

### Implementation for US3

- [X] T038 [US3] Add the cheap client-side duplicate pre-check to `frontend/src/pages/admin/ArtworkCreatePage.vue` — compare `name + size + lastModified` against already-queued files before queueing, and report any skipped file by name. Deliberately no byte hashing: the backend is authoritative and jsdom supplies no `crypto.subtle` (see [research.md](./research.md) R2)
- [X] T039 [US3] Surface per-file `results` entries on `frontend/src/pages/admin/ArtworkCreatePage.vue`, replacing the single generic `images-failed` warning at line 135 with per-file reporting that names each rejected or duplicate file — kept the generic sentence as a heading (still useful context) and added a bulleted per-file list beneath it; added a `hasFailure` ref since the banner must still show for a hard network exception (no per-file results at all), which the original "keyed off `imageIssues.length`" design would have hidden — caught by the pre-existing network-failure test
- [X] T040 [P] [US3] Surface per-file `results` entries on `frontend/src/pages/admin/ArtworkCurationPage.vue` for the immediate-upload path — reused the existing `actionError` display rather than adding new UI, since the curation screen already re-fetches and shows one error slot per action
- [X] T041 [P] [US3] Add the `max` cap feedback to `frontend/src/components/curation/ArtworkImagesPanel.vue` — tell the registrar the cap and stop offering uploads once reached, with the server remaining authoritative (FR-014) — added `MAX_ARTWORK_IMAGES = 20` to `types/artworkCuration.ts`, mirroring `StoreArtworkImagesRequest::MAX_IMAGES_PER_ARTWORK` with a comment cross-referencing it (no shared-constant mechanism exists between backend and frontend in this repo, so this is duplicated by hand — server stays authoritative per FR-014)
- [X] T042 [P] [US3] Add translation strings for the new duplicate, rejection and cap messages to both `frontend/src/i18n/locales/en/curation.json` and `frontend/src/i18n/locales/ar/curation.json`

**Checkpoint**: Bulk attachment is safe to use — mistakes are visible and correctable before commit.

**Verified**: frontend suite 355/355 (353 + 2 new), `npm run typecheck` and `npm run lint` clean,
i18n parity test green.

---

## Phase 6: User Story 4 — Drag files onto the panel (Priority: P3)

**Goal**: Dropping a group of files onto the image area works exactly like a multi-select.

**Independent test**: Drag three image files onto the panel and confirm they queue identically to a
file-chooser multi-selection.

- [X] T043 [P] [US4] Add a test to `frontend/src/pages/admin/ArtworkCreatePage.spec.ts` asserting a `drop` event carrying three files queues three pending images, identically to a `change` event
- [X] T044 [US4] Add a drop zone to `frontend/src/components/curation/ArtworkImagesPanel.vue` emitting the same `upload` event with the same `File[]` shape, so neither page needs additional handling. Use logical Tailwind properties only (`ms-`/`me-`/`ps-`/`pe-`/`start-`/`end-`) per D12 — implemented by extending the existing upload `<label>` into the drop target itself (`@dragover`/`@dragleave`/`@drop`), rather than a separate overlay element, so file-type filtering and the `dragOver` visual state stay in one place; filters dropped items to `image/*` client-side (server remains authoritative on real validity)
- [X] T045 [P] [US4] Add drop-zone hint strings to both `frontend/src/i18n/locales/en/curation.json` and `frontend/src/i18n/locales/ar/curation.json`

**Checkpoint**: All four user stories complete.

**Verified**: full frontend suite 356/356 (355 + 1 new drop test), `npm run typecheck` and
`npm run lint` clean, i18n parity holds (part of the full suite run).

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T046 [P] Add a test to `backend/tests/Feature/ArtworkImageUploadTest.php` asserting that attaching images never changes the artwork's `publication_status` and that a newly attached `unknown`-rights image on a draft artwork returns 404 from `GET /artworks/{a}/images/{i}/file` for an anonymous requester (FR-015, SC-006, privacy rule 5) — extended the existing "never changes publication status..." test in place rather than adding a new one (same assertions, one more expectation)
- [X] T047 [P] Confirm `path` and `sha256` remain in `excludedFromActivityLog()` in `backend/app/Models/ArtworkImage.php` so observer-driven primary flips stay audited without leaking file locations or checksums (privacy rule 9, D14) — confirmed unchanged
- [X] T048 [P] Verify Arabic/English key parity for every string added or reworded across `frontend/src/i18n/locales/{ar,en}/curation.json` by running the existing i18n parity test — green throughout (part of every full Vitest run this phase)
- [X] T049 Run `make lint` (Pint, Larastan, ESLint, `npm run typecheck`) and fix every finding — Pint/ESLint/typecheck clean; Larastan shows the same 4 pre-existing failures recorded at the T001 baseline, all in `HomeController.php`, a file this feature never touches — nothing to fix here, confirmed not a regression
- [X] T050 Run `make test` and confirm the whole suite is green against the T001 baseline, with the new Pest and Vitest coverage added — **329/329 Pest** (315 baseline + 14: 13 in `ArtworkImageUploadTest` + 1 merge regression), **356/356 Vitest** (351 baseline + 5: T021, T035, T036, T037, T043), exit code 0
- [X] T051 Walk through the eight scenarios in [quickstart.md](./quickstart.md) in a browser against `make dev`, including Scenario 7 (merge keeps exactly one primary) and Scenario 8 (nothing auto-publishes) — the UI behaviours cannot be confirmed by the test suite alone — **not viewed in a browser (no browser tool available in this session)**, consistent with how this same limitation is already recorded elsewhere in `docs/decisions.md` (F10/F12 frontend entries). Every scenario's underlying behavior *is* exercised: 1/2/3/5/7/8 map directly to passing `ArtworkImageUploadTest`/`ArtworkCurationTest` cases (exact API contract a browser session would hit), 1/2/3/4/6 map to passing `ArtworkCreatePage.spec.ts`/`ArtworkCurationPage.spec.ts` DOM assertions (badge visibility, wording, per-file messages). What a live browser run would add beyond the tests: actual visual rendering, real drag-and-drop interaction (jsdom's drop event is synthetic), and RTL layout — genuinely unverified, flagged rather than assumed
- [X] T052 Append a decision entry to `docs/decisions.md` recording the batch-upload contract, the observer-enforced primary invariant, the duplicate-blocking split of authority, and the deliberate choice to leave the stored column named `is_final` while the UI says "primary". Follow the file's own rule: amendments are appended, never silently edited — appended as "Artwork image batch upload & primary designation", following this file's own recent-section convention (prose, no D-number) rather than inventing a new D-number sequence

---

## Dependencies & Execution Order

### Phase order

```text
Phase 1 (Setup)
   └─> Phase 2 (Foundational — shared component signature)
          ├─> Phase 3 (US1, P1)  ─┐
          └─> Phase 4 (US2, P1)  ─┤  independent of each other
                                  └─> Phase 5 (US3, P2) — needs US1's `results`
                                         └─> Phase 6 (US4, P3)
                                                └─> Phase 7 (Polish)
```

### Story dependencies

- **US1** depends only on Phase 2. Fully independent of US2.
- **US2** depends only on Phase 2. Fully independent of US1. Either P1 story can ship first.
- **US3** depends on US1 (it reports the per-file `results` US1 introduces) and reads better after
  US2, though it does not strictly require it.
- **US4** depends on Phase 2's array signature only; it is sequenced last because it is P3, not
  because anything blocks it.

### Within-phase ordering

- T012 → T013 → T014 → T015 are strictly sequential: all four touch `ArtworkImageController::store()`
  and its request class.
- T028 → T029 → T030: the observer must exist before registration, and registration before the
  merger can reuse its promotion method.
- T031/T032 (locale files) are parallel to each other but both must land before T048's parity check.
- T033 and T041 and T044 all edit `ArtworkImagesPanel.vue` — sequence them, do not run in parallel
  despite their `[P]` peers elsewhere in the same phase.

### Parallel opportunities

- **Phase 3 tests**: T007–T011 all create or append to one new test file — write them together, but
  they are the same file, so treat `[P]` as "no dependency", not "concurrent edit".
- **Phase 4**: T031 and T032 (the two locale files) are genuinely concurrent. T023/T024/T025 are
  independent test cases.
- **Phase 7**: T046, T047, T048 touch different files and run concurrently.

> **A note on `[P]` and shared files**: several `[P]`-marked tasks in this list append to the same new
> test file (`ArtworkImageUploadTest.php`) or the same locale files. The marker means "no logical
> dependency on incomplete work" — it does not license two concurrent writers on one file. Where two
> tasks name the same path, serialise them.

## Implementation Strategy

### MVP (recommended first increment)

**Phase 1 + Phase 2 + Phase 3 (US1)** — multi-file selection with per-file outcomes, duplicate
blocking and the cap. This is the bulk of the time saving the request was about, and it ships
without touching the primary invariant.

### Second increment

**Phase 4 (US2)** — the primary invariant. Worth taking soon after US1 regardless of ordering,
because it fixes two live defects rather than only adding capability: artworks catalogued from the
curation screen currently end up with **zero** primary image, and merging into an image-less survivor
leaves the merged artwork with no primary at all. Both are reachable in production today.

### Then

**Phase 5 (US3)** → **Phase 6 (US4)** → **Phase 7 (Polish)**.

Phase 7's T051 (browser walkthrough) and T052 (decision log) should not be skipped: the UI behaviours
in US1–US4 cannot be verified by Pest or Vitest alone, and this repo's convention is that
architectural choices land in `docs/decisions.md`.
