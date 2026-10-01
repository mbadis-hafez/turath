import { computed, onBeforeUnmount, ref, watch, type Ref } from "vue";

import {
  acceptExtractedField, acceptHighConfidenceFields, confirmEntityMatch, decideEntityMatch, editExtractedField, getArchiveItemFileOcr, markExtractedFieldUncertain,
  rejectExtractedField, requestHandwritingSuggestion, reviewExtractedDate, reviewHandwritingSuggestion, runArchiveItemFileOcr, runArchiveItemFileOcrStage,
  setOcrDocumentType, setOcrRegionDismissed, transcribeOcrFormField, transcribeOcrRegion,
} from "@/api/archive";
import type { DocumentType, EntityMatchCandidate, FileOcrBundle, OcrStageName } from "@/types/ocr";

const POLL_MS = 3000;

/** Loads OCR status/text/extracted fields for one archive item's file, polling while processing. */
export function useFileOcr(archiveItemId: Ref<number>) {
  const bundle = ref<FileOcrBundle | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const actionError = ref<string | null>(null);

  let timer: ReturnType<typeof setTimeout> | null = null;
  let controller: AbortController | null = null;

  const isProcessing = computed(() => bundle.value?.status === "pending" || bundle.value?.status === "processing");
  // AI correction runs after the file is complete, so keep polling while any stage is still under way.
  const hasActiveStage = computed(() => bundle.value?.stages?.some((s) => (s.status === "queued" || s.status === "running") && !s.stale) ?? false);
  // Uncertain values are still open: someone has to come back to them.
  const pendingCount = computed(() => bundle.value?.fields.filter((f) => f.status === "pending" || f.status === "edited" || f.status === "uncertain").length ?? 0);
  const averageConfidence = computed(() => {
    const fields = bundle.value?.fields.filter((f) => f.status === "pending" || f.status === "edited") ?? [];
    if (fields.length === 0) return null;
    return Math.round(fields.reduce((sum, f) => sum + f.confidence, 0) / fields.length);
  });

  function scheduleNextPoll(): void {
    if (timer !== null) clearTimeout(timer);
    if (!isProcessing.value && !hasActiveStage.value) return;
    timer = setTimeout(() => void load(), POLL_MS);
  }

  async function load(): Promise<void> {
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = bundle.value === null;
    error.value = null;
    try {
      const response = await getArchiveItemFileOcr(archiveItemId.value, self.signal);
      if (controller !== self) return;
      bundle.value = response.data;
      scheduleNextPoll();
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      error.value = err instanceof Error ? err.message : "Failed to load";
    } finally {
      if (controller === self) loading.value = false;
    }
  }

  async function runAction(fn: () => Promise<unknown>): Promise<void> {
    actionError.value = null;
    try {
      await fn();
      await load();
    } catch (err) {
      actionError.value = err instanceof Error ? err.message : "Failed to save";
    }
  }

  const accept = (fieldId: number) => runAction(() => acceptExtractedField(archiveItemId.value, fieldId));
  const reject = (fieldId: number) => runAction(() => rejectExtractedField(archiveItemId.value, fieldId));
  const edit = (fieldId: number, value: string) => runAction(() => editExtractedField(archiveItemId.value, fieldId, value));
  const acceptHighConfidence = (threshold?: number) => runAction(() => acceptHighConfidenceFields(archiveItemId.value, threshold));
  const runOcr = () => runAction(() => runArchiveItemFileOcr(archiveItemId.value));
  const runStage = (stage: OcrStageName) => runAction(() => runArchiveItemFileOcrStage(archiveItemId.value, stage));
  const setDocumentType = (documentType: DocumentType | null) => runAction(() => setOcrDocumentType(archiveItemId.value, documentType));
  const reviewDate = (dateId: number, decision: "accept" | "reject") => runAction(() => reviewExtractedDate(archiveItemId.value, dateId, decision));
  const confirmMatch = (matchId: number, candidate: EntityMatchCandidate) =>
    runAction(() => confirmEntityMatch(archiveItemId.value, matchId, candidate.id !== null ? { entity_id: candidate.id } : { key: candidate.key ?? "" }));
  const decideMatch = (matchId: number, decision: "no-match" | "reset") => runAction(() => decideEntityMatch(archiveItemId.value, matchId, decision));
  /** A record the reviewer found by searching, rather than one of the candidates. */
  const linkMatch = (matchId: number, entityId: number | string) => runAction(() => confirmEntityMatch(archiveItemId.value, matchId, { entity_id: entityId }));
  const markUncertain = (fieldId: number, note: string | null) => runAction(() => markExtractedFieldUncertain(archiveItemId.value, fieldId, note));
  const transcribeRegion = (regionId: number, fieldKey: string, value: string) => runAction(() => transcribeOcrRegion(archiveItemId.value, regionId, fieldKey, value));
  const setRegionDismissed = (regionId: number, dismissed: boolean) => runAction(() => setOcrRegionDismissed(archiveItemId.value, regionId, dismissed));
  const transcribeFormField = (formFieldId: number, value: string) => runAction(() => transcribeOcrFormField(archiveItemId.value, formFieldId, value));
  const requestSuggestion = (regionId: number) => runAction(() => requestHandwritingSuggestion(archiveItemId.value, regionId));
  const rejectSuggestion = (suggestionId: number) => runAction(() => reviewHandwritingSuggestion(archiveItemId.value, suggestionId, "rejected"));

  watch(archiveItemId, () => void load(), { immediate: true });
  onBeforeUnmount(() => {
    controller?.abort();
    if (timer !== null) clearTimeout(timer);
  });

  return {
    bundle, loading, error, actionError, isProcessing, pendingCount, averageConfidence,
    accept, reject, edit, acceptHighConfidence, runOcr, runStage, setDocumentType, reviewDate, confirmMatch, decideMatch, linkMatch,
    markUncertain, transcribeFormField, requestSuggestion, rejectSuggestion, transcribeRegion, setRegionDismissed,
  };
}
