import { request } from "@/api/http";
import type { PaginatedResponse } from "@/types/api";
import type {
  ArtistArtworksQueryParams,
  Artwork,
  ArtworkListItem,
  ArtworksQueryParams,
} from "@/types/artwork";

function cleanParams(
  params: Record<string, string | number | undefined>,
): Record<string, string | number> {
  const result: Record<string, string | number> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") result[key] = value;
  }
  return result;
}

export function listArtworks(
  params: ArtworksQueryParams = {},
  signal?: AbortSignal,
): Promise<PaginatedResponse<ArtworkListItem>> {
  return request<PaginatedResponse<ArtworkListItem>>({
    method: "GET",
    url: "/api/v1/artworks",
    params: cleanParams({ ...params }),
    signal,
  });
}

export function getArtwork(
  id: number,
  signal?: AbortSignal,
): Promise<{ data: Artwork }> {
  return request<{ data: Artwork }>({
    method: "GET",
    url: `/api/v1/artworks/${id}`,
    signal,
  });
}

export function listArtistArtworks(
  artistId: number,
  params: ArtistArtworksQueryParams = {},
  signal?: AbortSignal,
): Promise<PaginatedResponse<ArtworkListItem>> {
  return request<PaginatedResponse<ArtworkListItem>>({
    method: "GET",
    url: `/api/v1/artists/${artistId}/artworks`,
    params: cleanParams({ ...params }),
    signal,
  });
}
