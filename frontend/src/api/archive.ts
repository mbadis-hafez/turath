import { request } from "@/api/http";
import type { PaginatedResponse, PaginationMeta } from "@/types/api";
import type {
  AdminArchiveQuery, AdminArchiveRow, ArchiveEdit, ArchiveEditFile, ArchiveItem, ArchiveQueryParams, BulkResult,
} from "@/types/archive";

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

export function getAdminArchiveItem(id: number, signal?: AbortSignal): Promise<{ data: ArchiveEdit }> {
  return request({ method: "GET", url: `/api/v1/admin/archive-items/${id}`, signal });
}

export function createArchiveItem(payload: Record<string, unknown>): Promise<{ data: { id: number } }> {
  return request({ method: "POST", url: "/api/v1/archive-items", data: payload });
}

export function updateArchiveItem(id: number, payload: Record<string, unknown>): Promise<unknown> {
  return request({ method: "PATCH", url: `/api/v1/archive-items/${id}`, data: payload });
}

export function uploadArchiveFile(id: number, file: File): Promise<{ data: ArchiveEditFile }> {
  const data = new FormData();
  data.append("file", file);
  return request({ method: "POST", url: `/api/v1/archive-items/${id}/file`, data });
}

export function deleteArchiveFile(id: number): Promise<unknown> {
  return request({ method: "DELETE", url: `/api/v1/archive-items/${id}/file` });
}

export function submitArchiveReview(id: number): Promise<{ data: ArchiveEdit }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${id}/submit-review` });
}

export function addArchiveLink(id: number, link: { linkable_type: "artist" | "artwork"; linkable_id: number; role: string }): Promise<unknown> {
  return request({ method: "POST", url: `/api/v1/archive-items/${id}/links`, data: link });
}

export function removeArchiveLink(id: number, linkId: number): Promise<unknown> {
  return request({ method: "DELETE", url: `/api/v1/archive-items/${id}/links/${linkId}` });
}
