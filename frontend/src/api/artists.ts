import { request } from "@/api/http";
import type { PaginatedResponse } from "@/types/api";
import type { ActivityEntry } from "@/types/activity";
import type {
  Artist,
  ArtistActivityQueryParams,
  ArtistListItem,
  ArtistsQueryParams,
} from "@/types/artist";

function cleanParams(
  params: Record<string, string | number | undefined>,
): Record<string, string | number> {
  const result: Record<string, string | number> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") result[key] = value;
  }
  return result;
}

export function listArtists(
  params: ArtistsQueryParams = {},
  signal?: AbortSignal,
): Promise<PaginatedResponse<ArtistListItem>> {
  return request<PaginatedResponse<ArtistListItem>>({
    method: "GET",
    url: "/api/v1/artists",
    params: cleanParams({ ...params }),
    signal,
  });
}

export function getArtist(
  slug: string,
  signal?: AbortSignal,
): Promise<{ data: Artist }> {
  return request<{ data: Artist }>({
    method: "GET",
    url: `/api/v1/artists/${encodeURIComponent(slug)}`,
    signal,
  });
}

export function getArtistActivity(
  id: number,
  params: ArtistActivityQueryParams = {},
  signal?: AbortSignal,
): Promise<PaginatedResponse<ActivityEntry>> {
  return request<PaginatedResponse<ActivityEntry>>({
    method: "GET",
    url: `/api/v1/artists/${id}/activity`,
    params: cleanParams({ ...params }),
    signal,
  });
}
