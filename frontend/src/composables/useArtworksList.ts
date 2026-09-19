import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { listArtworks } from "@/api/artworks";
import type {
  ArtworkCategory,
  ArtworkListItem,
  ArtworkSort,
  ArtworksQueryParams,
} from "@/types/artwork";
import type { PaginationMeta } from "@/types/api";

export const ARTWORK_SEARCH_DEBOUNCE_MS = 300;

const CATEGORIES: ArtworkCategory[] = [
  "painting",
  "drawing",
  "printmaking",
  "sculpture",
  "mixed_media",
  "paper_work",
  "photography",
  "installation",
  "other",
];

const SORTS: ArtworkSort[] = [
  "-created_at",
  "created_at",
  "title_ar",
  "title_en",
  "creation_year_from",
  "-creation_year_from",
];

export interface ArtworksListQuery {
  q: string;
  category: "" | ArtworkCategory;
  artist: number | null;
  yearFrom: number | null;
  yearTo: number | null;
  sort: ArtworkSort;
  page: number;
}

function stringParam(value: unknown): string {
  return typeof value === "string" ? value : "";
}

function intParam(value: unknown): number | null {
  const parsed = Number.parseInt(stringParam(value), 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

export function parseArtworksQuery(query: LocationQueryRaw): ArtworksListQuery {
  const page = intParam(query.page) ?? 1;
  const category = stringParam(query.category);
  const sort = stringParam(query.sort);
  return {
    q: stringParam(query.q),
    category: (CATEGORIES as string[]).includes(category)
      ? (category as ArtworkCategory)
      : "",
    artist: intParam(query.artist),
    yearFrom: intParam(query.year_from),
    yearTo: intParam(query.year_to),
    sort: (SORTS as string[]).includes(sort)
      ? (sort as ArtworkSort)
      : "-created_at",
    page,
  };
}

export function artworksQueryToParams(
  parsed: ArtworksListQuery,
): ArtworksQueryParams {
  const params: ArtworksQueryParams = {};
  if (parsed.q !== "") params.q = parsed.q;
  if (parsed.category !== "") params.category = parsed.category;
  if (parsed.artist !== null) params.artist_id = parsed.artist;
  if (parsed.yearFrom !== null) params.year_from = parsed.yearFrom;
  if (parsed.yearTo !== null) params.year_to = parsed.yearTo;
  if (parsed.sort !== "-created_at") params.sort = parsed.sort;
  params.page = parsed.page;
  return params;
}

/**
 * Owns the artworks list: all filter/page state lives in the URL query string,
 * every fetch aborts the previous one, and search input is debounced before
 * it is written back to the URL.
 */
export function useArtworksList() {
  const route = useRoute();
  const router = useRouter();

  const items = ref<ArtworkListItem[]>([]);
  const meta = ref<PaginationMeta | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const query = computed(() => parseArtworksQuery(route.query));

  /** Local mirror of the search box, synced from the URL when it changes. */
  const searchInput = ref(query.value.q);
  watch(
    () => query.value.q,
    (q) => {
      searchInput.value = q;
    },
  );

  let abortController: AbortController | null = null;
  let debounceTimer: ReturnType<typeof setTimeout> | null = null;

  /** Writes a patch to the query string, dropping page when filters change. */
  function pushQuery(patch: Partial<ArtworksListQuery>): void {
    const next = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (next.q !== "") target.q = next.q;
    if (next.category !== "") target.category = next.category;
    if (next.artist !== null) target.artist = String(next.artist);
    if (next.yearFrom !== null) target.year_from = String(next.yearFrom);
    if (next.yearTo !== null) target.year_to = String(next.yearTo);
    if (next.sort !== "-created_at") target.sort = next.sort;
    if (next.page > 1) target.page = String(next.page);
    void router.push({ query: target });
  }

  function setSearch(term: string): void {
    searchInput.value = term;
    if (debounceTimer !== null) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      debounceTimer = null;
      if (term !== query.value.q) pushQuery({ q: term, page: 1 });
    }, ARTWORK_SEARCH_DEBOUNCE_MS);
  }

  function setCategory(category: "" | ArtworkCategory): void {
    pushQuery({ category, page: 1 });
  }

  function setArtist(artist: number | null): void {
    pushQuery({ artist, page: 1 });
  }

  function setYearFrom(year: number | null): void {
    pushQuery({ yearFrom: year, page: 1 });
  }

  function setYearTo(year: number | null): void {
    pushQuery({ yearTo: year, page: 1 });
  }

  function setSort(sort: ArtworkSort): void {
    pushQuery({ sort, page: 1 });
  }

  function setPage(page: number): void {
    pushQuery({ page });
  }

  function clearFilters(): void {
    if (debounceTimer !== null) {
      clearTimeout(debounceTimer);
      debounceTimer = null;
    }
    searchInput.value = "";
    void router.push({ query: {} });
  }

  async function load(): Promise<void> {
    abortController?.abort();
    const self = new AbortController();
    abortController = self;
    loading.value = true;
    error.value = null;
    try {
      const response = await listArtworks(
        artworksQueryToParams(query.value),
        self.signal,
      );
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
    () => route.query,
    () => {
      void load();
    },
    { immediate: true, deep: true },
  );

  onBeforeUnmount(() => {
    abortController?.abort();
    if (debounceTimer !== null) clearTimeout(debounceTimer);
  });

  return {
    items,
    meta,
    loading,
    error,
    retry: load,
    query,
    searchInput,
    setSearch,
    setCategory,
    setArtist,
    setYearFrom,
    setYearTo,
    setSort,
    setPage,
    clearFilters,
  };
}

export type UseArtworksListReturn = ReturnType<typeof useArtworksList>;
