import { request } from "@/api/http";
import type { PaginatedResponse, PaginationMeta } from "@/types/api";
import type { AdminArchiveQuery, AdminArchiveRow, ArchiveItem, ArchiveQueryParams, BulkResult } from "@/types/archive";

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

export async function listAdminArchive(
  params: AdminArchiveQuery,
  signal?: AbortSignal,
): Promise<{ data: AdminArchiveRow[]; meta: PaginationMeta & { total_all: number; mine_count: number; incomplete_count: number } }> {
  const clean: Record<string, string | number> = {};
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== "") clean[k] = v as string | number;
  }
  const response = await request<{
    data: AdminArchiveRow[];
    meta: Omit<PaginationMeta, "from" | "to"> & { total_all: number; mine_count: number; incomplete_count: number };
  }>({ method: "GET", url: "/api/v1/admin/archive-items", params: clean, signal });
  return { data: response.data, meta: { ...response.meta, from: null, to: null } };
}

export function bulkArchive(payload: {
  ids: number[];
  action: "set_status" | "link_artist" | "delete";
  status?: "draft" | "published" | "hidden";
  artist_id?: number;
}): Promise<{ data: BulkResult }> {
  return request({ method: "POST", url: "/api/v1/admin/archive-items/bulk", data: payload });
}
