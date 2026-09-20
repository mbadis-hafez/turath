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

export interface RecordCompletenessDetail {
  open_conflicts: OpenConflict[];
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
  submitted_at: string;
}
