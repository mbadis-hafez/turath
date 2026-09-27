# Research: Creation requires review, same as editing

## R0: What already exists (baseline)

- **The editorial-draft pipeline** (`EditorialDraftService`, `EditorialDraftController`, frontend
  `useRecordDraft`/`DraftStatusBanner`) is how editors already edit *existing* artists, artworks, events
  and archive items: a save upserts a section-keyed `EditProposal.payload` (status `draft`), "send for
  review" flips it to `pending` (computing `field_diffs` from `payload` vs. the live record and rejecting
  with 422 if nothing differs), a reviewer approves (`EditorialDraftService::apply()`, which fills and
  saves the record through its normal update path) or requests changes (back to `changes_requested`).
- **`EditProposal.citable_id`/`citable_type`** is a `morphTo` that must point at an existing row —
  `ProposalController::authorizeReview()` 404s if the record can't be found. The whole review/queue/audit
  machinery assumes the record already exists.
- **Creation is currently a separate, direct-write path per type**: `ArtistCreatePage.vue` calls
  `createArtist()` (a live `POST`) then several more direct writes (curation, entries, social links,
  portrait, assignment) before redirecting to the curation/edit page. `ArtworkCreatePage.vue` is the
  same shape. `EventEditPage.vue` and `ArchiveEditPage.vue` already unify create-and-edit in one
  component (an `isNew` branch), but their create branch is *also* a direct write, not routed through the
  draft pipeline.
- **Publication is already gated, separately from review.** Every type defaults to
  `publication_status = draft` at creation and is invisible publicly until explicitly published/verified
  — but that flip requires no reviewer involvement today; it's the same direct authority (`artists.manage`
  etc.) that created the record in the first place. This is the actual asymmetry the bug report surfaced:
  big actions (create the whole record, verify/publish it) bypass review entirely, while small content
  edits to an existing record are blocked behind it.

## R1: Should the record row exist before or only after approval?

**Decision**: The row is created immediately, exactly as today (needed for a stable id — file uploads,
slugs, relations — and to avoid inventing a second "record" representation). What changes is a new
per-type nullable `creation_approved_at` timestamp, `NULL` until a reviewer approves the record's
creation. Nothing that depends on a record being real and reviewed (publish/verify, being selectable as a
linked entity elsewhere, appearing in listings that assume reviewed material) is permitted while it's
`NULL` (FR-005, FR-006, FR-008).

**Rationale**:
- `EditProposal`/`ReviewQueueItem`/`ProposalController` all assume `citable_id` resolves to a real row;
  deferring row creation would mean building an entirely parallel "proposed record" representation just
  for the pre-approval window, then migrating it into a real row on approval — real complexity for a
  problem a single nullable column already solves.
- File uploads (portrait, artwork images, archive files) already need a real id to attach to. Not
  creating the row would either block uploads until approval (bad UX — "attach your portrait" has to wait
  for a stranger's decision) or require a separate staging-upload mechanism.
- Slugs are already generated from the row's own data at creation (see `ArtistSlugGenerator` and peers);
  deferring row creation would mean generating a slug for a record that doesn't exist yet, or deferring
  slug assignment to approval time, which is extra state to track for no real benefit.

**Alternatives considered**: a "shadow" table holding proposed-but-not-yet-real records, promoted to the
real table on approval. Rejected — doubles the entity model per type, doubles every read path that needs
to consider "does this record exist, in either table," and provides no benefit over a nullable column
once file uploads and slugs are accounted for.

## R2: How does a reviewer see "what's proposed" with no prior version to diff against?

**Decision**: No diff view for a creation review. The reviewer's screen for a pending creation shows the
record's current field values directly — which, since nothing has touched them except the creating
editor, **are** the proposed content. This reuses the *existing* curation read views (the same
resources/serializers already used to render an artist/artwork/event/archive item for editing) rather
than building a new "creation diff" renderer, satisfying FR-004 for free.

**Rationale**: The existing diff engine (`ProposalDiffBuilder`, `computeDiffs`) is built entirely around
"proposed payload vs. live record's current values" and would need a fabricated all-blank baseline to
produce anything meaningful for a creation review — extra code whose entire job is to render exactly what
already renders correctly today when reading the record directly. Simpler to skip the diff machinery for
this one case rather than contort it.

**Alternatives considered**: comparing against an all-`NULL` synthetic record to force every field through
the existing diff renderer as "added." Rejected — real implementation cost (the diff engine isn't
type-generic; it's built per-entity around `ProposableFields`) to reproduce a screen that already exists.

## R3: How does the creator keep editing while awaiting review, and how does "changes requested" work?

**Decision**: While `creation_approved_at IS NULL` and the caller is the record's own creator, edits go
straight to the live record (the same direct-write endpoints creation already uses), **not** through the
section-payload draft pipeline. "Send for review" and "changes requested" are tracked on a dedicated
creation-review record (see R4) that carries status only — no `payload`/`field_diffs` of its own, since
there's nothing to diff (R2).

**Rationale**: The section-payload/diff system exists to protect a record's *current, already-reviewed*
state from being silently overwritten by an in-progress edit — there is no such state to protect for a
record nobody has approved yet. Routing pre-approval edits through the diff pipeline would recreate
exactly the "nothing differs from the current record" trap the bug report hit, since the live record
already holds what the creator wants by the time they'd get to "submit." Direct-editing a record only its
own creator can see or act on (R5/US3) has no safety property the diff system would add.

**Alternatives considered**: forcing creation-time edits through `EditorialDraftService.upsert()`/`payload`
too, treating "the blank row" as the diff baseline. Rejected for the same reason as R2 — it would need a
fabricated empty baseline, and would resurface the exact "nothing differs" 422 the moment the creator's
live record already matches their own draft (which it always will, since nothing else can write to an
unapproved record).

## R4: What represents "a pending creation," reusing existing infrastructure?

**Decision**: Reuse `EditProposal`/`ReviewQueueItem` (not a new table), with a new boolean
`is_creation` column on `edit_proposals`, `payload` and `field_diffs` left empty for this kind. Created
automatically, in the same transaction as the record itself, with `status = draft`. "Send for review"
flips it to `pending` (no diff/nothing-differs check applies — R3 already ensures there's always
something to review, since the whole record is the content). Approving it is a pure confirmation: no
values to apply (R1 — they're already on the record), just stamp `creation_approved_at = now()`, close
the queue item, and log the approval. Requesting changes behaves exactly like it does for an edit
proposal: `status = changes_requested`, a note, and the creator can resubmit.

**Rationale**: Reusing `EditProposal`/`ReviewQueueItem` keeps every piece of infrastructure this feature
would otherwise have to rebuild: the review queue screen, reviewer permission checks
(`review_queue.*`/`*.manage`), the "own proposal" guard, activity-log integration, and the
`review_type` derivation (`ProposableFields::reviewTypeFor()`, run once at submit-time against the
record's full field set — a creation proposal routes to whichever queue its content would route to as an
edit, with no new `ReviewType` case needed).

**Alternatives considered**: a dedicated `record_creations` table. Rejected — would need its own queue
screen, its own permission wiring, and its own audit trail, duplicating everything `EditProposal` already
does, for a "kind" that only really differs from an edit proposal by not carrying a diff.

## R5: Visibility — who can see/act on an unapproved record (US3)

**Decision**, per surface:
- **Publish/verify**: blocked outright by the publish gate for every type (R1) — already covers the
  strongest form of "not real yet."
- **Curation/edit page**: anyone with the record type's `*.manage` permission can already open it (no
  change — this mirrors how an in-progress edit draft is visible to `artists.manage` holders today, per
  the existing "own proposal" pattern), but sees a pending-creation state rather than a normal editable
  record if they are not its creator (US3 acceptance scenario 2) — reusing the same `DraftStatusBanner`
  "blocked"/pending states already built for edit conflicts.
- **Linked-entity pickers** (e.g. an artwork's artist picker): scoped to `creation_approved_at IS NOT
  NULL`, alongside whatever visibility scoping they already apply.
- **Staff registries** (e.g. the admin artists list): unaffected in *whether* an unapproved record
  appears (curation registries already show unpublished/draft records for staff to work on) but gets a
  visible "pending creation review" badge, reusing the same badge pattern as `publication_status`/
  `verified_status`.

**Rationale**: Minimizes new surface area — most of "nobody can treat it as established" already follows
mechanically from the publish gate (R1) and existing permission checks; the only genuinely new pieces are
the picker scoping and the registry badge.

## R6: Does this reach all four record types identically?

**Decision**: Yes, mechanically — `creation_approved_at` per type, a creation-flagged `EditProposal` per
type, reusing each type's existing `*.manage`/`review_queue.*` permissions and publish/verify gate.
Artists are the reference implementation (the reported case); artworks, events and archive items follow
the same shape against their own creation endpoints, publish gates and pickers.

**Rationale**: All four already share the identical asymmetry (direct-write creation vs. reviewed
editing) and the identical underlying `EditProposal`/`ReviewQueueItem` infrastructure — there's no
type-specific reason to diverge. Type-specific work is limited to: which endpoint currently does the
direct write, what that type's publish-gate function is called, and what its linked-entity pickers are
(archive items and events don't have an obvious "linked from elsewhere" picker the way an artist does —
scoped per type in `data-model.md`/`tasks.md`, not assumed uniform).
