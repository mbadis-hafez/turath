import { request } from "@/api/http";
import type { PaginationMeta } from "@/types/api";
import type { AdminArtworkRow, AdminArtworksQuery } from "@/types/artworkCuration";

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
