import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { listAdminArtworks } from "@/api/artworkCuration";
import type { PaginationMeta } from "@/types/api";
import type { AdminArtworkRow, ArtworkStatus } from "@/types/artworkCuration";

export const ARTWORKS_DEBOUNCE_MS = 300;

export interface ArtworksRegistryQuery {
  q: string;
  status: ArtworkStatus | "";
  missingDimensions: boolean;
  pipelineGap: boolean;
  page: number;
}

const str = (v: unknown): string => (typeof v === "string" ? v : "");

export function parseArtworksQuery(query: LocationQueryRaw): ArtworksRegistryQuery {
  const page = Number.parseInt(str(query.page), 10);
  return {
    q: str(query.q),
    status: str(query.status) as ArtworkStatus | "",
    missingDimensions: str(query.missing_dimensions) === "1",
    pipelineGap: str(query.has_pipeline_gap) === "1",
    page: Number.isFinite(page) && page > 0 ? page : 1,
  };
}

/** Artworks registry: filter/page state lives in the URL; search is debounced. */
export function useAdminArtworks() {
  const route = useRoute();
  const router = useRouter();

  const items = ref<AdminArtworkRow[]>([]);
  const meta = ref<(PaginationMeta & { candidate_count: number }) | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const query = computed(() => parseArtworksQuery(route.query));
  const searchInput = ref(query.value.q);
  watch(() => query.value.q, (v) => (searchInput.value = v));

  let controller: AbortController | null = null;
  let timer: ReturnType<typeof setTimeout> | null = null;

  function push(patch: Partial<ArtworksRegistryQuery>): void {
    const next = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (next.q) target.q = next.q;
    if (next.status) target.status = next.status;
    if (next.missingDimensions) target.missing_dimensions = "1";
    if (next.pipelineGap) target.has_pipeline_gap = "1";
    if (next.page > 1) target.page = String(next.page);
    void router.push({ query: target });
  }

  function setSearch(value: string): void {
    searchInput.value = value;
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      timer = null;
      if (value !== query.value.q) push({ q: value, page: 1 });
    }, ARTWORKS_DEBOUNCE_MS);
  }

  async function load(): Promise<void> {
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = true;
    error.value = null;
    try {
      const q = query.value;
      const response = await listAdminArtworks(
        {
          q: q.q || undefined,
          status: q.status || undefined,
          missing_dimensions: q.missingDimensions ? 1 : undefined,
          has_pipeline_gap: q.pipelineGap ? 1 : undefined,
          page: q.page,
        },
        self.signal,
      );
      if (controller !== self) return;
      items.value = response.data;
      meta.value = response.meta;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      if (controller !== self) return;
      items.value = [];
      meta.value = null;
      error.value = err;
    } finally {
      if (controller === self) loading.value = false;
    }
  }

  watch(() => route.query, () => void load(), { immediate: true, deep: true });
  onBeforeUnmount(() => {
    controller?.abort();
    if (timer) clearTimeout(timer);
  });

  return {
    items, meta, loading, error, query, searchInput, retry: load, setSearch,
    setStatus: (v: ArtworkStatus | "") => push({ status: v, page: 1 }),
    setMissingDimensions: (v: boolean) => push({ missingDimensions: v, page: 1 }),
    setPipelineGap: (v: boolean) => push({ pipelineGap: v, page: 1 }),
    setPage: (page: number) => push({ page }),
    clear: () => void router.push({ query: {} }),
  };
}
