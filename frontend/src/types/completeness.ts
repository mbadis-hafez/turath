import type { PortraitRights } from "@/types/artistCuration";

export type CompletenessSeverity =
  | "blocking"
  | "conflict"
  | "minor"
  | "pending_review"
  | "clear";

export type DashboardEntityType = "artist" | "artwork" | "archive_item";

export interface EntityBreakdown {
  pct: number;
  note_key: string | null;
  count: number;
  of: number;
}

export interface DashboardStats {
  user: { id: number; name: string } | null;
  total_records: number;
  avg_completeness_pct: number;
  blocking_record_count: number;
  conflict_count: number;
  missing_field_count: number;
  by_entity_type?: Partial<Record<DashboardEntityType, EntityBreakdown>>;
  computed_at: string | null;
}

export interface DashboardRecord {
  entity_type: DashboardEntityType;
  id: number;
  slug: string | null;
  title: { ar: string | null; en: string | null };
  completeness_pct: number;
  severity: CompletenessSeverity;
  blocking_gaps: string[];
  minor_gaps: string[];
  open_conflict_count: number;
}

export interface DashboardRecordsQuery {
  entity_type?: DashboardEntityType;
  severity?: CompletenessSeverity;
  page?: number;
}

export interface ConflictCitation {
  id: string;
  field_key: string;
  claimed_value: unknown;
  source: {
    id: string;
    title: { ar: string | null; en: string | null };
    publisher_or_outlet: string | null;
    reference_note: string | null;
  } | null;
}

export interface OpenConflict {
  id: string;
  field_key: string;
  label: { ar: string; en: string };
  citations: ConflictCitation[];
}

/** A gap on the records-completeness payload, labelled in both locales. */
export interface CompletenessGap {
  field_key: string;
  label: { ar: string; en: string };
}

export interface RecordCompletenessCitation {
  id: string;
  field_key: string;
  claimed_value: unknown;
  is_primary: boolean;
  source: ConflictCitation["source"];
  created_at: string;
}

export interface RecordCompletenessDetail {
  citable_type: string;
  citable_id: number;
  completeness_pct: number;
  severity: CompletenessSeverity;
  blocking_gaps: CompletenessGap[];
  minor_gaps: CompletenessGap[];
  open_conflicts: OpenConflict[];
  citations: RecordCompletenessCitation[];
  /** Artist-only extras, computed live by the backend (see sectionedExtras). */
  met_count?: number;
  total_count?: number;
  complete?: boolean;
  sections?: ProfileCompletenessSections;
  missing?: CompletenessMissingItem[];
}

export type CompletenessSectionKey = "identity" | "biography" | "media";

export interface CompletenessSection {
  met: number;
  total: number;
  percentage: number;
}

export type ProfileCompletenessSections = Record<CompletenessSectionKey, CompletenessSection>;

export interface CompletenessMissingItem {
  key: string;
  label: { ar: string; en: string };
  section: CompletenessSectionKey;
}

/** Normalized profile-completeness shape shared by the records and preview endpoints. */
export interface ProfileCompletenessSummary {
  percentage: number;
  met_count: number;
  total_count: number;
  complete: boolean;
  sections: ProfileCompletenessSections;
  missing: CompletenessMissingItem[];
}

/** Body of POST /artists/completeness-preview (every field optional). */
export interface ProfileCompletenessPreviewRequest {
  name?: { ar: string | null; en: string | null };
  bio?: { ar: string | null; en: string | null };
  living_status?: "living" | "deceased" | "unknown";
  birth?: { year_from: number | null } | null;
  death?: { year_from: number | null } | null;
  birth_place?: { ar: string | null; en: string | null };
  nationality?: { ar: string | null; en: string | null };
  legacy_code?: string | null;
  portrait_uploaded?: boolean;
  portrait_rights_status?: PortraitRights;
}

export type ReviewType =
  | "archivist_review"
  | "data_audit"
  | "second_source_needed";

export interface ReviewQueueItem {
  id: string;
  citable_type: string;
  citable_id: number;
  review_type: ReviewType;
  title: { ar: string | null; en: string | null } | null;
  note: string | null;
  status: "pending" | "acknowledged" | "approved" | "rejected";
  /** Non-null when the entry is backed by an edit proposal (reviewed via the proposal routes). */
  edit_proposal_id: string | null;
  is_proposal_backed: boolean;
  review_note: string | null;
  acted_by: { id: number; name: string } | null;
  acted_at: string | null;
  submitted_by: { id: number; name: string } | null;
  submitted_at: string;
}
