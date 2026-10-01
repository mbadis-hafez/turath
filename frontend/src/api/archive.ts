import { request } from "@/api/http";
import type { PaginatedResponse, PaginationMeta } from "@/types/api";
import type {
  AdminArchiveQuery, AdminArchiveRow, ArchiveEdit, ArchiveFacets, ArchiveEditFile, ArchiveItem, ArchiveQueryParams, BulkResult,
} from "@/types/archive";
import type {
  ArtistContactProposalInput, DocumentType, EntityMatch, ExtractedDate, ExtractedField, FileOcrBundle, OcrArtistContactState, OcrFormField, OcrHandwritingSuggestion,
  OcrStageName, OcrStageRun,
} from "@/types/ocr";

export function listArchiveItems(
  params: ArchiveQueryParams = {},
  signal?: AbortSignal,
): Promise<PaginatedResponse<ArchiveItem> & { meta: { facets?: ArchiveFacets } }> {
  const clean: Record<string, string | number | (string | number)[]> = {};
  for (const [k, v] of Object.entries(params)) {
    if (v === undefined || v === "" || (Array.isArray(v) && v.length === 0)) continue;
    clean[k] = v as string | number | (string | number)[];
  }
  // Arrays go out as key[]=a&key[]=b, which Laravel reads as arrays.
  return request({ method: "GET", url: "/api/v1/archive-items", params: clean, paramsSerializer: { indexes: false }, signal });
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

export function publishArchiveItem(id: number): Promise<{ data: ArchiveItem }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${id}/publish` });
}

export function addArchiveLink(id: number, link: { linkable_type: "artist" | "artwork" | "event"; linkable_id: number; role: string }): Promise<unknown> {
  return request({ method: "POST", url: `/api/v1/archive-items/${id}/links`, data: link });
}

export function removeArchiveLink(id: number, linkId: number): Promise<unknown> {
  return request({ method: "DELETE", url: `/api/v1/archive-items/${id}/links/${linkId}` });
}

export function listArtistArchiveItems(
  artistId: number,
  params: { per_page?: number } = {},
  signal?: AbortSignal,
): Promise<PaginatedResponse<ArchiveItem>> {
  return request<PaginatedResponse<ArchiveItem>>({ method: "GET", url: `/api/v1/artists/${artistId}/archive-items`, params, signal });
}

export function syncArchiveItemThemes(id: number, themeIds: number[]): Promise<unknown> {
  return request({ method: "PATCH", url: `/api/v1/archive-items/${id}/themes`, data: { theme_ids: themeIds } });
}

export function getArchiveItemFileOcr(archiveItemId: number, signal?: AbortSignal): Promise<{ data: FileOcrBundle | null }> {
  return request({ method: "GET", url: `/api/v1/archive-items/${archiveItemId}/file/ocr`, signal });
}

export function runArchiveItemFileOcr(archiveItemId: number): Promise<{ data: { status: string } }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/run` });
}

/** A reviewer's document type (null hands it back to the classifier); extraction re-runs with it. */
export function setOcrDocumentType(archiveItemId: number, documentType: DocumentType | null): Promise<{ data: { document_type: string | null; document_type_source: string; result: string } }> {
  return request({ method: "PUT", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/document-type`, data: { document_type: documentType } });
}

/** A reviewer says which record an extracted name means: a record id, or a place's spelling. Changes no record. */
export function confirmEntityMatch(archiveItemId: number, matchId: number, choice: { entity_id: number | string } | { key: string }): Promise<{ data: EntityMatch }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/matches/${matchId}/confirm`, data: choice });
}

/** The reviewer's own search for the record a name means, when it isn't among the candidates. */
export function searchEntityMatch(
  archiveItemId: number, matchId: number, q: string, signal?: AbortSignal,
): Promise<{ data: { id: number | string; label: { ar: string; en: string }; detail: string | null }[] }> {
  return request({ method: "GET", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/matches/${matchId}/search`, params: { q }, signal });
}

export function decideEntityMatch(archiveItemId: number, matchId: number, decision: "no-match" | "reset"): Promise<{ data: EntityMatch }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/matches/${matchId}/${decision}` });
}

export function reviewExtractedDate(archiveItemId: number, dateId: number, decision: "accept" | "reject"): Promise<{ data: ExtractedDate }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/dates/${dateId}/${decision}` });
}

/** Re-runs one pipeline stage; later stages follow only if its new output changes their input. */
export function runArchiveItemFileOcrStage(archiveItemId: number, stage: OcrStageName): Promise<{ data: { status: string | null; result: string; stages: OcrStageRun[] } }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/stages/${stage}/run` });
}

export function acceptExtractedField(archiveItemId: number, fieldId: number): Promise<{ data: ExtractedField }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/fields/${fieldId}/accept` });
}

export function rejectExtractedField(archiveItemId: number, fieldId: number): Promise<{ data: ExtractedField }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/fields/${fieldId}/reject` });
}

export function editExtractedField(archiveItemId: number, fieldId: number, value: string): Promise<{ data: ExtractedField }> {
  return request({ method: "PATCH", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/fields/${fieldId}`, data: { extracted_value: value } });
}

/** A cropped source image for one OCR region — used directly as an <img> src; cookie auth carries over since it's same-origin. */
export function ocrRegionCropUrl(archiveItemId: number, regionId: number): string {
  return `${import.meta.env.VITE_API_BASE_URL ?? ""}/api/v1/archive-items/${archiveItemId}/file/ocr/regions/${regionId}/crop`;
}

/** A page as OCR rendered it — region boxes are in its pixels. Used directly as an <img> src. */
export function ocrPageImageUrl(archiveItemId: number, page: number): string {
  return `${import.meta.env.VITE_API_BASE_URL ?? ""}/api/v1/archive-items/${archiveItemId}/file/ocr/pages/${page}/image`;
}

export function markExtractedFieldUncertain(archiveItemId: number, fieldId: number, note: string | null): Promise<{ data: ExtractedField }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/fields/${fieldId}/uncertain`, data: { note } });
}

/** Types what a set-aside line says into a field the reviewer chose. */
export function transcribeOcrRegion(archiveItemId: number, regionId: number, fieldKey: string, value: string): Promise<{ data: ExtractedField }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/regions/${regionId}/transcribe`, data: { field_key: fieldKey, value } });
}

export function setOcrRegionDismissed(archiveItemId: number, regionId: number, dismissed: boolean): Promise<{ data: { id: number; dismissed: boolean } }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/regions/${regionId}/${dismissed ? "dismiss" : "restore"}` });
}

export function transcribeOcrFormField(archiveItemId: number, formFieldId: number, value: string): Promise<{ data: OcrFormField }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/form-fields/${formFieldId}/transcribe`, data: { value } });
}

export function requestHandwritingSuggestion(archiveItemId: number, regionId: number): Promise<{ data: OcrHandwritingSuggestion }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/regions/${regionId}/handwriting-suggestion` });
}

export function reviewHandwritingSuggestion(
  archiveItemId: number,
  suggestionId: number,
  decision: "accepted" | "edited" | "rejected",
  finalText?: string,
): Promise<{ data: OcrHandwritingSuggestion }> {
  return request({
    method: "POST",
    url: `/api/v1/archive-items/${archiveItemId}/file/ocr/handwriting-suggestions/${suggestionId}/review`,
    data: finalText === undefined ? { decision } : { decision, final_text: finalText },
  });
}

/** The authorization letter → ArtistContact flow; `name` searches candidates under a different spelling. */
export function getOcrArtistContact(archiveItemId: number, name?: string, signal?: AbortSignal): Promise<{ data: OcrArtistContactState }> {
  return request({ method: "GET", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/artist-contact`, params: name ? { name } : {}, signal });
}

export function confirmOcrArtist(archiveItemId: number, artistId: number): Promise<{ data: OcrArtistContactState }> {
  return request({ method: "PUT", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/artist-contact/artist`, data: { artist_id: artistId } });
}

export function proposeOcrArtistContact(archiveItemId: number, input: ArtistContactProposalInput): Promise<{ data: OcrArtistContactState }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/artist-contact/propose`, data: input });
}

export function acceptHighConfidenceFields(archiveItemId: number, threshold?: number): Promise<{ data: { accepted: string[] } }> {
  return request({ method: "POST", url: `/api/v1/archive-items/${archiveItemId}/file/ocr/fields/accept-high-confidence`, data: threshold === undefined ? {} : { threshold } });
}
