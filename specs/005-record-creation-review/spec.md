# Feature Specification: Creating a record requires review, same as editing one

**Feature Branch**: `005-record-creation-review`

**Created**: 2026-09-23

**Status**: Draft

**Input**: User description: "New artist creation should go through the same editorial-review pipeline as edits do. Today an editor's createArtist() call is a direct live write (the record is created with publication_status=draft, but the creating editor can immediately flip it to published/verified with no reviewer involvement at all) — this is inconsistent with editing an existing artist, where every change (even by the same editor) is staged as an EditProposal draft and requires a reviewer to approve it via the review queue before it takes effect. A user reported this as confusing: after creating a new artist, the 'Send for review' button/banner doesn't appear at all, because no draft proposal was ever created for the brand-new record."

**Scope decision**: covers all four record types that share this asymmetry today — **artists** (the
reported case), **artworks**, **events** and **archive items**. Each has its own direct-write "create"
flow today and its own existing edit-review pipeline; this feature closes the same gap for each. The
scenarios below are written generically ("a new record") and apply to all four types, using whichever
finalization action already exists per type (verification for artists, publication for the others) where
a scenario says "publish/finalize."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - An editor creates a new record and sends it for review (Priority: P1)

An editor fills out a "new [artist/artwork/event/archive item]" form and saves. Today this immediately
creates a real, directly-editable record with no reviewer involved — inconsistent with every other change
an editor makes to that same record type, which is staged as a draft and requires review. Instead, saving
should stage the new record the same way editing an existing one does: the editor sees the familiar "send
for review" step, and nothing about the new record is treated as final until a reviewer approves it.

**Why this priority**: This is the reported problem and the reason the "send for review" control was
missing entirely for a newly created record — the core gap this feature closes.

**Independent Test**: For each record type, create a new record as an editor, confirm no reviewer-facing
finalized state is reachable without going through review, submit it, and confirm it appears in the
review queue exactly like a change to an existing record of that type would.

**Acceptance Scenarios**:

1. **Given** an editor has just created a new record, **When** they view its curation/edit page, **Then**
   they see the same draft/"send for review" controls used for editing an existing record of that type —
   not a hint that there's nothing to submit.
2. **Given** an editor has created a new record and sent it for review, **When** they check the review
   queue, **Then** the new record's creation appears there as an item a reviewer can act on.
3. **Given** an editor has created a new record but not yet sent it for review, **When** they (or anyone
   without review authority) try to finalize/publish it, **Then** the system refuses, the same way it
   already refuses to finalize an incomplete record.

---

### User Story 2 - A reviewer approves or requests changes to a brand-new record (Priority: P1)

A reviewer opens their queue and finds a newly created record waiting for their decision, alongside the
edit proposals they already review. They need to see what's being proposed — since there is no prior
version of this record to compare against, they see the proposed content directly rather than a diff.
They can approve it (making the record real and reachable through the ordinary curation workflow) or
request changes (sending it back to the editor with a note, exactly like today's "request changes" on an
edit).

**Why this priority**: Without a working reviewer-side experience, User Story 1 has nowhere to go —
editors could submit, but nothing would ever come of it.

**Independent Test**: As a reviewer, open a pending new-record submission of each type, confirm the
proposed content renders clearly without a diff view, approve it, and confirm the record becomes a
normal, fully editable record afterward.

**Acceptance Scenarios**:

1. **Given** a new record has been submitted for review, **When** a reviewer opens it, **Then** they see
   the proposed fields presented clearly, without being shown as a diff against something that doesn't
   exist yet.
2. **Given** a reviewer approves a newly submitted record, **When** the approval completes, **Then** the
   record becomes an ordinary record: editable, and eligible for finalization/publication through the
   existing rules for that record type.
3. **Given** a reviewer requests changes on a newly submitted record, **When** the request is sent,
   **Then** the editor who created it sees the same "changes requested" state and reviewer note they
   would see on a rejected edit to an existing record, and can revise and resubmit.

---

### User Story 3 - Nobody outside the editor and assigned reviewers can see or act on an unapproved new record (Priority: P2)

Until a reviewer approves it, a newly created record is not a real, citable, publishable part of the
archive. It doesn't appear in public listings (it wouldn't have anyway, being unpublished), doesn't
appear as a completed record to other staff browsing a registry expecting only reviewed material, and
cannot be linked to from other records as though it were established.

**Why this priority**: Closes the loophole the bug report surfaced — that a record could exist and be
published without ever passing through review — but is secondary to making the creation-then-review flow
work at all (User Stories 1–2).

**Independent Test**: Create and submit a new record of each type without approval, confirm it cannot be
published, finalized, or linked to as an established record by anyone other than its creator and eligible
reviewers.

**Acceptance Scenarios**:

1. **Given** a newly created record has not yet been approved, **When** anyone attempts to publish or
   finalize it, **Then** the action is refused with an explanation, regardless of what permissions they
   hold.
2. **Given** a newly created record has not yet been approved, **When** another editor (not its creator)
   opens its curation/edit page, **Then** they see its pending-review state, not an editable record with
   no history.

---

### Edge Cases

- What happens if the editor never sends the new record for review — does an abandoned, unsubmitted
  creation linger indefinitely, and is that acceptable, or does it need a discard path (mirroring the
  existing "discard draft" action available on edits)?
- What happens if a reviewer requests changes and the editor never revises it — same question, applied
  to a stalled resubmission.
- What happens to file/image uploads made during the creation form (portrait, artwork images, archive
  files), before the record is approved — are they visible/usable immediately, or held back with
  everything else until approval?
- Does the record's slug/URL get reserved at creation time (risking a squatted, never-approved slug) or
  only assigned on approval?
- Events and archive items may have their own type-specific finalization step distinct from artist
  "verification" (e.g. archive item publication/access level) — this feature must slot into whichever
  finalization mechanism already exists per type, not invent a new one.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST treat creating a new artist, artwork, event or archive item the same way it
  treats editing an existing one of that type: staged as a draft that requires a reviewer's approval
  before it takes effect, rather than a direct write.
- **FR-002**: An editor MUST see the same "send for review" control on a newly created record's
  curation/edit page that they see when editing an existing record of that type, once they have entered
  content worth submitting.
- **FR-003**: A submitted new-record creation MUST appear in the review queue, using the same queue and
  reviewer-facing mechanics (assignment, request-changes-with-note, approval) already used for edits to
  existing records of that type.
- **FR-004**: When a reviewer opens a pending new-record submission, the system MUST present the proposed
  content directly (not as a diff against a prior version, since none exists).
- **FR-005**: Approving a new-record submission MUST make it a fully ordinary record afterward: editable
  through the standard curation workflow, and eligible for finalization/publication under the existing
  rules for that record type — no different from a record that has always existed.
- **FR-006**: The system MUST refuse to publish or finalize a newly created record that has not yet been
  approved by a reviewer, regardless of who attempts it or what permissions they hold, with an
  explanation distinguishing this from other publish refusals (e.g. incomplete record).
- **FR-007**: Requesting changes on a new-record submission MUST behave the same way it does for an edit
  to an existing record: the editor sees the reviewer's note and a "changes requested" state, and can
  revise and resubmit.
- **FR-008**: The system MUST NOT let a newly created, not-yet-approved record be treated as an
  established record by anyone other than its creator and eligible reviewers (e.g. selectable as a linked
  entity elsewhere, appearing in staff-facing listings that assume reviewed material).
- **FR-009**: This feature applies to all four record types with a curation/edit-review pipeline today:
  artists, artworks, events and archive items. Each is delivered as its own independently testable slice
  (per the user stories above), so the four can ship in sequence rather than all at once, but all four
  are in scope.

### Key Entities

- **Artist / Artwork / Event / ArchiveItem**: The four record types this applies to. Each is created
  immediately today via its own direct-write endpoint; this feature changes what "created" means before a
  reviewer has acted, for all four.
- **EditProposal (creation kind)**: The existing draft/review mechanism, extended to represent "this
  record doesn't exist yet, and this is what it should be" rather than only "this is how an existing
  record should change." Whether this reuses the existing `EditProposal` model/table as-is, with a
  creation-specific interpretation of "no live counterpart," or needs a distinct representation, is a
  design question for planning — the spec's requirement is behavioral parity with the edit flow, not a
  particular data shape.
- **Review queue item**: Already exists for edit proposals; a new-record submission must appear here the
  same way, for each of the four types.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An editor creating a new record of any of the four types can, in the same session and
  without confusion, reach a working "send for review" action — the reported gap (button not appearing
  at all) no longer occurs for any of them.
- **SC-002**: 100% of records (across all four types) that become published/finalized after this feature
  ships have a reviewer approval on record for their creation, with zero exceptions reachable through the
  ordinary UI or API.
- **SC-003**: A reviewer can act on a new-record submission (approve or request changes), for any of the
  four types, without needing a different mental model than reviewing an edit — measured by reusing the
  existing review-queue screen rather than introducing a parallel one.
- **SC-004**: No existing edit-review workflow, for any record type, changes behavior or regresses as a
  result of this feature.

## Assumptions

- The existing editorial-draft/review pipeline (draft → pending → approved/changes-requested) is the
  right mechanism to extend for all four record types, rather than building a separate approval system
  for creation. The spec requires behavioral parity with editing; how that's implemented, and whether all
  four types are delivered together or in sequence, is for `/speckit-plan`.
- "Reviewer" means the same population that already reviews edit proposals for each record type today
  (holders of the relevant review-queue permission per type) — no new reviewer role is introduced by this
  feature.
- Attachments made during creation (portrait, artwork images, archive files) are held to the same
  not-final-until-approved standard as the rest of the record, unless a design reason emerges during
  planning to treat them differently.
- This feature does not change anything about how *editing an already-approved, existing* record works,
  for any type — that pipeline is correct today and is the behavioral target, not something being
  modified.
- Archive items and events may finalize under a different name than artist "verification" (e.g.
  publication, access level); this feature adapts to each type's existing finalization step rather than
  requiring them to converge on one shared mechanism.
