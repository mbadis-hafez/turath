import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { listArchiveItems } from "@/api/archive";
import {
  ARCHIVE_ITEM_TYPES, type AccessFacet, type ArchiveFacets, type ArchiveItem, type ArchiveItemType,
} from "@/types/archive";

export const ARCHIVE_SEARCH_DEBOUNCE_MS = 300;
export const PAGE_SIZE = 12;

export type ArchiveView = "grid" | "list";

export interface ArchiveListQuery {
  q: string;
  types: ArchiveItemType[];
  places: string[];
  themeIds: number[];
  access: AccessFacet[];
  view: ArchiveView;
  includeUnpublished: boolean;
  artistId: number | null;
}

const str = (v: unknown): string => (typeof v === "string" ? v : "");
const list = (v: unknown): string[] => (Array.isArray(v) ? v : v === undefined || v === null ? [] : [v]).filter((x): x is string => typeof x === "string" && x !== "");

export function parseArchiveQuery(query: LocationQueryRaw): ArchiveListQuery {
  const artist = Number.parseInt(str(query.artist_id), 10);
  return {
    q: str(query.q),
    types: list(query.type).filter((t): t is ArchiveItemType => (ARCHIVE_ITEM_TYPES as readonly string[]).includes(t)),
    places: list(query.place),
    themeIds: list(query.theme).map((n) => Number.parseInt(n, 10)).filter((n) => Number.isFinite(n) && n > 0),
    access: list(query.access).filter((a): a is AccessFacet => a === "full" || a === "preview"),
    view: str(query.view) === "list" ? "list" : "grid",
    includeUnpublished: str(query.status) === "all",
    artistId: artist > 0 ? artist : null,
  };
}

/**
 * Archive browse. Filters live in the URL; results accumulate ("load more") in
 * memory, so the URL never encodes a page and a shared link always starts at the top.
 */
export function useArchiveList() {
  const route = useRoute();
  const router = useRouter();

  const items = ref<ArchiveItem[]>([]);
  const total = ref(0);
  const facets = ref<ArchiveFacets | null>(null);
  const loading = ref(false);
  const loadingMore = ref(false);
  const error = ref<unknown>(null);
  const page = ref(1);
  const hasMore = computed(() => items.value.length < total.value);

  const query = computed(() => parseArchiveQuery(route.query));
  const searchInput = ref(query.value.q);
  watch(() => query.value.q, (v) => (searchInput.value = v));

  let controller: AbortController | null = null;
  let timer: ReturnType<typeof setTimeout> | null = null;

  function push(patch: Partial<ArchiveListQuery>): void {
    const n = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (n.q) target.q = n.q;
    if (n.types.length) target.type = n.types;
    if (n.places.length) target.place = n.places;
    if (n.themeIds.length) target.theme = n.themeIds.map(String);
    if (n.access.length) target.access = n.access;
    if (n.view === "list") target.view = "list";
    if (n.includeUnpublished) target.status = "all";
    if (n.artistId) target.artist_id = String(n.artistId);
    void router.push({ query: target });
  }

  const toggle = <T>(current: T[], value: T): T[] => (current.includes(value) ? current.filter((x) => x !== value) : [...current, value]);

  function setSearch(value: string): void {
    searchInput.value = value;
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      timer = null;
      if (value !== query.value.q) push({ q: value });
    }, ARCHIVE_SEARCH_DEBOUNCE_MS);
  }

  function params(pageNumber: number) {
    const q = query.value;
    return {
      q: q.q || undefined, item_type: q.types, place: q.places, theme_id: q.themeIds, access: q.access,
      artist_id: q.artistId ?? undefined, status: q.includeUnpublished ? ("all" as const) : undefined,
      per_page: PAGE_SIZE, page: pageNumber, include_facets: pageNumber === 1 ? (1 as const) : undefined,
    };
  }

  /** Reloads from the top whenever a filter changes; facet counts come with that first page. */
  async function load(): Promise<void> {
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = true;
    loadingMore.value = false;
    error.value = null;
    page.value = 1;
    try {
      const response = await listArchiveItems(params(1), self.signal);
      if (controller !== self) return;
      items.value = response.data;
      total.value = response.meta.total;
      facets.value = response.meta.facets ?? facets.value;
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

  async function loadMore(): Promise<void> {
    if (loading.value || loadingMore.value || !hasMore.value) return;
    const self = controller ?? new AbortController();
    controller = self;
    loadingMore.value = true;
    try {
      const response = await listArchiveItems(params(page.value + 1), self.signal);
      if (controller !== self) return;
      page.value += 1;
      // A row that shifted between pages must not appear twice.
      const seen = new Set(items.value.map((i) => i.id));
      items.value = [...items.value, ...response.data.filter((i) => !seen.has(i.id))];
      total.value = response.meta.total;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      error.value = err;
    } finally {
      loadingMore.value = false;
    }
  }

  watch(() => route.query, () => void load(), { immediate: true, deep: true });
  onBeforeUnmount(() => {
    controller?.abort();
    if (timer) clearTimeout(timer);
  });

  return {
    items, total, facets, loading, loadingMore, hasMore, error, query, searchInput, retry: load, loadMore, setSearch,
    toggleType: (v: ArchiveItemType) => push({ types: toggle(query.value.types, v) }),
    togglePlace: (v: string) => push({ places: toggle(query.value.places, v) }),
    toggleTheme: (v: number) => push({ themeIds: toggle(query.value.themeIds, v) }),
    toggleAccess: (v: AccessFacet) => push({ access: toggle(query.value.access, v) }),
    setView: (v: ArchiveView) => push({ view: v }),
    setIncludeUnpublished: (v: boolean) => push({ includeUnpublished: v }),
    clearArtist: () => push({ artistId: null }),
    /** Clears every filter but keeps the view the visitor chose. */
    clearFilters: () => push({ q: "", types: [], places: [], themeIds: [], access: [], includeUnpublished: false, artistId: null }),
  };
}
