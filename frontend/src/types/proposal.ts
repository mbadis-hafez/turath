import type { Bilingual } from "@/types/artist";
import type { PaginationMeta } from "@/types/api";

export const PROPOSAL_STATUSES = ["pending", "approved", "rejected", "superseded"] as const;
export type ProposalStatus = (typeof PROPOSAL_STATUSES)[number];
export const REVIEW_TYPES = ["editorial_review", "archivist_review", "data_audit", "second_source_needed"] as const;
export type ReviewType = (typeof REVIEW_TYPES)[number];
export type RecordType = "artists" | "artworks" | "archive-items" | "events";

export interface FieldDiff {
  old_value_at_proposal_time: unknown;
  proposed_value: unknown;
}

export interface ProposalConflict {
  field: string;
  proposed_against: unknown;
  current: unknown;
  proposed_value: unknown;
}

export interface ProposedCitation {
  field_key: string;
  claimed_value?: unknown;
  source_id?: string;
  new_source?: Record<string, unknown>;
}

export interface Proposal {
  id: string;
  record: { type: RecordType | null; id: number; label: string | null };
  status: ProposalStatus;
  review_type: ReviewType;
  field_diffs: Record<string, FieldDiff>;
  field_labels: Record<string, Bilingual>;
  rationale: string;
  proposed_citations: ProposedCitation[];
  proposed_by: { id: number; name: string } | null;
  reviewed_by: { id: number; name: string } | null;
  reviewed_at: string | null;
  review_note: string | null;
  resulting_revision_id: string | null;
  created_at: string;
  conflicts?: ProposalConflict[];
}

export type RevisionSource = "direct_edit" | "approved_proposal" | "rollback";

export interface Revision {
  id: string;
  revision_number: number;
  source: RevisionSource;
  edit_proposal_id: string | null;
  field_diffs: Record<string, { old: unknown; new: unknown }>;
  field_labels: Record<string, Bilingual>;
  applied_by: { id: number; name: string } | null;
  applied_at: string;
  reverted_by_revision_id: string | null;
}

export interface ProposalsPage {
  data: Proposal[];
  meta: PaginationMeta;
}
