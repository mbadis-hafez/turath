import { request } from "@/api/http";
import type { PaginationMeta } from "@/types/api";
import type {
  AdminArtistRow,
  AdminArtistsQuery,
  ArtistContact,
  ArtistCuration,
  CurationUpdate,
  DateValue,
  EntryGroups,
  PortraitInfo,
  PortraitRights,
  SocialLink,
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

export interface ArtistProfilePayload {
  name?: { ar: string | null; en: string | null };
  bio?: { ar: string | null; en: string | null };
  nationality?: { ar: string | null; en: string | null };
  classification?: { ar: string | null; en: string | null };
  birth?: (Partial<DateValue> & { place?: { ar: string | null; en: string | null } }) | null;
  death?: Partial<DateValue> | null;
  living_status?: "unknown" | "living" | "deceased";
  legacy_code?: string;
}

export function createArtist(payload: ArtistProfilePayload): Promise<{ data: { id: number } }> {
  return request({ method: "POST", url: "/api/v1/artists", data: payload });
}

export function updateArtist(id: number, payload: ArtistProfilePayload): Promise<unknown> {
  return request({ method: "PATCH", url: `/api/v1/artists/${id}`, data: payload });
}

export function syncArtistEntries(id: number, groups: EntryGroups): Promise<unknown> {
  return request({ method: "PUT", url: `/api/v1/artists/${id}/entries`, data: groups });
}

export function syncArtistSocialLinks(id: number, links: SocialLink[]): Promise<unknown> {
  return request({ method: "PUT", url: `/api/v1/artists/${id}/social-links`, data: { links } });
}

export function uploadArtistPortrait(id: number, file: File, rights: PortraitRights): Promise<{ data: PortraitInfo }> {
  const form = new FormData();
  form.append("image", file);
  form.append("rights_status", rights);
  return request({ method: "POST", url: `/api/v1/artists/${id}/portrait`, data: form });
}

export function setArtistPortraitRights(id: number, rights: PortraitRights): Promise<{ data: PortraitInfo }> {
  return request({ method: "PATCH", url: `/api/v1/artists/${id}/portrait`, data: { rights_status: rights } });
}

export function deleteArtistPortrait(id: number): Promise<{ data: PortraitInfo }> {
  return request({ method: "DELETE", url: `/api/v1/artists/${id}/portrait` });
}

export type { ArtistContact };
