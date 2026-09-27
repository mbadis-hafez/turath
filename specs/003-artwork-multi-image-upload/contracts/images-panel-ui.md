# Contract: Shared Artwork Images Panel

`frontend/src/components/curation/ArtworkImagesPanel.vue`

This one component is rendered by **both** the artwork create screen and the artwork curation
screen. That is why the feature lands on both (spec Assumptions) — and it is why the component's
props/events contract is the real integration boundary, not either page.

## Current contract

```ts
props:  { images: ArtworkImage[]; busy?: boolean }
emits:  {
  upload: [file: File, rights: ImageRights]   // exactly one file
  final:  [id: number]
  rights: [id: number, rights: ImageRights]
  remove: [id: number]
}
```

The two pages adapt it differently:

| | Create screen | Curation screen |
|---|---|---|
| `images` | synthesised from in-memory `Pending[]` with negative/local ids | real `ArtworkImage[]` from the API |
| `upload` | queues locally; uploads after the artwork is created | uploads immediately |
| `final` | flips a local flag | `PATCH … {is_final: true}` |
| `remove` | drops from the queue | `DELETE …` |

## Changed contract

```ts
props:  { images: ArtworkImage[]; busy?: boolean; max?: number }
emits:  {
  upload: [files: File[], rights: ImageRights]   // CHANGED: many files
  final:  [id: number]                            // unchanged (wire name kept)
  rights: [id: number, rights: ImageRights]       // unchanged
  remove: [id: number]                            // unchanged
}
```

### Requirements on the component

1. **Multi-select** — the file input carries `multiple`; `onFile` emits *every* selected file in one
   `upload` event rather than `files[0]` (FR-001). The input is still cleared after each selection so
   re-picking the same file fires a fresh `change`.
2. **Additive queueing** — emitting `upload` must never be interpreted as "replace"; the pages append
   (FR-002).
3. **Primary is visible at a glance** — the designated image carries a badge, and the panel's main
   preview shows it (FR-008). Wording comes from the reworded
   `curation.artworkImages.*` keys ("primary", not "final").
4. **Per-image controls stay per-image** — rights select, "make primary" action and remove button,
   one set per row (FR-012).
5. **`max`** — when supplied, the panel tells the registrar the cap and stops offering more uploads
   once reached (FR-014). Server remains authoritative.
6. **Drop zone (P3, US4)** — dropping files emits the same `upload` event with the same array shape,
   so the pages need no additional handling.
7. **RTL-safe** — logical Tailwind properties only (`ms-`/`me-`/`ps-`/`pe-`/`start-`/`end-`), per D12.
   The existing markup already complies; new markup must too.

### Requirements on the pages

**Create screen** (`ArtworkCreatePage.vue`)
- Append every file from an `upload` event to `pending`, applying the client-side
  `name + size + lastModified` duplicate check (research R2) and reporting any skipped file.
- Keep the existing already-correct behaviours: first queued image is primary
  (`isFinal: pending.length === 0`), and removing the primary promotes another.
- On save, upload the queue in **one** batch call, then designate the primary using
  `results[].image_id` — deleting the current `Math.max(...list.map(i => i.id))` guess (research R1).
- Keep the existing partial-failure behaviour: the artwork exists, failures are named, the registrar
  lands on the saved artwork rather than losing the record (FR-013).

**Curation screen** (`ArtworkCurationPage.vue`)
- Pass the batch straight to `uploadArtworkImages()`; surface per-file `results` entries.
- Keep `onMakeFinal` as-is (the wire field is unchanged).
- The `final-image` prop passed onward to the checklist keeps its name; only its label wording moves
  to "primary".

## Test surface

| Spec file | Must cover |
|---|---|
| `ArtworkCreatePage.spec.ts` | selecting 3 files in one `change` queues 3; re-picking the same file is skipped with a message; first queued is primary; removing the primary promotes another; save issues one batch call and designates primary from `results` |
| `ArtworkCurationPage.spec.ts` | batch upload reaches the API; per-file `results` surface; `is_final` PATCH still fires on "make primary" |
| i18n parity test (existing) | `ar` and `en` `artworkImages.*` keys stay in lockstep after the rewording |
