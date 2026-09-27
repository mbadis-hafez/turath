# Feature Specification: Condition Report Review Queue Visibility

**Feature Branch**: `[002-condition-report-review-queue]`

**Created**: 2026-09-22

**Status**: Draft

**Input**: User description: "Fatma (editor) imported a condition report from the archive and linked it to the artwork 'Easter', then sent it to review — but Samar (reviewer) does not see it under Review queue"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Reviewer Sees and Can Process Archive-Sourced Submissions (Priority: P1)

An editor (Fatma) imports a condition report document from the archive, links it to an artwork ("Easter"), and sends it to review. A reviewer (Samar) opens her Review queue and must find this submission — with enough context (what was submitted, who submitted it, which artwork it concerns, when) — and must be able to record the review outcome. Today the submission effectively disappears: it does not appear under the reviewer's Review queue, so the document never gets reviewed.

**Why this priority**: This is the reported bug and a data-integrity risk — submissions are silently lost. Nothing else matters until every "sent to review" item reliably reaches an eligible reviewer.

**Independent Test**: Can be fully tested by importing an archive document, linking it to an artwork, submitting it for review, then logging in as a reviewer and verifying the item appears in the review queue with its context and can be processed to an outcome.

**Acceptance Scenarios**:

1. **Given** a contributor has imported a condition report from the archive, linked it to the artwork "Easter", and pressed "send to review", **When** a reviewer with the matching review permission opens her review queue, **Then** the submission appears as a pending item showing the document title, the submitter, the submission date, the linked artwork, and the review type.
2. **Given** the reviewer sees the pending submission, **When** she opens it, **Then** she can inspect the linked artwork and document details needed to judge the submission.
3. **Given** the reviewer has inspected the submission, **When** she records an outcome, **Then** the item leaves the pending queue (or is visibly marked resolved) and the outcome is stored with her identity and timestamp.
4. **Given** a submission was sent to review, **When** any eligible reviewer (not just one specific person) opens the queue, **Then** the item is visible to every reviewer holding the matching review permission.

---

### User Story 2 - Submitter Can Track the Review State of Their Submission (Priority: P2)

The editor (Fatma) who sent the condition report to review can see, from the artwork or document screen, that the submission is pending review, and sees it update when a reviewer processes it — instead of having no signal about whether anyone received it.

**Why this priority**: Restores trust in the "send to review" action; without status feedback contributors re-submit or escalate. Secondary to making the queue work at all.

**Independent Test**: Can be tested by submitting an item for review, then checking the submitting user's view shows a "pending review" state that changes once a reviewer processes the item.

**Acceptance Scenarios**:

1. **Given** Fatma has sent a linked document to review, **When** she revisits the artwork/document screen, **Then** she sees a pending-review indicator associated with her submission.
2. **Given** a reviewer has processed the submission, **When** Fatma revisits the screen, **Then** the indicator shows the recorded outcome (and who recorded it).
3. **Given** a submission could not reach any reviewer (no eligible permission exists), **When** Fatma sends it to review, **Then** she is warned immediately instead of the submission being silently dropped.

---

### Edge Cases

- What happens when the linked artwork is merged into another record or deleted after submission? The queue item must remain visible with a clear indication of what happened to the link target.
- What happens when the same document is submitted to review twice? The reviewer must see distinct, deduplicated or clearly versioned queue entries — never silently merge or drop one.
- What happens when a reviewer's permissions are changed while items are pending? Pending items must not silently disappear for remaining eligible reviewers, and the system must not error for the reviewer who lost access.
- What happens when the submitter withdraws or edits the document while it is pending review? The reviewer must see an up-to-date snapshot or a clear "changed since submission" marker.
- What happens when no reviewer permission exists for the submission's review type? The submitter must be told at submission time (see US2 scenario 3).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Submitting any archive-sourced document (condition reports included) that is linked to a record for review MUST create exactly one review-queue entry reachable by reviewers holding the matching review permission.
- **FR-002**: Every pending review-queue entry MUST appear in the single review queue that reviewers use for all pending reviews, regardless of which screen or flow produced the submission (archive import, curation draft, or field proposal).
- **FR-003**: Each queue entry MUST expose at minimum: the submitted document's title, the submitter's identity, the submission timestamp, the linked record (artwork), and the review type.
- **FR-004**: A reviewer MUST be able to record an outcome on such an entry using the same outcome actions the Review queue already provides for its other pending items (consistent with how curation drafts and field proposals are reviewed) — an archive-sourced submission MUST NOT be limited to a lesser action such as acknowledge-only.
- **FR-005**: Recording an outcome MUST store the reviewer's identity and timestamp, and MUST remove the entry from the pending queue or visibly mark it resolved.
- **FR-006**: The submitter MUST be able to see the review state (pending / outcome) of their submission from the record screen, and MUST be warned at submission time if no reviewer can receive it.
- **FR-007**: The fix MUST apply to all archive-item review submissions, not only condition reports; the condition-report-on-artwork flow is the reported instance and MUST be covered.
- **FR-008**: Pending queue entries MUST NOT silently disappear due to permission or role changes; visibility is governed solely by holding the matching review permission.

### Key Entities

- **Archive document (condition report)**: An archival item imported into the system, carrying a title, source metadata, and a link to the record it documents.
- **Artwork link**: The association between an archive document and the artwork it concerns; the submitted unit of review is the document together with its link.
- **Review queue entry**: A pending-review item created by a submission, carrying submitter, timestamp, review type, linked record, and (once processed) the outcome with reviewer identity.
- **Submitter**: The editor (e.g., Fatma) who imported/linked the document and sent it to review.
- **Reviewer**: Any user (e.g., Samar) holding the review permission matching the entry's review type; not a specific assignee.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of submissions sent to review appear in the review queue of at least one eligible reviewer within 1 minute of submission; zero submissions are silently lost.
- **SC-002**: A reviewer can locate, understand, and record an outcome for a submitted archive-linked document in a single session without asking the submitter for context.
- **SC-003**: The reported reproduction (import condition report → link to artwork → send to review → reviewer logs in) succeeds end-to-end for 100% of attempts.
- **SC-004**: Submitters receive clear feedback at submission time when no reviewer can receive their submission (0 silent drops).
- **SC-005**: No regression: all existing review flows (curation drafts, field proposals, material intake) continue to reach reviewers exactly as before.

## Assumptions

- Reviewers see the queue by permission, not by individual assignment — any reviewer with the matching review permission is eligible; no per-item assignee concept exists or is introduced.
- "The queue" the user refers to is the reviewer's pending-review work queue (the same place other pending reviews appear), not a per-person notification list.
- The scope is the visibility/actionability gap for archive-sourced review submissions; the condition-report-import-on-artwork flow is the concrete reproduction and must be covered, but the fix is not limited to that one document type.
- The submitter's permission model and the reviewer role (e.g., Samar) already exist; this feature changes no roles or permissions, only whether submissions reliably reach reviewers.
- Both the archive import flow and the artwork-linking flow are existing, working capabilities; this feature does not change how documents are imported or linked, only what happens at "send to review".
