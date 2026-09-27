# Phase 0 Research: Multiple Image Upload with Primary Image Selection

All unknowns from the Technical Context are resolved below. Each entry records what was chosen, why,
and what was rejected. Findings came from reading the current implementation rather than from
assumption — the "Current state" notes are what the code does today.

## R0: What already exists (baseline)

Worth stating first, because it substantially shrinks the feature:

| Behaviour | Create screen | Curation screen | Backend |
|---|---|---|---|
| Queue several images before saving | ✅ already (one at a time) | n/a (uploads immediately) | n/a |
| Select several files in one interaction | ❌ `files?.[0]`, no `multiple` | ❌ same shared component | ❌ single `image` field |
| First image auto-becomes primary | ✅ already (`isFinal: pending.length === 0`) | ❌ nothing sets it | ❌ column defaults `false` |
| Re-promote after removing the primary | ✅ already (promotes first) | ❌ nothing does | ❌ nothing does |
| Only one primary at a time | ✅ already | ✅ via PATCH | ✅ in `update()` only |
| Per-image rights | ✅ | ✅ | ✅ |
| Duplicate detection | ❌ | ❌ | ❌ (SHA-256 stored + indexed, never compared) |

**Consequence**: FR-005/006/009 are already satisfied *for pending images on the create screen*, and
not satisfied anywhere else. An artwork whose images were all uploaded from the curation screen has
**zero** primary images until a human clicks "make final" — which is exactly the implicit-fallback
ambiguity the spec is closing. So the invariant work belongs on the backend, not in the create page.

## R1: How to upload several files

**Decision**: Extend `POST /api/v1/artworks/{id}/images` to accept an `images[]` field (1..N files)
while continuing to accept the existing single `image` field. Respond with the full image list in
`data` (unchanged, so existing callers keep working) plus a new `results` array giving a per-file
outcome: `attached` with its image id, `rejected` with a reason, or `duplicate` with the id of the
image it matched.

**Rationale**:
- FR-004 and FR-013 require *per-file* outcomes. A `results` array expresses that directly; N
  separate requests would force the client to reassemble it.
- One round trip instead of 20. The API rate limiter is 120/min per IP (D21), so a 20-file attach
  via sequential requests would consume a sixth of a registrar's minute budget for a single action.
- It removes an existing latent bug. The create page currently identifies the image it just
  uploaded with `Math.max(...list.map(i => i.id))` — inferring "the newest id is mine" from a
  response that returns the whole list. With concurrent uploads that guess is wrong. Returning
  per-file ids makes the guess unnecessary and lets that line be deleted.
- Backward compatible: `data` keeps its current shape and status code, so the curation screen's
  single-file path and the existing Pest/Vitest assertions continue to hold.

**Alternatives rejected**:
- *N sequential single-file requests from the client* — zero backend change, but 20 round trips,
  20 rate-limit hits, 20 full-list responses, and it keeps the `Math.max(id)` guess alive.
- *N parallel requests* — faster than sequential but makes the `Math.max(id)` guess definitively
  wrong, and makes "exactly one primary" a write race between simultaneous creates.
- *A separate `/images/batch` endpoint* — a second endpoint doing what the first should do, with
  duplicated authorization and validation. Rejected as needless surface.

## R2: How to detect and block duplicates

**Decision**: Split by authority.
- **Backend is authoritative.** On upload, hash each incoming file (SHA-256 is already computed for
  storage) and compare against the `sha256` of images already on that artwork. A match is not
  attached and comes back as `status: "duplicate"` naming the file and the image it matched.
- **Browser catches the cheap case.** Before queueing, compare `name + size + lastModified` against
  already-queued files to reject the same-file-picked-twice case instantly, with no byte reading.

**Rationale**:
- `docs/privacy-rules.md` rule 7 requires duplicates be "detected by checksum and reported, never
  silently rejected". Only the server can honour that authoritatively, and it already computes the
  checksum, so the check costs one indexed query (`sha256` is indexed).
- Hashing in the browser would mean reading up to 20 × 20 MB = 400 MB through `crypto.subtle` before
  the registrar can even see their files queued. The cheap triple catches the overwhelmingly common
  mistake (re-picking the same file) for free.
- It also avoids a test-environment problem: the frontend suite runs in jsdom, which does not supply
  `crypto.subtle`, so client-side hashing would require a shim in `src/test/setup.ts` purely to make
  tests run. No existing code uses `crypto.subtle`; this keeps it that way.
- The gap is acceptable and spec-compliant: a byte-identical file *renamed* slips past the client
  check, reaches the server, and is blocked and reported there. FR-011 is satisfied either way; the
  client check is a latency optimisation, not the guarantee.

**Alternatives rejected**:
- *Client-side SHA-256 as the gate* — 400 MB of hashing on the happy path, a jsdom shim, and the
  server would still need its own check because a client cannot be trusted. Cost with no authority.
- *A unique DB index on `(artwork_id, sha256)`* — would enforce it in the right place but surfaces
  as a driver-level integrity error that has to be caught and translated back into a per-file
  message anyway; and it would make the *merge* path (which moves images between artworks) able to
  fail on a constraint. Explicit comparison keeps control of the error message.
- *Perceptual/visual similarity matching* — explicitly out of scope per the spec's assumption; a
  re-crop is a different image.

## R3: Where the "exactly one primary" invariant lives

**Decision**: A new `ArtworkImageObserver`, registered via `static::observe()` inside
`ArtworkImage::booted()`, matching how `ArtworkObserver` and `ArtistObserver` are wired.
- `created`: if the artwork has no primary image, this one becomes primary.
- `deleted`: if the removed image was primary and siblings remain, promote the oldest remaining.

**Rationale**:
- Every writer gets the invariant for free — the API, `ArtworkMerger`, importers, seeders and any
  future bulk tooling. Putting it in the controller would leave all the other paths broken, which is
  precisely the situation today.
- It matches the established repo convention: D26 puts artist-rename search resync in
  `ArtistObserver`, D30 puts child-orphaning on soft delete in `ArchiveItemObserver`. Cross-model
  invariants are observer work here.
- `ArtworkImage` already uses `LogsChanges`, so observer-driven primary flips remain audited
  field-level (privacy rule 9) with `path`/`sha256` still excluded from diffs.

**Alternatives rejected**:
- *Enforce in `ArtworkImageController` only* — leaves merge, seeders and imports able to produce
  artworks with zero or many primaries. It is today's bug, not a fix.
- *A DB constraint / partial unique index* — MySQL 8 has no partial/filtered unique index, so
  "at most one row per artwork with `is_final = 1`" cannot be expressed directly. A generated-column
  trick would work but is opaque, and D23 already establishes that this project accepts
  application-layer enforcement of invariants MySQL can't express.
- *Compute the primary on read instead of storing it* — that is effectively today's
  "final, else first" fallback, and it is the ambiguity the spec exists to remove: it gives no way
  for a registrar to say "this one", only "whatever sorts first".

## R4: Merge currently breaks the invariant

**Current state**: `ArtworkMerger` line 44 moves the duplicate's images to the survivor and sets
`is_final => false` on all of them:

```php
ArtworkImage::where('artwork_id', $duplicate->id)->update(['artwork_id' => $survivor->id, 'is_final' => false]);
```

If the survivor had no images of its own, the merged artwork ends up with images and **no primary**
— a direct FR-005 violation, and reachable today from the merge modal.

**Decision**: Keep clearing the incoming images' primary flag (the survivor's own choice must win —
that is the intent of the existing line, and it is correct), then ensure a primary exists
afterwards. Because the invariant lives in the observer, the cleanest fix is for the merger to
re-assert it after the move rather than hand-rolling the logic: if the survivor has images but no
primary, promote its oldest.

**Rationale**: The bulk `update()` above bypasses Eloquent events by design (it is one query, not N
model saves), so the observer will not fire for it. The merger therefore has to ask for the
invariant explicitly. Keeping that call in one place — a small public method on the observer or a
shared helper both call — avoids two copies of the promotion rule.

**Note for `/speckit-tasks`**: this is a pre-existing bug the feature exposes, not new work the
feature creates. It needs its own test (`merge leaves the survivor with exactly one primary`).

## R5: Scope of the "primary" rename

**Decision**: Reword the eight `curation.artworkImages.*` strings in **both**
`frontend/src/i18n/locales/en/curation.json` and `.../ar/curation.json`. Five carry "final"
wording today: `finalCaption`, `noFinalCaption`, `final`, `makeFinal`, plus the concept leaking into
`ArtworkCurationPage.vue`'s `final-image` prop label. Translation *keys* may keep their names;
only displayed text changes. The stored `is_final` column, the `hr_image` checklist key and the
`has_final_hr_image` API field are untouched.

**Rationale**:
- Q2 → option A: "primary" everywhere user-facing, stored name unchanged.
- The suite asserts Arabic/English key parity, so both files must move together or tests fail.
- Leaving keys alone keeps the diff to string values, which is easy to review and impossible to get
  subtly wrong in a template.
- `has_final_hr_image` and `hr_image` are consumed by the completeness/checklist engine
  (`ArtworkCurationShowController`) and the F10 dashboard rules. Renaming them would ripple into
  that engine for zero user-visible benefit.

**Alternatives rejected**:
- *Rename the column and API fields too* (Q2 option C) — migration plus merger, registry,
  checklist and completeness read paths. Explicitly declined.
- *Rename keys as well as values* — churn in two locale files and every `t()` call site, with the
  same visible result.

## R6: The 20-image cap

**Decision**: Enforce in a new `StoreArtworkImagesRequest` Form Request — validate that
`existing count + incoming count <= 20`, with the excess reported per-file rather than failing the
whole request (FR-014, FR-004). Mirror the limit in the panel so the registrar is told before
uploading.

**Rationale**: D24 establishes that this project does input mapping/validation in the request layer
rather than in observers or controllers. Server-side is the real gate; the client-side mirror is
only for fast feedback. 20 is a working figure from the spec's Assumptions, adjustable in one place.

**Alternatives rejected**: *No cap* — an unbounded batch of 20 MB files is a trivial way to exhaust
disk and request limits. *Config-driven cap* — a constant is enough until someone needs to vary it;
adding config indirection now would be speculative.

## R7: Constitution gate substitution

**Decision**: Record the empty constitution as a flagged process gap and gate the feature against
`docs/privacy-rules.md` (labelled non-negotiable) and `docs/decisions.md` (locked decisions,
append-only) instead. See the Constitution Check table in [plan.md](./plan.md).

**Rationale**: `.specify/memory/constitution.md` still contains only placeholder tokens, so deriving
gates from it is impossible. Asserting a pass against unwritten principles would be worse than
useless — it would look like the gate ran. The project's real governance is written down elsewhere
and is specific enough to gate against, and three of its rules bear directly on this feature
(rights/no-auto-publish, immutable originals + checksum duplicate reporting, field-level audit).

**Follow-up**: run `/speckit-constitution` to populate the constitution. Source material is already
identified: `README.md`, `docs/decisions.md` (D1–D112 plus amendments A1–A9), `docs/privacy-rules.md`.
