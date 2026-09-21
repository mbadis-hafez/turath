import { computed, onBeforeUnmount, ref, watch, type Ref } from "vue";

import { listArtistArchiveItems } from "@/api/archive";
import type { ArchiveItem } from "@/types/archive";

/** The published archive material linked to one artist, oldest first for the career list. */
export function useArtistArchive(artistId: Ref<number>) {
  const items = ref<ArchiveItem[]>([]);
  const total = ref(0);
  const loading = ref(false);
  const error = ref<unknown>(null);
  let controller: AbortController | null = null;

  async function load(): Promise<void> {
    if (!Number.isFinite(artistId.value) || artistId.value <= 0) {
      items.value = [];
      total.value = 0;
      return;
    }
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = true;
    error.value = null;
    try {
      const response = await listArtistArchiveItems(artistId.value, { per_page: 100 }, self.signal);
      if (controller !== self) return;
      items.value = response.data;
      total.value = response.meta.total;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      if (controller !== self) return;
      items.value = [];
      total.value = 0;
      error.value = err;
    } finally {
      if (controller === self) loading.value = false;
    }
  }

  watch(artistId, () => void load(), { immediate: true });
  onBeforeUnmount(() => controller?.abort());

  /** Undated items sort last, and within a year the reference code keeps the order stable. */
  const chronological = computed(() =>
    [...items.value].sort((a, b) => {
      const ya = a.content?.year_from ?? Number.MAX_SAFE_INTEGER;
      const yb = b.content?.year_from ?? Number.MAX_SAFE_INTEGER;
      return ya - yb || (a.legacy_ref ?? "").localeCompare(b.legacy_ref ?? "");
    }),
  );

  return { items, chronological, total, loading, error, retry: load };
}
