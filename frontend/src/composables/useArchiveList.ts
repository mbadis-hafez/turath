import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { listArchiveItems } from "@/api/archive";
import type { PaginationMeta } from "@/types/api";
import { ARCHIVE_ITEM_TYPES, type ArchiveItem, type ArchiveItemType } from "@/types/archive";

export const ARCHIVE_SEARCH_DEBOUNCE_MS = 300;

export interface ArchiveListQuery {
  q: string;
  type: ArchiveItemType | "";
  includeUnpublished: boolean;
  page: number;
}

const str = (v: unknown): string => (typeof v === "string" ? v : "");

export function parseArchiveQuery(query: LocationQueryRaw): ArchiveListQuery {
  const page = Number.parseInt(str(query.page), 10);
  const type = str(query.type);
  return {
    q: str(query.q),
    type: (ARCHIVE_ITEM_TYPES as readonly string[]).includes(type) ? (type as ArchiveItemType) : "",
    includeUnpublished: str(query.status) === "all",
    page: Number.isFinite(page) && page > 0 ? page : 1,
  };
}

/** Archive list: filter/page state lives in the URL; search is debounced. `status=all` only takes effect for archive managers (server-enforced). */
export function useArchiveList() {
  const route = useRoute();
  const router = useRouter();

  const items = ref<ArchiveItem[]>([]);
  const meta = ref<PaginationMeta | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const query = computed(() => parseArchiveQuery(route.query));
  const searchInput = ref(query.value.q);
  watch(() => query.value.q, (v) => (searchInput.value = v));

  let controller: AbortController | null = null;
  let timer: ReturnType<typeof setTimeout> | null = null;

  function push(patch: Partial<ArchiveListQuery>): void {
    const next = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (next.q) target.q = next.q;
    if (next.type) target.type = next.type;
    if (next.includeUnpublished) target.status = "all";
    if (next.page > 1) target.page = String(next.page);
    void router.push({ query: target });
  }

  function setSearch(value: string): void {
    searchInput.value = value;
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      timer = null;
      if (value !== query.value.q) push({ q: value, page: 1 });
    }, ARCHIVE_SEARCH_DEBOUNCE_MS);
  }

  async function load(): Promise<void> {
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = true;
    error.value = null;
    try {
      const q = query.value;
      const response = await listArchiveItems(
        { q: q.q || undefined, item_type: q.type || undefined, status: q.includeUnpublished ? "all" : undefined, page: q.page },
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
    setType: (v: ArchiveItemType | "") => push({ type: v, page: 1 }),
    setIncludeUnpublished: (v: boolean) => push({ includeUnpublished: v, page: 1 }),
    setPage: (page: number) => push({ page }),
    clear: () => void router.push({ query: {} }),
  };
}
