import { ref, watch, onBeforeUnmount, type Ref } from "vue";

import { getArtwork } from "@/api/artworks";
import type { Artwork } from "@/types/artwork";

/** Loads a single artwork by numeric id, aborting stale requests on id change. */
export function useArtwork(id: Ref<number>) {
  const artwork = ref<Artwork | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  let abortController: AbortController | null = null;

  async function load(): Promise<void> {
    if (!Number.isFinite(id.value) || id.value <= 0) {
      artwork.value = null;
      error.value = null;
      return;
    }
    abortController?.abort();
    const self = new AbortController();
    abortController = self;
    loading.value = true;
    error.value = null;
    try {
      const response = await getArtwork(id.value, self.signal);
      if (abortController !== self) return;
      artwork.value = response.data;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      if (abortController !== self) return;
      artwork.value = null;
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

  return { artwork, loading, error, retry: load };
}
