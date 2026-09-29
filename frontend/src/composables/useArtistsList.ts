import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { isLocale, type AppLocale } from "@/i18n";
import { listArtists } from "@/api/artists";
import type {
  ArtistFacets,
  ArtistLetters,
  ArtistListItem,
  ArtistsQueryParams,
} from "@/types/artist";
import type { PaginationMeta } from "@/types/api";

export const ARTIST_SEARCH_DEBOUNCE_MS = 300;
export const PAGE_SIZE = 100;

export type ArtistsSortOption = "alphabetical" | "materials";

export interface ArtistsListQuery {
  q: string;
  sort: ArtistsSortOption;
  city: string;
  themeId: number | null;
  itemType: string;
}

function stringParam(value: unknown): string {
  return typeof value === "string" ? value : "";
}

function numberParam(value: unknown): number | null {
  const n = Number.parseInt(stringParam(value), 10);
  return Number.isFinite(n) && n > 0 ? n : null;
}

export function parseArtistsQuery(query: LocationQueryRaw): ArtistsListQuery {
  return {
    q: stringParam(query.q),
    sort: stringParam(query.sort) === "materials" ? "materials" : "alphabetical",
    city: stringParam(query.city),
    themeId: numberParam(query.theme),
    itemType: stringParam(query.type),
  };
}

export function artistsQueryToParams(
  parsed: ArtistsListQuery,
  locale: "ar" | "en",
  pageNumber: number,
): ArtistsQueryParams {
  const params: ArtistsQueryParams = {};
  if (parsed.q !== "") params.q = parsed.q;
  if (parsed.sort === "materials") {
    params.sort = "-materials_count";
  } else {
    params.sort = locale === "ar" ? "name_ar" : "name_en";
  }
  if (parsed.city !== "") params.city = parsed.city;
  if (parsed.themeId !== null) params.theme_id = parsed.themeId;
  if (parsed.itemType !== "") params.item_type = parsed.itemType;
  params.per_page = PAGE_SIZE;
  params.page = pageNumber;
  if (pageNumber === 1) params.include_facets = 1;
  return params;
}

/**
 * Owns the artists directory list. Filters live in the URL, results accumulate
 * via "load more", and every fetch aborts the previous one. Search input is
 * debounced before it is written back to the URL.
 */
export function useArtistsList() {
  const route = useRoute();
  const router = useRouter();
  const locale = computed<AppLocale>(() =>
    isLocale(route.params.locale) ? route.params.locale : "ar",
  );

  const items = ref<ArtistListItem[]>([]);
  const meta = ref<PaginationMeta | null>(null);
  const facets = ref<ArtistFacets | null>(null);
  const letters = ref<ArtistLetters | null>(null);
  const materialsTotal = ref(0);
  const loading = ref(false);
  const loadingMore = ref(false);
  const error = ref<unknown>(null);
  const page = ref(1);

  const query = computed(() => parseArtistsQuery(route.query));
  const hasMore = computed(() =>
    meta.value === null ? false : items.value.length < meta.value.total,
  );

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

  function pushQuery(patch: Partial<ArtistsListQuery>): void {
    const next = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (next.q !== "") target.q = next.q;
    if (next.sort !== "alphabetical") target.sort = next.sort;
    if (next.city !== "") target.city = next.city;
    if (next.themeId !== null) target.theme = String(next.themeId);
    if (next.itemType !== "") target.type = next.itemType;
    void router.push({ query: target });
  }

  function setSearch(term: string): void {
    searchInput.value = term;
    if (debounceTimer !== null) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      debounceTimer = null;
      if (term !== query.value.q) pushQuery({ q: term });
    }, ARTIST_SEARCH_DEBOUNCE_MS);
  }

  function setSort(sort: ArtistsSortOption): void {
    pushQuery({ sort });
  }

  function setCity(city: string): void {
    pushQuery({ city });
  }

  function setThemeId(themeId: number | null): void {
    pushQuery({ themeId });
  }

  function setItemType(itemType: string): void {
    pushQuery({ itemType });
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
    loadingMore.value = false;
    error.value = null;
    page.value = 1;

    try {
      const response = await listArtists(
        artistsQueryToParams(query.value, locale.value, 1),
        self.signal,
      );
      if (abortController !== self) return;
      items.value = response.data;
      meta.value = response.meta;
      facets.value = response.meta.facets ?? null;
      letters.value = response.meta.letters ?? null;
      materialsTotal.value = response.meta.materials_total ?? 0;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      if (abortController !== self) return;
      items.value = [];
      meta.value = null;
      facets.value = null;
      letters.value = null;
      materialsTotal.value = 0;
      error.value = err;
    } finally {
      if (abortController === self) loading.value = false;
    }
  }

  async function loadMore(): Promise<void> {
    if (loading.value || loadingMore.value || !hasMore.value) return;
    const self = abortController ?? new AbortController();
    abortController = self;
    loadingMore.value = true;

    try {
      const response = await listArtists(
        artistsQueryToParams(query.value, locale.value, page.value + 1),
        self.signal,
      );
      if (abortController !== self) return;
      page.value += 1;
      const seen = new Set(items.value.map((i) => i.id));
      items.value = [
        ...items.value,
        ...response.data.filter((i) => !seen.has(i.id)),
      ];
      meta.value = response.meta;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      error.value = err;
    } finally {
      loadingMore.value = false;
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
    facets,
    letters,
    materialsTotal,
    loading,
    loadingMore,
    hasMore,
    error,
    retry: load,
    query,
    searchInput,
    setSearch,
    setSort,
    setCity,
    setThemeId,
    setItemType,
    loadMore,
    clearFilters,
  };
}

export type UseArtistsListReturn = ReturnType<typeof useArtistsList>;
