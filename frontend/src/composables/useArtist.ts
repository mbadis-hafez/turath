import { ref, watch, onBeforeUnmount, type Ref } from "vue";

import { getArtist } from "@/api/artists";
import type { Artist } from "@/types/artist";

/** Loads a single artist by slug, aborting stale requests on slug change. */
export function useArtist(slug: Ref<string>) {
  const artist = ref<Artist | null>(null);
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
      const response = await getArtist(slug.value, self.signal);
      if (abortController !== self) return;
      artist.value = response.data;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      if (abortController !== self) return;
      artist.value = null;
      error.value = err;
    } finally {
      if (abortController === self) loading.value = false;
    }
  }

  watch(
    slug,
    () => {
      void load();
    },
    { immediate: true },
  );

  onBeforeUnmount(() => {
    abortController?.abort();
  });

  return { artist, loading, error, retry: load };
}
