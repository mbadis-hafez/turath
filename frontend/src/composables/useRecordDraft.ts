import { computed, ref } from "vue";

import { getDraft, saveDraft, submitDraft } from "@/api/editorial";
import { ApiError } from "@/types/api";
import type { ProposalStatus, RecordType } from "@/types/proposal";

export interface DraftBlocker {
  proposal_id: string;
}

function blockerFrom(err: unknown): DraftBlocker | null {
  if (err instanceof ApiError && err.status === 409) {
    const body = (err.body ?? {}) as { proposal_id?: string };
    return { proposal_id: body.proposal_id ?? "" };
  }
  return null;
}

/**
 * Generic editorial-draft state for a record page. Tracks the current user's
 * open draft (if any), merges saved sections into the in-memory payload, and
 * exposes submit/discard flows. Pages read `draftPayload` to initialize their
 * form models — the draft wins per section present in the payload.
 */
export function useRecordDraft() {
  const status = ref<ProposalStatus | null>(null);
  const reviewNote = ref<string | null>(null);
  const blocker = ref<DraftBlocker | null>(null);
  const pendingSubmit = ref(false);
  const draftPayload = ref<Record<string, unknown>>({});
  const dirtySections = ref<Set<string>>(new Set());

  let type: RecordType | null = null;
  let recordId = 0;

  const hasDraft = computed(() => status.value !== null && blocker.value === null);

  async function init(recordType: RecordType, id: number): Promise<void> {
    type = recordType;
    recordId = id;
    status.value = null;
    reviewNote.value = null;
    blocker.value = null;
    draftPayload.value = {};
    dirtySections.value = new Set();
    try {
      const response = await getDraft(recordType, id);
      const proposal = response.data;
      if (!proposal) return;
      status.value = proposal.status;
      reviewNote.value = proposal.review_note ?? null;
      draftPayload.value = proposal.payload ?? {};
    } catch (err) {
      const conflict = blockerFrom(err);
      if (conflict) {
        blocker.value = conflict;
        return;
      }
      throw err;
    }
  }

  /** Merges a section into the draft and persists it. Throws (e.g. 422) so the caller can surface field errors. */
  async function saveSection(section: string, data: unknown): Promise<void> {
    if (type === null) throw new Error("useRecordDraft.init must be called before saveSection");
    const next = { ...draftPayload.value, [section]: data };
    try {
      const response = await saveDraft(type, recordId, next);
      draftPayload.value = next;
      status.value = response.data.status;
      dirtySections.value = new Set([...dirtySections.value, section]);
    } catch (err) {
      const conflict = blockerFrom(err);
      if (conflict) blocker.value = conflict;
      throw err;
    }
  }

  async function submit(): Promise<void> {
    if (type === null) throw new Error("useRecordDraft.init must be called before submit");
    pendingSubmit.value = true;
    try {
      const response = await submitDraft(type, recordId);
      status.value = response.data.status;
      dirtySections.value = new Set();
    } finally {
      pendingSubmit.value = false;
    }
  }

  /** Clears the local overlay only; the backend keeps the last saved draft. */
  function resetLocally(): void {
    draftPayload.value = {};
    dirtySections.value = new Set();
    status.value = null;
    reviewNote.value = null;
  }

  const sectionInDraft = (section: string): boolean => dirtySections.value.has(section);

  return {
    status, reviewNote, blocker, pendingSubmit, draftPayload, dirtySections, hasDraft,
    init, saveSection, submit, resetLocally, sectionInDraft,
  };
}
