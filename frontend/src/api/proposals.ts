import { request } from "@/api/http";
import type { PaginationMeta } from "@/types/api";
import type { Proposal, ProposalStatus, RecordType, ReviewType, Revision } from "@/types/proposal";

type Meta = Omit<PaginationMeta, "from" | "to">;

export async function listProposals(
  params: { status?: ProposalStatus | ""; review_type?: ReviewType | ""; mine?: 1; page?: number },
  signal?: AbortSignal,
): Promise<{ data: Proposal[]; meta: PaginationMeta }> {
  const clean: Record<string, string | number> = {};
  for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== "") clean[k] = v as string | number;
  const r = await request<{ data: Proposal[]; meta: Meta }>({ method: "GET", url: "/api/v1/proposals", params: clean, signal });
  return { data: r.data, meta: { ...r.meta, from: null, to: null } };
}

export function getProposal(id: string, signal?: AbortSignal): Promise<{ data: Proposal }> {
  return request({ method: "GET", url: `/api/v1/proposals/${id}`, signal });
}

export function submitProposal(
  type: RecordType,
  id: number,
  payload: { changes: Record<string, unknown>; rationale: string },
): Promise<{ data: Proposal }> {
  return request({ method: "POST", url: `/api/v1/records/${type}/${id}/proposals`, data: payload });
}

export function approveProposal(
  id: string,
  payload: { review_note?: string; confirm_conflict?: boolean } = {},
): Promise<{ data: Proposal }> {
  return request({ method: "POST", url: `/api/v1/proposals/${id}/approve`, data: payload });
}

export function rejectProposal(id: string, reviewNote: string): Promise<{ data: Proposal }> {
  return request({ method: "POST", url: `/api/v1/proposals/${id}/reject`, data: { review_note: reviewNote } });
}

export function listRevisions(type: RecordType, id: number, signal?: AbortSignal): Promise<{ data: Revision[] }> {
  return request({ method: "GET", url: `/api/v1/records/${type}/${id}/revisions`, signal });
}

export function rollbackRevision(
  type: RecordType,
  id: number,
  revisionId: string,
  confirmUnpublish = false,
): Promise<{ data: Revision }> {
  return request({
    method: "POST",
    url: `/api/v1/records/${type}/${id}/revisions/${revisionId}/rollback`,
    data: { confirm_unpublish: confirmUnpublish },
  });
}
