# Feature Specification: Multiple Image Upload with Primary Image Selection

**Feature Branch**: `003-artwork-multi-image-upload` (spec directory; no git branch created — no branch hook configured. Renumbered from `001-` to resolve a collision with `001-admin-artwork-list-view`.)

**Created**: 2026-09-23

**Status**: Draft

**Input**: User description: "i want the artwork create page support multiple image upload and option to select the primary image that always shows as primary"

## Context: what exists today

The artwork create screen already lets a registrar attach images before saving, and already
has a notion of one designated image per artwork (currently labelled "final selected image").
Two gaps make the current flow slow and ambiguous:

1. **Images can only be picked one at a time.** The file chooser accepts a single file per
   interaction, so attaching ten photographs of one artwork means repeating the same
   pick-and-confirm loop ten times.
2. **The designated image is implicit, not guaranteed.** If nobody explicitly designates one,
   displays silently fall back to "whichever image was added first". Nothing tells the
   registrar which image will actually represent the artwork, and an artwork can be saved with
   no explicit designation at all.

This feature closes both gaps: select many images in one action, and always have exactly one
explicitly designated primary image.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Attach a whole shoot in one action (Priority: P1)

A registrar cataloguing a newly documented artwork has a folder of photographs of it (full
view, signature detail, verso, frame condition). While creating the artwork record, they open
the file chooser once, select all of the photographs together, and see every one of them
queued against the record before saving.

**Why this priority**: This is the bulk of the time cost in the current flow and the explicit
ask. It delivers value on its own even if primary selection behaviour is untouched, because the
existing designation mechanism continues to work as it does today.

**Independent Test**: Create an artwork, select four image files in a single file-chooser
interaction, confirm all four appear in the pending list with their filenames, save, and confirm
all four are attached to the saved artwork.

**Acceptance Scenarios**:

1. **Given** a registrar is filling in a new artwork, **When** they select four valid image files in one file-chooser interaction, **Then** all four appear in the pending image list with filename and dimensions, and none replaces another.
2. **Given** four images are already pending, **When** the registrar selects three more in a second interaction, **Then** all seven are pending, in the order they were added.
3. **Given** seven images are pending, **When** the registrar saves the artwork, **Then** the saved artwork has all seven images attached.
4. **Given** a selection containing a file that is not an accepted image type or exceeds the size limit, **When** the selection is made, **Then** the acceptable files are queued, each rejected file is named with the reason, and the rejection does not discard the accepted ones.
5. **Given** an image is already queued, **When** the registrar selects the byte-identical file again, **Then** it is not queued a second time and they are told by name that the image is already attached.
6. **Given** one selection contains the same file twice, **When** the selection is made, **Then** the image is queued once and the repeat is reported as already attached.

---

### User Story 2 - Decide which image represents the artwork (Priority: P1)

The registrar picks which of the attached images is the primary one — the image used wherever
the artwork is represented by a single picture. They can see at a glance which one is primary,
change their mind before saving, and trust that the choice they made is the image that appears
afterwards.

**Why this priority**: Equally explicit in the request ("always shows as primary"), and it is
what makes a multi-image artwork usable downstream — without a dependable primary, every
listing has to guess. Independently valuable even with single-file picking.

**Independent Test**: Attach three images one at a time, mark the third as primary, save, then
confirm the third image is the one shown for that artwork in listings and on its detail screen.

**Acceptance Scenarios**:

1. **Given** no images are attached yet, **When** the registrar attaches the first image, **Then** that image is automatically the primary one and is shown as primary without any further action.
2. **Given** three images are attached and the first is primary, **When** the registrar designates the third as primary, **Then** the third is shown as primary and the first is no longer marked primary.
3. **Given** exactly one image is marked primary, **When** the artwork is saved, **Then** that same image is the primary image on the saved artwork, and no other image is marked primary.
4. **Given** an artwork has images attached, **When** it is displayed anywhere that represents it with a single image, **Then** the image shown is its primary image.
5. **Given** the primary image is removed from the pending list, **When** other images remain, **Then** one of the remaining images becomes primary automatically and is shown as such.
6. **Given** the only attached image is removed, **When** no images remain, **Then** the artwork can still be saved and is represented by its no-image placeholder.

---

### User Story 3 - Fix an attachment mistake before committing (Priority: P2)

Before saving, the registrar reviews the queued images, removes the accidental duplicate or
the blurred frame, sets the rights status on each, and reorganises which one is primary —
all without having saved anything yet.

**Why this priority**: Correction is what makes bulk attachment safe to use; attaching ten
images at once raises the cost of a mistake. It builds on US1 but is separable — it can ship
after bulk selection is working.

**Independent Test**: Queue five images, remove two, change the rights status of one, change
which is primary, save, and confirm the saved artwork reflects exactly those decisions.

**Acceptance Scenarios**:

1. **Given** five images are pending, **When** the registrar removes two, **Then** three remain pending and the removed two are not attached on save.
2. **Given** images are pending, **When** the registrar sets a rights status on an individual image, **Then** that status is stored against that image on save and does not change the others.
3. **Given** the artwork record was created but some image attachments failed, **When** the failure is reported, **Then** each failed image is named, the successfully attached images are kept, and the registrar is taken to the saved artwork rather than losing their work.

---

### User Story 4 - Drag files onto the panel (Priority: P3)

The registrar drags a group of image files from their file manager straight onto the artwork's
image area instead of going through the file chooser.

**Why this priority**: Convenience on top of US1, with no new data behaviour. Purely additive
and safe to defer.

**Independent Test**: Drag three image files onto the image panel and confirm they queue
exactly as a multi-select through the file chooser would.

**Acceptance Scenarios**:

1. **Given** the registrar is on the artwork create screen, **When** they drop three image files onto the image panel, **Then** the three queue identically to a file-chooser multi-selection.
2. **Given** a drop contains a non-image file, **When** the drop completes, **Then** the image files queue and the non-image file is named as rejected.

---

### Edge Cases

- **The same file is selected twice** (in one selection, or across two selections): queued once, and the repeat is reported as already attached (FR-011).
- **A byte-identical image arrives under a different filename**: recognised by content rather than name, so it is still blocked as a duplicate and reported.
- **A visually similar but not byte-identical image** (re-exported, re-cropped, different compression) is *not* a duplicate and attaches normally — content matching is exact, not perceptual.
- **The attachment cap is reached**: selecting more images than an artwork may hold queues up to the cap, names the excess as rejected, and does not silently drop them.
- **Every file in a selection is invalid**: the registrar is told why, and nothing is queued.
- **The artwork record saves but every image attachment fails**: the artwork still exists, the registrar lands on it, and the failure is reported rather than the record appearing image-less with no explanation.
- **Removing the primary image** re-designates another automatically (never leaves images attached with no primary).
- **An image is attached but its rights are not cleared, or the artwork is unpublished**: the image is still the artwork's primary image for internal screens, but it remains non-public — image visibility rules are unchanged by this feature.
- **The registrar abandons the screen with images queued**: nothing is uploaded and nothing is orphaned.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Registrars MUST be able to select multiple image files in a single file-chooser interaction while creating an artwork.
- **FR-002**: Every valid file in a multi-file selection MUST be queued; a later selection MUST add to the queue rather than replace it.
- **FR-003**: The system MUST show each queued image with enough identifying detail to tell them apart (preview, filename, pixel dimensions).
- **FR-004**: Each file in a selection MUST be validated independently, and invalid files MUST be reported individually by name and reason without discarding the valid ones in the same selection.
- **FR-005**: An artwork with at least one image attached MUST always have exactly one image designated primary — never zero, never more than one.
- **FR-006**: The first image attached to an artwork MUST become its primary image automatically, with no extra action from the registrar.
- **FR-007**: Registrars MUST be able to designate any attached image as primary, which removes that designation from the previously primary image.
- **FR-008**: The primary designation MUST be visually distinguishable in the image list at a glance, and the primary image MUST be the one shown in the panel's main preview.
- **FR-009**: Removing the primary image MUST automatically designate one of the remaining images as primary; removing the last image MUST leave the artwork with no images and no primary.
- **FR-010**: The primary image chosen before saving MUST be the primary image of the saved artwork, and MUST be the image used wherever the artwork is represented by a single image.
- **FR-011**: The system MUST recognise when a selected image is byte-identical to one already attached to (or already queued against) the same artwork, MUST NOT attach the duplicate, and MUST tell the registrar by name that the image is already attached. A duplicate MUST never be discarded silently, and MUST never result in two copies of the same image on one artwork.
- **FR-012**: Registrars MUST be able to remove any queued image before saving, and MUST be able to set a rights status per individual image.
- **FR-013**: When the artwork record is created but one or more image attachments fail, the system MUST keep the successfully attached images, name each failure, and leave the registrar on the saved artwork.
- **FR-014**: The system MUST enforce a maximum number of images per artwork and report clearly when a selection would exceed it.
- **FR-015**: Attaching images MUST NOT change an artwork's or an image's publication or rights state; newly attached images MUST remain non-public until rights are cleared and the artwork is published.
- **FR-016**: The designated image MUST be called the **primary** image in all user-facing wording, in both Arabic and English, on every screen that exposes it — replacing the current "final selected image" / "make final" wording. No screen may present the two names for the same concept.

### Key Entities

- **Artwork**: The catalogued work being created. Holds zero or more images; represented by a single primary image wherever one picture is needed (listings, cards, detail headers).
- **Artwork Image**: One photograph of an artwork. Carries its own identifying detail (filename, pixel dimensions, file size), its own rights status, a content fingerprint used to recognise byte-identical duplicates, and a flag marking whether it is the artwork's primary image. At most one image per artwork carries that flag.
- **Pending Image**: An image chosen but not yet committed, existing only while the create screen is open. Carries the same rights status and primary designation as a committed image so choices made before saving survive the save.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A registrar can attach ten images to a new artwork and save it in a single pass through the screen, in under two minutes.
- **SC-002**: Attaching ten images requires one file-chooser interaction, down from ten.
- **SC-003**: 100% of saved artworks that have at least one image have exactly one primary image — no artwork is left with an ambiguous or unset primary.
- **SC-004**: For 100% of artworks, the image a registrar designated as primary during creation is the image displayed for that artwork in listings and on its detail screen.
- **SC-005**: When part of a batch fails to attach, 100% of the successfully attached images are retained and every failure is named to the registrar.
- **SC-006**: No image attached through this flow becomes publicly visible without its rights being cleared and its artwork being published.
- **SC-007**: Registrars can tell which image is primary without clicking into anything, verified by having someone unfamiliar with the screen identify the primary image on first look.
- **SC-008**: No artwork ends up with two byte-identical images attached, and every blocked duplicate is reported to the registrar by name rather than disappearing without explanation.
- **SC-009**: The word "primary" is the only term used for the designated image across all screens and both languages — zero occurrences of the older "final" wording remain in user-facing text.

## Assumptions

- **Accepted formats and size limits are unchanged** from what the artwork image upload already enforces (JPEG, PNG and WebP, up to 20 MB per file); this feature changes how many files can be chosen at once, not what counts as a valid image.
- **Maximum 20 images per artwork.** No cap exists today; 20 is a working figure chosen to keep the screen usable and bound the upload burst. Adjustable before implementation.
- **The first attached image becomes primary automatically**, which is how "always shows as primary" is interpreted: there is never a state where images exist without a designated primary. This replaces today's implicit "designated one, else whichever was added first" fallback with an explicit designation.
- **Existing save mechanics are kept**: the artwork record is created first and images attach afterwards, so a partial image failure leaves a real artwork record rather than losing the catalogue data.
- **Duplicate matching is exact, not perceptual.** Two images count as duplicates only when their
  contents are byte-for-byte identical. A re-exported, re-cropped or recompressed version of the
  same photograph is treated as a distinct image, because judging visual similarity is a different
  and much larger problem.
- **The "primary" rename is user-facing wording only** (both Arabic and English, on the create and
  curation screens). The internally stored field keeps its current name; renaming stored data was
  considered and deliberately excluded to keep this feature from pulling in a migration and the
  merge/listing code that reads that field.
- **Image reordering and sorting are out of scope.** Only which image is primary is controllable; the rest keep their attachment order.
- **Thumbnail generation and image resizing remain out of scope** — originals continue to be served as they are today.
- **The image panel is shared with the artwork detail/curation screen**, so the multi-select control and primary-image behaviour will appear on both screens. This is treated as desirable consistency rather than scope creep; the request named the create screen, and the detail screen inherits the same improvement.
- **Existing artworks are unaffected** by the "always has a primary" rule until someone edits their images; no retrospective migration of already-saved artworks is assumed. Displays continue to fall back gracefully for them.
- **Image visibility rules are pre-existing and unchanged**: an image is public only when its rights are licensed or public domain and its artwork is published.

## Dependencies

- Relies on the existing artwork image attachment, per-image rights, and designated-image capabilities; this feature extends them rather than introducing a new image store.
- Relies on the existing content-fingerprint (checksum) captured per image for duplicate recognition (FR-011).
- The project's privacy rules govern this feature: duplicates must be reported rather than silently rejected, and nothing auto-publishes.
