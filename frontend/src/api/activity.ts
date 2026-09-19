import { request } from "@/api/http";
import type { ActivityEntry, ActivityFilters } from "@/types/activity";
import type { PaginatedResponse } from "@/types/api";

export function fetchActivity(
  filters: ActivityFilters,
  signal?: AbortSignal,
): Promise<PaginatedResponse<ActivityEntry>> {
  const params: Record<string, string | number> = {};
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== null && value !== "") {
      params[key] = value;
    }
  }
  return request<PaginatedResponse<ActivityEntry>>({
    method: "GET",
    url: "/api/v1/activity",
    params,
    signal,
  });
}
