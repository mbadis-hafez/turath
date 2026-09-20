import { request } from "@/api/http";
import type { PaginatedResponse } from "@/types/api";
import type { ArchiveItem, ArchiveQueryParams } from "@/types/archive";

export function listArchiveItems(
  params: ArchiveQueryParams = {},
  signal?: AbortSignal,
): Promise<PaginatedResponse<ArchiveItem>> {
  const clean: Record<string, string | number> = {};
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== "") clean[k] = v as string | number;
  }
  return request<PaginatedResponse<ArchiveItem>>({ method: "GET", url: "/api/v1/archive-items", params: clean, signal });
}
