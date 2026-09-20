import { request } from "@/api/http";
import type { PaginationMeta } from "@/types/api";
import type {
  DashboardEntityType,
  DashboardRecord,
  DashboardRecordsQuery,
  DashboardStats,
  RecordCompletenessDetail,
  ReviewQueueItem,
} from "@/types/completeness";

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
  signal?: AbortSignal,
): Promise<{ data: ReviewQueueItem[] }> {
  return request({ method: "GET", url: "/api/v1/review-queue", signal });
}

export function acknowledgeReviewItem(id: string): Promise<unknown> {
  return request({ method: "POST", url: `/api/v1/review-queue/${id}/acknowledge` });
}
