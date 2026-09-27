import type { LocalizedValue } from "@/composables/useLocalized";

/** Entity types the editor dashboard can reference (singular, as the API sends them). */
export type EditorEntityType = "artist" | "artwork" | "event" | "archive_item";

export type AttentionKind = "changes_requested" | "incomplete" | "creation_pending";

export interface MyWorkSummary {
  drafts: number;
  in_progress: number;
  ready_for_review: number;
  changes_requested: number;
}

export interface AttentionItem {
  entity_type: EditorEntityType;
  id: number;
  slug: string | null;
  title: LocalizedValue;
  kind: AttentionKind;
  completeness_pct: number | null;
  severity: string | null;
  reason_note: string | null;
  updated_at: string;
}

export interface ContentOverviewEntry {
  total: number;
  incomplete: number;
}

/** content_overview keys are plural, unlike the singular entity keys. */
export interface ContentOverview {
  artists: ContentOverviewEntry;
  artworks: ContentOverviewEntry;
  events: ContentOverviewEntry;
  archive_items: ContentOverviewEntry;
}

export type PipelineStage =
  | "draft"
  | "in_progress"
  | "ready_for_review"
  | "under_review"
  | "changes_requested"
  | "approved"
  | "published";

export type ReviewPipeline = Record<PipelineStage, number>;

export interface CompletenessBuckets {
  complete: number;
  high: number;
  medium: number;
  low: number;
}

export interface LowestCompletenessItem {
  entity_type: EditorEntityType;
  id: number;
  slug: string | null;
  title: LocalizedValue;
  completeness_pct: number;
}

export interface CompletenessSection {
  buckets: CompletenessBuckets;
  lowest: LowestCompletenessItem[];
}

export type ArchiveWorkStatus = "incomplete" | "draft" | "under_review";

export interface ArchiveWorkItem {
  id: number;
  slug: string | null;
  title: LocalizedValue;
  status: ArchiveWorkStatus;
  completeness_pct: number | null;
}

export interface ArchiveSection {
  draft: number;
  incomplete: number;
  under_review: number;
  published: number;
  items: ArchiveWorkItem[];
}

export interface ContinueWorkingItem {
  entity_type: EditorEntityType;
  id: number;
  slug: string | null;
  title: LocalizedValue;
  completeness_pct: number | null;
  updated_at: string;
}

export interface RecentActivityItem {
  entity_type: EditorEntityType;
  id: number;
  slug: string | null;
  title: LocalizedValue;
  event: string;
  created_at: string;
}

/** Mirrors `App\Support\Dashboard\EditorDashboard::forUser()`. */
export interface EditorDashboardData {
  my_work: MyWorkSummary;
  needs_attention: AttentionItem[];
  content_overview: ContentOverview;
  review_pipeline: ReviewPipeline;
  completeness: CompletenessSection;
  archive: ArchiveSection;
  continue_working: ContinueWorkingItem[];
  recent_activity: RecentActivityItem[];
}
