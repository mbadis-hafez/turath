import type { Bilingual } from "@/types/artist";
import type { PaginationMeta } from "@/types/api";

export const SUBMITTER_ROLES = ["artist", "artist_family", "private_collection", "association", "researcher", "other"] as const;
export type SubmitterRole = (typeof SUBMITTER_ROLES)[number];

export const SUBMISSION_STATUSES = [
  "submitted", "initial_review", "cataloging", "authorization_pending", "published", "rejected", "withdrawn",
] as const;
export type SubmissionStatus = (typeof SUBMISSION_STATUSES)[number];
export type LetterStatus = "not_started" | "pending" | "signed" | "not_applicable";

/** D154: what the public form accepts, mirrored client-side as a courtesy; the server is the real gate. */
export const ACCEPTED_TYPES = ["image/jpeg", "image/tiff", "application/pdf", "audio/mpeg"] as const;
export const ACCEPTED_EXTENSIONS = [".jpg", ".jpeg", ".tif", ".tiff", ".pdf", ".mp3"] as const;
export const MAX_TOTAL_BYTES = 2 * 1024 * 1024 * 1024;

export interface SubmissionRow {
  id: number;
  submitter_name: string;
  submitter_contact: string;
  submitter_role: SubmitterRole;
  city: string | null;
  description: string;
  status: SubmissionStatus;
  file_count: number;
  submitted_at: string;
}

export interface StagedFile {
  id: number;
  name: string | null;
  mime_type: string;
  size_bytes: number;
  url: string;
}

export interface SubmissionDetail extends SubmissionRow {
  linked_artist: { id: number; name: Bilingual } | null;
  linked_artwork: { id: number; title: Bilingual } | null;
  authorization_letter_status: LetterStatus;
  staff_notes: string | null;
  reviewed_at: string | null;
  files: StagedFile[];
  archive_items: { id: number; title: Bilingual; item_type: string }[];
}

export interface CatalogItem {
  item_type: string;
  title: { ar: string | null; en: string | null };
  file_ids: number[];
}

export interface SubmissionsPage {
  data: SubmissionRow[];
  meta: PaginationMeta;
}
