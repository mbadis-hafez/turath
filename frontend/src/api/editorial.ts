import { request } from "@/api/http";
import type { Proposal, RecordType } from "@/types/proposal";
import type { SectionDiff } from "@/types/proposalDiff";

/** The current user's open draft proposal for a record, or null when none exists. */
export function getDraft(type: RecordType, id: number, signal?: AbortSignal): Promise<{ data: Proposal | null }> {
  return request({ method: "GET", url: `/api/v1/records/${type}/${id}/draft`, signal });
}

/** Upserts the current user's draft. A 409 body carries `{message, proposal_id}`. */
export function saveDraft(
  type: RecordType,
  id: number,
  payload: Record<string, unknown>,
  rationale?: string,
): Promise<{ data: Proposal }> {
  return request({ method: "PUT", url: `/api/v1/records/${type}/${id}/draft`, data: { payload, rationale } });
}

/** Moves the current user's draft to pending (review). */
export function submitDraft(type: RecordType, id: number): Promise<{ data: Proposal }> {
  return request({ method: "POST", url: `/api/v1/records/${type}/${id}/draft/submit` });
}

/** Per-section diff of a sectioned (editorial draft) proposal, for reviewers. */
export function getProposalDiff(id: string, signal?: AbortSignal): Promise<{ data: { sections: SectionDiff[] } }> {
  return request({ method: "GET", url: `/api/v1/proposals/${id}/diff`, signal });
}

/** Sends a pending proposal back to the editor with a required review note. */
export function requestChanges(id: string, reviewNote: string): Promise<{ data: Proposal }> {
  return request({ method: "POST", url: `/api/v1/proposals/${id}/request-changes`, data: { review_note: reviewNote } });
}
