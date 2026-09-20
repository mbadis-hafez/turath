export type ImportEntityType = "artist" | "artwork" | "holder" | "archive_item";

export type ImportBatchStatus =
  | "uploaded"
  | "validated"
  | "committed"
  | "failed"
  | "cancelled";

export type ImportRowMatchStatus =
  | "new"
  | "matched_exact"
  | "matched_suggested"
  | "ambiguous"
  | "error";

export type ImportMatchConfidence = "high" | "medium" | "low";

export type ImportRowResolution =
  | "pending"
  | "create_new"
  | "link_existing"
  | "skip";

export type ImportRowCommitResult = "created" | "updated" | "skipped" | "failed";

export interface ImportBatch {
  id: string;
  entity_type: ImportEntityType;
  mapping_profile_id: string | null;
  original_filename: string;
  status: ImportBatchStatus;
  row_count: number;
  new_count: number;
  matched_count: number;
  error_count: number;
  skipped_count: number;
  uploaded_by: { id: number; name: string } | null;
  validated_at: string | null;
  committed_at: string | null;
  created_at: string;
}

export interface ImportValidationError {
  field: string;
  message: string;
}

export interface ImportBatchRow {
  id: string;
  row_number: number;
  match_status: ImportRowMatchStatus;
  match_confidence: ImportMatchConfidence | null;
  matched_entity_id: number | null;
  raw_data: Record<string, string | null>;
  mapped_data: Record<string, unknown>;
  validation_errors: ImportValidationError[];
  resolution: ImportRowResolution;
  resolved_by_user_id: number | null;
  resolved_at: string | null;
  commit_result: ImportRowCommitResult | null;
  resulting_entity_id: number | null;
}

export interface ImportMappingProfile {
  id: string;
  entity_type: ImportEntityType;
  name: string;
  column_map: Record<string, string>;
  created_at: string;
}

export interface ImportBatchesQueryParams {
  entity_type?: ImportEntityType;
  status?: ImportBatchStatus;
  page?: number;
  per_page?: number;
}

export interface ImportBatchRowsQueryParams {
  match_status?: ImportRowMatchStatus;
  resolution?: ImportRowResolution;
  page?: number;
  per_page?: number;
}
