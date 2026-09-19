import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { listArtists } from "@/api/artists";
import type { ArtistListItem, ArtistsQueryParams } from "@/types/artist";
import type { PaginationMeta } from "@/types/api";

export const ARTIST_SEARCH_DEBOUNCE_MS = 300;

/** UI-level sort options; mapped to API sort values when fetching. */
export type ArtistsSortOption = "name" | "recent";

export interface ArtistsListQuery {
  q: string;
  sort: ArtistsSortOption;
  verifiedOnly: boolean;
  page: number;
}

function stringParam(value: unknown): string {
  return typeof value === "string" ? value : "";
}

export function parseArtistsQuery(query: LocationQueryRaw): ArtistsListQuery {
  const page = Number.parseInt(stringParam(query.page), 10);
  return {
    q: stringParam(query.q),
    sort: stringParam(query.sort) === "recent" ? "recent" : "name",
    verifiedOnly: stringParam(query.verified) === "1",
    page: Number.isFinite(page) && page > 0 ? page : 1,
  };
}

export function artistsQueryToParams(
  parsed: ArtistsListQuery,
): ArtistsQueryParams {
  const params: ArtistsQueryParams = {};
  if (parsed.q !== "") params.q = parsed.q;
  if (parsed.sort === "recent") params.sort = "-created_at";
  if (parsed.verifiedOnly) params.verified_status = "verified";
  params.page = parsed.page;
  return params;
}

/**
 * Owns the artists list: all filter/page state lives in the URL query string,
 * every fetch aborts the previous one, and search input is debounced before
 * it is written back to the URL.
 */
export function useArtistsList() {
  const route = useRoute();
  const router = useRouter();

  const items = ref<ArtistListItem[]>([]);
  const meta = ref<PaginationMeta | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const query = computed(() => parseArtistsQuery(route.query));

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
  function pushQuery(patch: Partial<ArtistsListQuery>): void {
    const next = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (next.q !== "") target.q = next.q;
    if (next.sort !== "name") target.sort = next.sort;
    if (next.verifiedOnly) target.verified = "1";
    if (next.page > 1) target.page = String(next.page);
    void router.push({ query: target });
  }

  function setSearch(term: string): void {
    searchInput.value = term;
    if (debounceTimer !== null) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      debounceTimer = null;
      if (term !== query.value.q) pushQuery({ q: term, page: 1 });
    }, ARTIST_SEARCH_DEBOUNCE_MS);
  }

  function setSort(sort: ArtistsSortOption): void {
    pushQuery({ sort, page: 1 });
  }

  function setVerifiedOnly(verifiedOnly: boolean): void {
    pushQuery({ verifiedOnly, page: 1 });
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
      const response = await listArtists(
        artistsQueryToParams(query.value),
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
    setSort,
    setVerifiedOnly,
    setPage,
    clearFilters,
  };
}

export type UseArtistsListReturn = ReturnType<typeof useArtistsList>;
