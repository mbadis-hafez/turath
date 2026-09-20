import { onBeforeUnmount, ref, watch, type Ref } from "vue";

import { getArtistCuration } from "@/api/artistCuration";
import type { ArtistCuration } from "@/types/artistCuration";

/** Loads one artist's internal curation bundle, aborting stale requests. */
export function useArtistCuration(id: Ref<number>) {
  const curation = ref<ArtistCuration | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);
  let controller: AbortController | null = null;

  async function load(): Promise<void> {
    if (!Number.isFinite(id.value) || id.value <= 0) return;
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = true;
    error.value = null;
    try {
      const response = await getArtistCuration(id.value, self.signal);
      if (controller !== self) return;
      curation.value = response.data;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      if (controller !== self) return;
      curation.value = null;
      error.value = err;
    } finally {
      if (controller === self) loading.value = false;
    }
  }

  watch(id, () => void load(), { immediate: true });
  onBeforeUnmount(() => controller?.abort());

  return { curation, loading, error, retry: load, set: (c: ArtistCuration) => (curation.value = c) };
}
