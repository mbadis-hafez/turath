# Quickstart: Validating Multiple Image Upload with Primary Image Selection

How to prove this feature works end to end. Scenarios map to the spec's user stories and success
criteria; details of shapes and invariants live in
[contracts/artwork-images-api.md](./contracts/artwork-images-api.md) and
[data-model.md](./data-model.md) rather than being repeated here.

## Prerequisites

- PHP 8.4 + Composer, Node 22+, MySQL and Redis running under Herd (see root `README.md`)
- Databases created: `bidayaat` and `bidayaat_test`
- A signed-in user holding `artworks.manage` — the seeded `editor@bidayaat.test` / `password`, or
  a team account from `TeamUserSeeder`

```bash
make install         # once
make migrate         # migrate:fresh --seed  (NOTE: wipes the DB, including user accounts)
make dev             # API on :8000, SPA on :5173
```

> `make migrate` runs `migrate:fresh --seed`, which drops everything. Use the full `--seed` form (not
> individual seeders) so `TeamUserSeeder` reinstates the team logins.

Have 3–4 real JPEGs to hand, plus one deliberate copy of one of them under a different filename (for
the duplicate scenario) and one non-image file such as a PDF (for the rejection scenario).

## Automated checks

```bash
make test                                            # Pest + Vitest, both sides
make lint                                            # Pint, Larastan, ESLint, vue-tsc

# Targeted while iterating:
cd backend  && php artisan test --filter=ArtworkImageUploadTest
cd backend  && php artisan test --filter=ArtworkCurationTest
cd frontend && npx vitest run src/pages/admin/ArtworkCreatePage.spec.ts
cd frontend && npm run typecheck                     # bare vue-tsc --noEmit is vacuous here
```

Expected once implemented: the full suite stays green (baseline is 315 Pest tests passing), with new
coverage for batch upload, duplicate blocking, the image cap, the primary invariant, and the merge
case from research R4.

## Scenario 1 — Attach a whole shoot in one action (US1, SC-001/002)

1. Sign in, go to `/ar/admin/artworks/new` (or `/en/...`).
2. Fill the minimum required artwork fields.
3. In the images panel, open the file chooser **once** and select all 3–4 JPEGs together.
4. **Expect**: every file appears in the queue with filename and pixel dimensions; none replaced
   another; the first is marked primary.
5. Save.
6. **Expect**: you land on the artwork's curation page with all of them attached, and exactly one
   marked primary.

✅ SC-002 is met if that took **one** file-chooser interaction, not one per image.

## Scenario 2 — Primary selection sticks (US2, SC-003/004)

1. On the artwork from Scenario 1, designate the **third** image as primary.
2. **Expect**: the third shows the primary badge, the first no longer does, and the panel's main
   preview switches to it.
3. Go to `/ar/admin/artworks`.
4. **Expect**: the card thumbnail for this artwork is the third image — the one you chose.
5. Remove the primary image.
6. **Expect**: another image immediately becomes primary; the artwork is never left with images and
   no primary.
7. Remove every remaining image.
8. **Expect**: no primary, no error, and the registry falls back to the no-image placeholder.

## Scenario 3 — Duplicates are blocked and named (FR-011, SC-008)

1. On the create screen, queue one JPEG, then select the **same file** again.
2. **Expect**: it is not queued twice, and you are told it is already attached (caught client-side by
   `name + size + lastModified`).
3. Now select the byte-identical **copy under a different filename**.
4. **Expect**: it queues (the cheap client check can't see it), then on save the server blocks it and
   names it as a duplicate of the image it matched. The other images still attach.
5. **Verify** nothing slipped through:

```bash
cd backend && php artisan tinker --execute="
\$a = App\Models\Artwork::latest('id')->first();
echo \$a->images()->count().' images, '.\$a->images()->distinct('sha256')->count('sha256').' distinct checksums'.PHP_EOL;
echo \$a->images()->where('is_final', true)->count().' primary'.PHP_EOL;"
```

**Expect**: image count equals distinct-checksum count, and exactly `1 primary`.

## Scenario 4 — Bad files don't take good ones down (FR-004)

1. Select 2 valid JPEGs **and** the PDF in one selection.
2. **Expect**: both JPEGs queue; the PDF is named as rejected with a reason; the rejection does not
   discard the JPEGs.
3. Try a selection of *only* invalid files.
4. **Expect**: nothing queues, and you're told why.

## Scenario 5 — The cap holds (FR-014)

1. Attach images until 20 are on one artwork.
2. Attempt to attach more.
3. **Expect**: told the cap is reached; the excess is named rather than silently dropped; the first
   20 are unaffected.

## Scenario 6 — Wording is consistent in both languages (FR-016, SC-009)

1. View the curation page at `/en/admin/artworks/{id}` — the badge and action read **primary**, not
   "final".
2. Switch to `/ar/admin/artworks/{id}` — Arabic wording matches the same concept.
3. **Verify** no stale wording remains:

```bash
cd frontend && grep -rn "makeFinal\|finalCaption\|noFinalCaption" src/i18n/locales/*/curation.json
```

**Expect**: keys may remain (they are internal), but no *displayed value* still says "final" /
"نهائية". The i18n parity test must stay green.

## Scenario 7 — Merge keeps exactly one primary (research R4 regression)

This is the pre-existing bug the feature exposes — worth checking by hand once.

1. Create artwork **A** with no images. Create artwork **B** with two images.
2. Merge B into A from the registry's merge modal, keeping A as survivor.
3. **Expect**: A now holds both images and **exactly one** is primary.

Before this feature, A ends up with two images and **zero** primary.

```bash
cd backend && php artisan tinker --execute="
\$a = App\Models\Artwork::find(<A_id>);
echo \$a->images()->count().' images, '.\$a->images()->where('is_final', true)->count().' primary';"
```

## Scenario 8 — Nothing auto-publishes (FR-015, SC-006, privacy rule 5)

1. Attach images to a **draft** artwork, leaving rights at the default `unknown`.
2. Sign out (or use a private window).
3. Request the image URL from `data[].url` directly.
4. **Expect**: `404`. Not a redirect, not the bytes.
5. **Expect**: attaching did not change the artwork's `publication_status` — it is still `draft`.

## What is deliberately *not* validated here

- Thumbnail/derivative generation — still out of scope; originals are served as-is.
- Image reordering — out of scope; only the primary designation is controllable.
- Perceptual duplicate detection — out of scope; matching is byte-exact, so a re-crop is a new image.
- Retroactive fixing of legacy artworks that already have images but no primary — no migration; reads
  stay safe via the existing "primary, else first" fallback.
