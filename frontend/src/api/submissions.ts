import { request } from "@/api/http";
import type { PaginationMeta } from "@/types/api";
import type { CatalogItem, LetterStatus, SubmissionDetail, SubmissionRow, SubmissionStatus } from "@/types/submission";

type Meta = Omit<PaginationMeta, "from" | "to">;

export function submitMaterial(form: FormData): Promise<{ data: { id: number; status: SubmissionStatus } }> {
  return request({ method: "POST", url: "/api/v1/material-submissions", data: form });
}

export async function listSubmissions(
  params: { status?: SubmissionStatus | ""; page?: number },
  signal?: AbortSignal,
): Promise<{ data: SubmissionRow[]; meta: PaginationMeta }> {
  const clean: Record<string, string | number> = {};
  for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== "") clean[k] = v as string | number;
  const r = await request<{ data: SubmissionRow[]; meta: Meta }>({ method: "GET", url: "/api/v1/material-submissions", params: clean, signal });
  return { data: r.data, meta: { ...r.meta, from: null, to: null } };
}

export function getSubmission(id: number, signal?: AbortSignal): Promise<{ data: SubmissionDetail }> {
  return request({ method: "GET", url: `/api/v1/material-submissions/${id}`, signal });
}

export function updateSubmission(
  id: number,
  patch: {
    status?: SubmissionStatus;
    linked_artist_id?: number | null;
    linked_artwork_id?: number | null;
    staff_notes?: string | null;
    authorization_letter_status?: LetterStatus;
  },
): Promise<{ data: SubmissionDetail }> {
  return request({ method: "PATCH", url: `/api/v1/material-submissions/${id}`, data: patch });
}

export function catalogSubmission(id: number, items: CatalogItem[]): Promise<{ data: SubmissionDetail & { created_archive_item_ids: number[] } }> {
  return request({ method: "POST", url: `/api/v1/material-submissions/${id}/catalog`, data: { items } });
}

export function rejectSubmission(id: number, staffNotes: string): Promise<{ data: SubmissionDetail }> {
  return request({ method: "POST", url: `/api/v1/material-submissions/${id}/reject`, data: { staff_notes: staffNotes } });
}
