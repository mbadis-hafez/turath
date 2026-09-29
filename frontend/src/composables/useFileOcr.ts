import { computed, onBeforeUnmount, ref, watch, type Ref } from "vue";

import { acceptExtractedField, acceptHighConfidenceFields, editExtractedField, getArchiveItemFileOcr, rejectExtractedField, runArchiveItemFileOcr, transcribeOcrFormField } from "@/api/archive";
import type { FileOcrBundle } from "@/types/ocr";

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
  const pendingCount = computed(() => bundle.value?.fields.filter((f) => f.status === "pending" || f.status === "edited").length ?? 0);
  const averageConfidence = computed(() => {
    const fields = bundle.value?.fields.filter((f) => f.status === "pending" || f.status === "edited") ?? [];
    if (fields.length === 0) return null;
    return Math.round(fields.reduce((sum, f) => sum + f.confidence, 0) / fields.length);
  });

  function scheduleNextPoll(): void {
    if (timer !== null) clearTimeout(timer);
    if (!isProcessing.value) return;
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
  const transcribeFormField = (formFieldId: number, value: string) => runAction(() => transcribeOcrFormField(archiveItemId.value, formFieldId, value));

  watch(archiveItemId, () => void load(), { immediate: true });
  onBeforeUnmount(() => {
    controller?.abort();
    if (timer !== null) clearTimeout(timer);
  });

  return { bundle, loading, error, actionError, isProcessing, pendingCount, averageConfidence, accept, reject, edit, acceptHighConfidence, runOcr, transcribeFormField };
}
