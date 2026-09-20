import { request } from "@/api/http";
import type { PaginationMeta } from "@/types/api";
import type { Localized } from "@/types/artistCuration";
import type { AdminArtworkRow, AdminArtworksQuery, ArtworkCuration, PipelineStatus } from "@/types/artworkCuration";

export async function listAdminArtworks(
  params: AdminArtworksQuery,
  signal?: AbortSignal,
): Promise<{ data: AdminArtworkRow[]; meta: PaginationMeta & { candidate_count: number } }> {
  const clean: Record<string, string | number> = {};
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== "") clean[k] = v as string | number;
  }
  const response = await request<{
    data: AdminArtworkRow[];
    meta: Omit<PaginationMeta, "from" | "to"> & { candidate_count: number };
  }>({ method: "GET", url: "/api/v1/admin/artworks", params: clean, signal });
  return { data: response.data, meta: { ...response.meta, from: null, to: null } };
}

export function getArtworkCuration(id: number, signal?: AbortSignal): Promise<{ data: ArtworkCuration }> {
  return request({ method: "GET", url: `/api/v1/artworks/${id}/curation`, signal });
}

export function updateArtwork(id: number, payload: Record<string, unknown>): Promise<unknown> {
  return request({ method: "PATCH", url: `/api/v1/artworks/${id}`, data: payload });
}

export function updateArtworkStage(id: number, stageKey: string, status: PipelineStatus): Promise<unknown> {
  return request({ method: "PATCH", url: `/api/v1/artworks/${id}/pipeline/${stageKey}`, data: { status } });
}

export function approveArtwork(id: number): Promise<unknown> {
  return request({ method: "POST", url: `/api/v1/artworks/${id}/approve` });
}

export async function searchHolders(q: string, signal?: AbortSignal): Promise<{ id: number; name: Localized }[]> {
  const response = await request<{ data: { id: number; name: Localized }[] }>({
    method: "GET", url: "/api/v1/admin/holders", params: { q }, signal,
  });
  return response.data;
}

export function createArtwork(payload: Record<string, unknown>): Promise<{ data: { id: number } }> {
  return request({ method: "POST", url: "/api/v1/artworks", data: payload });
}

export function mergeArtworks(payload: {
  survivor_id: number;
  duplicate_id: number;
  field_resolution: Record<string, "survivor" | "duplicate">;
}): Promise<unknown> {
  return request({ method: "POST", url: "/api/v1/artworks/merge", data: payload });
}
