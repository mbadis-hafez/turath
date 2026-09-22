/** Record-type manage permissions: full editorial access, including reviewing any proposal on those types. */
const RECORD_MANAGE_PERMISSIONS = ["artists.manage", "artworks.manage", "archive.manage", "events.manage"];

/** Review-queue permissions: working a specific reviewer queue (mirrors the backend ReviewType::permission()). */
const REVIEW_QUEUE_PERMISSIONS = [
  "review_queue.archivist_review",
  "review_queue.data_audit",
  "review_queue.second_source_needed",
  "review_queue.editorial_review",
  "review_queue.material_intake",
];

/** Anyone who can act on at least one proposal sees the review queue instead of just "my suggestions". */
export function canReviewProposals(can: (permission: string) => boolean): boolean {
  return [...RECORD_MANAGE_PERMISSIONS, ...REVIEW_QUEUE_PERMISSIONS].some(can);
}
