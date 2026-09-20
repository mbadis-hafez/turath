import { onBeforeUnmount, ref, watch, type Ref } from "vue";

import { getImportBatch } from "@/api/imports";
import type { ImportBatch } from "@/types/import";

/** Loads a single import batch by id, aborting stale requests on id change. */
export function useImportBatch(id: Ref<string>) {
  const batch = ref<ImportBatch | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  let abortController: AbortController | null = null;

  async function load(): Promise<void> {
    abortController?.abort();
    const self = new AbortController();
    abortController = self;
    loading.value = true;
    error.value = null;
    try {
      const response = await getImportBatch(id.value, self.signal);
      if (abortController !== self) return;
      batch.value = response.data;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      if (abortController !== self) return;
      batch.value = null;
      error.value = err;
    } finally {
      if (abortController === self) loading.value = false;
    }
  }

  watch(
    id,
    () => {
      void load();
    },
    { immediate: true },
  );

  onBeforeUnmount(() => {
    abortController?.abort();
  });

  return { batch, loading, error, retry: load };
}
