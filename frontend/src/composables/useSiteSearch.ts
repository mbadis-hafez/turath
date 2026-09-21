import { computed, onBeforeUnmount, reactive, watch, type Ref } from "vue";

import { listArchiveItems } from "@/api/archive";
import { listArtists } from "@/api/artists";
import { listArtworks } from "@/api/artworks";
import { listEvents } from "@/api/events";
import type { ArchiveItem } from "@/types/archive";
import type { ArtistListItem } from "@/types/artist";
import type { ArtworkListItem } from "@/types/artwork";
import type { EventListItem } from "@/types/event";

export const MIN_TERM_LENGTH = 2;
export const PER_SECTION = 6;

export interface Section<T> {
  items: T[];
  total: number;
  loading: boolean;
  error: unknown;
}

const empty = <T>(): Section<T> => ({ items: [], total: 0, loading: false, error: null });

/**
 * One term, four independent searches. Each kind loads, fails and retries on its
 * own, so one slow or broken source never blanks the results the others found.
 */
export function useSiteSearch(term: Ref<string>) {
  const sections = reactive({
    artists: empty<ArtistListItem>(),
    artworks: empty<ArtworkListItem>(),
    archive: empty<ArchiveItem>(),
    events: empty<EventListItem>(),
  });
  type Key = keyof typeof sections;

  const controllers: Partial<Record<Key, AbortController>> = {};

  function run<T>(key: Key, fetcher: (signal: AbortSignal) => Promise<{ data: T[]; meta: { total: number } }>): Promise<void> {
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

  const active = computed(() => term.value.trim().length >= MIN_TERM_LENGTH);

  const loaders: Record<Key, (q: string) => Promise<void>> = {
    artists: (q) => run("artists", (signal) => listArtists({ q, per_page: PER_SECTION }, signal)),
    artworks: (q) => run("artworks", (signal) => listArtworks({ q, per_page: PER_SECTION }, signal)),
    archive: (q) => run("archive", (signal) => listArchiveItems({ q, per_page: PER_SECTION }, signal)),
    events: (q) => run("events", (signal) => listEvents({ q, per_page: PER_SECTION }, signal)),
  };

  function reset(): void {
    for (const key of Object.keys(sections) as Key[]) {
      controllers[key]?.abort();
      Object.assign(sections[key], empty());
    }
  }

  watch(
    term,
    (value) => {
      const q = value.trim();
      if (q.length < MIN_TERM_LENGTH) return reset();
      for (const load of Object.values(loaders)) void load(q);
    },
    { immediate: true },
  );
  onBeforeUnmount(() => Object.values(controllers).forEach((c) => c?.abort()));

  const anyLoading = computed(() => Object.values(sections).some((s) => s.loading));
  const anyError = computed(() => Object.values(sections).some((s) => s.error !== null));
  const totalResults = computed(() => Object.values(sections).reduce((sum, s) => sum + s.total, 0));
  /** Nothing found, and nothing is still on its way or failed (a failure is not "no results"). */
  const nothingFound = computed(() => active.value && !anyLoading.value && !anyError.value && totalResults.value === 0);

  return { sections, active, anyLoading, anyError, totalResults, nothingFound, retry: (key: Key) => loaders[key](term.value.trim()) };
}
