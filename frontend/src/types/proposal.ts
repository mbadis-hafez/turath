import type { Bilingual } from "@/types/artist";
import type { PaginationMeta } from "@/types/api";
import type { CurationUpdate, DateValue, Localized, ProfileEntry, SocialLink } from "@/types/artistCuration";

export const PROPOSAL_STATUSES = ["draft", "pending", "changes_requested", "approved", "rejected", "superseded"] as const;
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
  /** A collection (e.g. contacts) that changed since the draft was written: no values, the section diff shows them. */
  collection?: boolean;
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
  /** True for a record's creation-review item — no field_diffs/payload; the record's current values are the proposed content (005). */
  is_creation: boolean;
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
  /** Per-type draft sections (editorial drafts only): each section matches the body of the corresponding direct endpoint. */
  payload?: Record<string, unknown>;
}

/** PATCH /api/v1/artists/{id} body as stored in a draft's `fields` section. */
export interface ArtistDraftFields {
  name?: Localized;
  bio?: Localized;
  birth?: DateValue | null;
  death?: DateValue | null;
  nationality?: Localized;
  classification?: Localized;
  living_status?: "unknown" | "living" | "deceased";
  legacy_code?: string;
  publication_status?: string;
}

/** Artist draft payload: sections are optional; each matches the corresponding direct endpoint's body. */
export interface ArtistDraftPayload {
  fields?: ArtistDraftFields;
  educations?: ProfileEntry[];
  activities?: ProfileEntry[];
  social_links?: SocialLink[];
  curation?: CurationUpdate;
}

export type RevisionSource = "direct_edit" | "approved_proposal" | "rollback" | "audit";

export interface Revision {
  id: string;
  revision_number: number | null;
  source: RevisionSource;
  edit_proposal_id: string | null;
  field_diffs: Record<string, { old: unknown; new: unknown }>;
  field_labels: Record<string, Bilingual>;
  applied_by: { id: number; name: string } | null;
  applied_at: string;
  reverted_by_revision_id: string | null;
  /** Audit-log entries (child rows, encrypted values): informational only, never revertible. */
  event?: string | null;
  description?: string | null;
  subject_label?: string | null;
  edit_summary?: string | null;
  contacts_changed?: Record<string, number> | null;
}

export interface ProposalsPage {
  data: Proposal[];
  meta: PaginationMeta;
}

/** PATCH /api/v1/artworks/{id} body as stored in a draft's `fields` section. */
export interface ArtworkDraftFields {
  title?: Localized;
  is_untitled?: boolean;
  category?: string;
  artist_id?: number | null;
  holder_id?: number | null;
  medium?: Localized;
  signed?: string;
  dimensions?: { height_cm?: number | null; width_cm?: number | null; depth_cm?: number | null };
  frame_dimensions?: { height_cm?: number | null; width_cm?: number | null; depth_cm?: number | null };
  weight_kg?: number | null;
  edition_number?: string | null;
  edition_size?: number | null;
  holder_inventory_no?: string | null;
  inventory_by_owner?: string | null;
  condition_report_link?: string | null;
  condition_report_status?: string | null;
  image_quality?: string | null;
  editing_status?: string | null;
  notes?: Localized;
  material_classification?: string;
  conservation_risk_note?: string | null;
  creation?: DateValue | null;
}

/** One pipeline toggle; the backend applies each via PipelineService. */
export interface ArtworkPipelineStageDraft {
  stage_key: string;
  status?: "not_started" | "in_progress" | "tbc" | "done" | "not_applicable";
  note?: string | null;
  linked_file_id?: number | null;
}

/** Artwork draft payload: sections are optional; each matches the corresponding direct endpoint's body. */
export interface ArtworkDraftPayload {
  fields?: ArtworkDraftFields;
  pipeline?: ArtworkPipelineStageDraft[];
}

/** PATCH /api/v1/events/{id} body as stored in a draft's `fields` section. */
export interface EventDraftFields {
  event_type?: string;
  title?: Localized;
  description?: Localized;
  venue_name?: string | null;
  city?: string | null;
  holder_id?: number | null;
  date_note?: string | null;
  start?: DateValue | null;
  end?: DateValue | null;
}

/** One participant row as sent by the participants sync endpoint. */
export interface EventParticipantDraft {
  id?: number;
  type: "artist" | "artwork";
  participant_id: number;
  role: string;
  note: string | null;
}

/** Event draft payload: `fields` mirrors PATCH /events/{id}; `participants` mirrors the participants sync body. */
export interface EventDraftPayload {
  fields?: EventDraftFields;
  participants?: EventParticipantDraft[];
}

/** PATCH /api/v1/archive-items/{id} body as stored in a draft's `fields` section. */
export interface ArchiveItemDraftFields {
  item_type?: string;
  title?: Localized;
  description?: Localized;
  place?: Localized;
  people_names?: string[];
  keywords?: string[];
  source_name?: string | null;
  rights_holder?: Localized;
  rights_status?: string;
  digitized_at?: string | null;
  license?: string | null;
  verification_reference?: string | null;
  access_level?: string;
  legacy_ref?: string | null;
  content?: DateValue | null;
  date_note?: string | null;
}

/** Archive item draft payload: only the `fields` section, mirroring PATCH /archive-items/{id}. */
export interface ArchiveItemDraftPayload {
  fields?: ArchiveItemDraftFields;
}
