import { computed, ref, watch, onBeforeUnmount, type Ref } from "vue";

import { listArtistArtworks } from "@/api/artworks";
import type { ArtworkListItem } from "@/types/artwork";
import type { PaginationMeta } from "@/types/api";

/**
 * Loads the published artworks of one artist (for the artist detail tab).
 * Starts empty for invalid ids so the tab renders nothing until the artist
 * itself has loaded.
 */
export function useArtistArtworks(artistId: Ref<number>) {
  const items = ref<ArtworkListItem[]>([]);
  const meta = ref<PaginationMeta | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const count = computed(() => meta.value?.total ?? 0);

  let abortController: AbortController | null = null;

  async function load(): Promise<void> {
    if (!Number.isFinite(artistId.value) || artistId.value <= 0) {
      items.value = [];
      meta.value = null;
      return;
    }
    abortController?.abort();
    const self = new AbortController();
    abortController = self;
    loading.value = true;
    error.value = null;
    try {
      const response = await listArtistArtworks(artistId.value, {}, self.signal);
      if (abortController !== self) return;
      items.value = response.data;
      meta.value = response.meta;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      if (abortController !== self) return;
      items.value = [];
      meta.value = null;
      error.value = err;
    } finally {
      if (abortController === self) loading.value = false;
    }
  }

  watch(
    artistId,
    () => {
      void load();
    },
    { immediate: true },
  );

  onBeforeUnmount(() => {
    abortController?.abort();
  });

  return { items, meta, loading, error, count, retry: load };
}
