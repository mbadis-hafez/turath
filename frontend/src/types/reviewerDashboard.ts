import type { LocalizedValue } from "@/composables/useLocalized";

/** citable_type values the reviewer dashboard can reference — the URL-segment
 * style used by CitableTypeResolver::segmentFor (artists/artworks/archive-items),
 * plus the reviewer-only material_submission kind. */
export type ReviewerCitableType = "artists" | "artworks" | "archive-items" | "material_submission";

export interface ReviewQueueSummary {
  waiting_review: number;
  verification_issues: number;
  changes_returned: number;
  reviewed_today: number;
}

export interface NeedsReviewItem {
  id: string;
  citable_type: ReviewerCitableType | string;
  citable_id: number;
  review_type: string;
  title: LocalizedValue | null;
  note: string | null;
  status: string;
  submitted_by: { id: number; name: string } | null;
  submitted_at: string;
  completeness_pct: number | null;
  severity: string | null;
  verification_status: string | null;
}

export type VerificationIssueKind = "verification" | "conflict";

export interface VerificationIssueItem {
  kind: VerificationIssueKind;
  citable_type: ReviewerCitableType | string | null;
  id: number;
  slug: string | null;
  title: LocalizedValue | null;
  completeness_pct?: number;
  verification_status?: string;
  issues?: string[];
  field_key?: string;
  conflict_id?: string;
}

export interface ReturnedContentItem {
  id: string;
  citable_type: ReviewerCitableType | string | null;
  citable_id: number;
  title: LocalizedValue | null;
  proposed_by: { id: number; name: string } | null;
  review_type: string;
  previous_review_note: string | null;
}

export type ReviewPipelineStage = "ready_for_review" | "changes_requested" | "approved" | "rejected";

export type ReviewPipeline = Record<ReviewPipelineStage, number>;

export interface ReviewerContentOverview {
  artists: number;
  artworks: number;
  archive_items: number;
  material_submissions: number;
}

export interface RecentlyReviewedItem {
  id: string;
  citable_type: ReviewerCitableType | string | null;
  citable_id: number;
  title: LocalizedValue | null;
  decision: "approved" | "rejected" | "changes_requested";
  reviewed_at: string | null;
}

export interface ReviewActivityItem {
  citable_type: ReviewerCitableType | string | null;
  id: number;
  title: LocalizedValue | null;
  event: string;
  created_at: string;
}

/** Mirrors `App\Support\Dashboard\ReviewerDashboard::forUser()`. */
export interface ReviewerDashboardData {
  summary: ReviewQueueSummary;
  needs_review: NeedsReviewItem[];
  priority_queue: NeedsReviewItem[];
  verification_issues: VerificationIssueItem[];
  returned_content: ReturnedContentItem[];
  pipeline: ReviewPipeline;
  content_overview: ReviewerContentOverview;
  recently_reviewed: RecentlyReviewedItem[];
  recent_activity: ReviewActivityItem[];
}
