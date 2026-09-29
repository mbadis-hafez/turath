import { request } from "@/api/http";
import type { PaginationMeta } from "@/types/api";
import type { EditorDashboardData } from "@/types/editorDashboard";
import type {
  DashboardEntityType,
  DashboardRecord,
  DashboardRecordsQuery,
  DashboardStats,
  RecordCompletenessDetail,
  ReviewQueueItem,
} from "@/types/completeness";
import type { ReviewerDashboardData } from "@/types/reviewerDashboard";

/** URL segment used by /records/{type}/{id}/… for each dashboard entity. */
const SEGMENTS: Record<DashboardEntityType, string> = {
  artist: "artists",
  artwork: "artworks",
  archive_item: "archive-items",
};

export function getDashboardStats(
  signal?: AbortSignal,
): Promise<{ data: DashboardStats }> {
  return request({ method: "GET", url: "/api/v1/dashboard/completeness", signal });
}

export function getEditorDashboard(
  signal?: AbortSignal,
): Promise<{ data: EditorDashboardData }> {
  return request({ method: "GET", url: "/api/v1/dashboard/editor", signal });
}

export function getReviewerDashboard(
  signal?: AbortSignal,
): Promise<{ data: ReviewerDashboardData }> {
  return request({ method: "GET", url: "/api/v1/dashboard/reviewer", signal });
}

export async function listDashboardRecords(
  query: DashboardRecordsQuery,
  signal?: AbortSignal,
): Promise<{ data: DashboardRecord[]; meta: PaginationMeta }> {
  const response = await request<{
    data: DashboardRecord[];
    meta: Omit<PaginationMeta, "from" | "to">;
  }>({
    method: "GET",
    url: "/api/v1/dashboard/records",
    params: { ...query },
    signal,
  });
  return { data: response.data, meta: { ...response.meta, from: null, to: null } };
}

export function dashboardExportUrl(query: DashboardRecordsQuery): string {
  const params = new URLSearchParams();
  if (query.entity_type) params.set("entity_type", query.entity_type);
  if (query.severity) params.set("severity", query.severity);
  const qs = params.toString();
  return `/api/v1/dashboard/export${qs ? `?${qs}` : ""}`;
}

export function getRecordCompleteness(
  entity: DashboardEntityType,
  id: number,
  signal?: AbortSignal,
): Promise<{ data: RecordCompletenessDetail }> {
  return request({
    method: "GET",
    url: `/api/v1/records/${SEGMENTS[entity]}/${id}/completeness`,
    signal,
  });
}

export interface NewCitationSource {
  linked_archive_item_id?: number;
  source_type?: string;
  title_ar?: string | null;
  title_en?: string | null;
  publisher_or_outlet?: string | null;
  reference_note?: string | null;
  url?: string | null;
  year?: number | null;
}

export function addFieldCitation(
  entity: DashboardEntityType,
  id: number,
  payload: { field_key: string; source_id?: string; new_source?: NewCitationSource; claimed_value: unknown },
): Promise<unknown> {
  return request({ method: "POST", url: `/api/v1/records/${SEGMENTS[entity]}/${id}/citations`, data: payload });
}

export function resolveSourceConflict(
  conflictId: string,
  payload: { resolved_source_id: string; resolution_note?: string },
): Promise<unknown> {
  return request({
    method: "POST",
    url: `/api/v1/source-conflicts/${conflictId}/resolve`,
    data: payload,
  });
}

export function listReviewQueue(
  params: { status?: string; review_type?: string } = {},
  signal?: AbortSignal,
): Promise<{ data: ReviewQueueItem[] }> {
  return request({ method: "GET", url: "/api/v1/review-queue", params, signal });
}

/** Approve/reject a standalone (non-proposal) queue entry (FR-004/005). */
export function recordReviewOutcome(
  id: string,
  payload: { outcome: "approved" | "rejected"; review_note?: string },
): Promise<{ data: ReviewQueueItem }> {
  return request({ method: "POST", url: `/api/v1/review-queue/${id}/outcome`, data: payload });
}

export function acknowledgeReviewItem(id: string): Promise<unknown> {
  return request({ method: "POST", url: `/api/v1/review-queue/${id}/acknowledge` });
}
