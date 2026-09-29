import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { listArchiveItems } from "@/api/archive";
import { listArtists } from "@/api/artists";
import { listArtworks } from "@/api/artworks";
import { listEvents } from "@/api/events";
import {
  ARCHIVE_ITEM_TYPES,
  type ArchiveFacets,
  type ArchiveItem,
  type ArchiveItemType,
} from "@/types/archive";
import type { ArtistListItem } from "@/types/artist";
import type { ArtworkListItem } from "@/types/artwork";
import type { EventListItem } from "@/types/event";

export const MIN_TERM_LENGTH = 2;
export const PER_SECTION = 6;
export const SEARCH_DEBOUNCE_MS = 300;

export const SECTION_KEYS = [
  "artists",
  "artworks",
  "archive",
  "events",
] as const;
export type SectionKey = (typeof SECTION_KEYS)[number];

export interface Section<T> {
  items: T[];
  total: number;
  loading: boolean;
  error: unknown;
}

const empty = <T>(): Section<T> => ({
  items: [],
  total: 0,
  loading: false,
  error: null,
});

export interface SiteSearchQuery {
  q: string;
  /** Sections the visitor wants shown; all four when nothing is excluded. */
  types: SectionKey[];
  itemTypes: ArchiveItemType[];
  verifiedOnly: boolean;
}

const str = (v: unknown): string => (typeof v === "string" ? v : "");
const list = (v: unknown): string[] =>
  (Array.isArray(v) ? v : v === undefined || v === null ? [] : [v]).filter(
    (x): x is string => typeof x === "string" && x !== "",
  );

export function parseSiteSearchQuery(query: LocationQueryRaw): SiteSearchQuery {
  const types = list(query.type).filter((t): t is SectionKey =>
    (SECTION_KEYS as readonly string[]).includes(t),
  );
  return {
    q: str(query.q),
    types: types.length > 0 ? types : [...SECTION_KEYS],
    itemTypes: list(query.item_type).filter((t): t is ArchiveItemType =>
      (ARCHIVE_ITEM_TYPES as readonly string[]).includes(t),
    ),
    verifiedOnly: str(query.verified) === "1",
  };
}

/**
 * One term, four independent searches. Each kind loads, fails and retries on its
 * own, so one slow or broken source never blanks the results the others found.
 * Filters (shown sections, archive material type, verified-only) live in the
 * URL, mirroring useArchiveList/useArtistsList so a results page is always
 * shareable and reloadable.
 */
export function useSiteSearch() {
  const route = useRoute();
  const router = useRouter();

  const sections = reactive({
    artists: empty<ArtistListItem>(),
    artworks: empty<ArtworkListItem>(),
    archive: empty<ArchiveItem>(),
    events: empty<EventListItem>(),
  });
  const archiveFacets = ref<ArchiveFacets | null>(null);

  const query = computed(() => parseSiteSearchQuery(route.query));
  const searchInput = ref(query.value.q);
  watch(
    () => query.value.q,
    (v) => (searchInput.value = v),
  );

  const controllers: Partial<Record<SectionKey, AbortController>> = {};
  let debounceTimer: ReturnType<typeof setTimeout> | null = null;

  function push(patch: Partial<SiteSearchQuery>): void {
    const next = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (next.q) target.q = next.q;
    if (next.types.length < SECTION_KEYS.length) target.type = next.types;
    if (next.itemTypes.length) target.item_type = next.itemTypes;
    if (next.verifiedOnly) target.verified = "1";
    void router.push({ query: target });
  }

  function run<T>(
    key: SectionKey,
    fetcher: (
      signal: AbortSignal,
    ) => Promise<{ data: T[]; meta: { total: number } }>,
  ): Promise<void> {
    controllers[key]?.abort();
    const self = new AbortController();
    controllers[key] = self;
    const section = sections[key] as Section<T>;
    section.loading = true;
    section.error = null;

    return fetcher(self.signal)
      .then((response) => {
        if (controllers[key] !== self) return;
        section.items = response.data;
        section.total = response.meta.total;
        if (key === "archive") {
          const facets = (response.meta as { facets?: ArchiveFacets }).facets;
          archiveFacets.value = facets ?? null;
        }
      })
      .catch((err: unknown) => {
        if (err instanceof DOMException && err.name === "AbortError") return;
        if (controllers[key] !== self) return;
        section.items = [];
        section.total = 0;
        section.error = err;
      })
      .finally(() => {
        if (controllers[key] === self) section.loading = false;
      });
  }

  const active = computed(() => query.value.q.trim().length >= MIN_TERM_LENGTH);

  function loaders(q: string): Record<SectionKey, () => Promise<void>> {
    const { itemTypes, verifiedOnly } = query.value;
    return {
      artists: () =>
        run("artists", (signal) =>
          listArtists(
            {
              q,
              per_page: PER_SECTION,
              verified_status: verifiedOnly ? "verified" : undefined,
            },
            signal,
          ),
        ),
      artworks: () =>
        run("artworks", (signal) =>
          listArtworks({ q, per_page: PER_SECTION }, signal),
        ),
      archive: () =>
        run("archive", (signal) =>
          listArchiveItems(
            {
              q,
              per_page: PER_SECTION,
              item_type: itemTypes,
              include_facets: 1,
            },
            signal,
          ),
        ),
      events: () =>
        run("events", (signal) =>
          listEvents({ q, per_page: PER_SECTION }, signal),
        ),
    };
  }

  function reset(): void {
    for (const key of SECTION_KEYS) {
      controllers[key]?.abort();
      Object.assign(sections[key], empty());
    }
    archiveFacets.value = null;
  }

  function loadAll(): void {
    const q = query.value.q.trim();
    if (q.length < MIN_TERM_LENGTH) return reset();
    const load = loaders(q);
    for (const key of SECTION_KEYS) void load[key]();
  }

  watch(() => route.query, loadAll, { immediate: true, deep: true });
  onBeforeUnmount(() => {
    Object.values(controllers).forEach((c) => c?.abort());
    if (debounceTimer !== null) clearTimeout(debounceTimer);
  });

  function setSearch(value: string): void {
    searchInput.value = value;
    if (debounceTimer !== null) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      debounceTimer = null;
      if (value.trim() !== query.value.q) push({ q: value.trim() });
    }, SEARCH_DEBOUNCE_MS);
  }

  const toggle = <T>(current: T[], value: T): T[] =>
    current.includes(value)
      ? current.filter((x) => x !== value)
      : [...current, value];

  const anyLoading = computed(() =>
    Object.values(sections).some((s) => s.loading),
  );
  const anyError = computed(() =>
    Object.values(sections).some((s) => s.error !== null),
  );
  const totalResults = computed(() =>
    Object.values(sections).reduce((sum, s) => sum + s.total, 0),
  );
  /** Nothing found, and nothing is still on its way or failed (a failure is not "no results"). */
  const nothingFound = computed(
    () =>
      active.value &&
      !anyLoading.value &&
      !anyError.value &&
      totalResults.value === 0,
  );

  return {
    sections,
    archiveFacets,
    active,
    anyLoading,
    anyError,
    totalResults,
    nothingFound,
    query,
    searchInput,
    setSearch,
    retry: (key: SectionKey) => loaders(query.value.q.trim())[key](),
    toggleType: (v: SectionKey) =>
      push({ types: toggle(query.value.types, v) }),
    toggleItemType: (v: ArchiveItemType) =>
      push({ itemTypes: toggle(query.value.itemTypes, v) }),
    setVerifiedOnly: (v: boolean) => push({ verifiedOnly: v }),
    clearFilters: () =>
      push({ types: [...SECTION_KEYS], itemTypes: [], verifiedOnly: false }),
  };
}
