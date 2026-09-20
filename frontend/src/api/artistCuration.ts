import { request } from "@/api/http";
import type { PaginationMeta } from "@/types/api";
import type {
  AdminArtistRow,
  AdminArtistsQuery,
  ArtistCuration,
  CurationUpdate,
  Theme,
} from "@/types/artistCuration";

export async function listAdminArtists(
  params: AdminArtistsQuery,
  signal?: AbortSignal,
): Promise<{ data: AdminArtistRow[]; meta: PaginationMeta }> {
  const clean: Record<string, string | number> = {};
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== "") clean[k] = v as string | number;
  }
  const response = await request<{
    data: AdminArtistRow[];
    meta: Omit<PaginationMeta, "from" | "to">;
  }>({ method: "GET", url: "/api/v1/admin/artists", params: clean, signal });
  return { data: response.data, meta: { ...response.meta, from: null, to: null } };
}

export function getArtistCuration(
  id: number,
  signal?: AbortSignal,
): Promise<{ data: ArtistCuration }> {
  return request({ method: "GET", url: `/api/v1/artists/${id}/curation`, signal });
}

export function updateArtistCuration(
  id: number,
  payload: CurationUpdate,
): Promise<{ data: ArtistCuration }> {
  return request({ method: "PATCH", url: `/api/v1/artists/${id}/curation`, data: payload });
}

export function verifyArtist(id: number): Promise<unknown> {
  return request({ method: "POST", url: `/api/v1/artists/${id}/verify`, data: { status: "verified" } });
}

export function mergeArtists(payload: {
  survivor_id: number;
  duplicate_id: number;
  field_resolution: Record<string, "survivor" | "duplicate">;
}): Promise<unknown> {
  return request({ method: "POST", url: "/api/v1/artists/merge", data: payload });
}

export function listThemes(signal?: AbortSignal): Promise<{ data: Theme[] }> {
  return request({ method: "GET", url: "/api/v1/themes", signal });
}
