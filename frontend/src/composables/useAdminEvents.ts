import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { listAdminEvents } from "@/api/events";
import type { PaginationMeta } from "@/types/api";
import { EVENT_TYPES, type AdminEventRow, type EventStatus, type EventType } from "@/types/event";

export const EVENTS_DEBOUNCE_MS = 300;
export const EVENT_STATUSES: EventStatus[] = ["draft", "published", "hidden"];

export interface AdminEventsState {
  q: string;
  type: EventType | "";
  status: EventStatus | "";
  page: number;
}

const str = (v: unknown): string => (typeof v === "string" ? v : "");

export function parseAdminEventsQuery(query: LocationQueryRaw): AdminEventsState {
  const page = Number.parseInt(str(query.page), 10);
  const type = str(query.type);
  const status = str(query.status);
  return {
    q: str(query.q),
    type: (EVENT_TYPES as readonly string[]).includes(type) ? (type as EventType) : "",
    status: (EVENT_STATUSES as string[]).includes(status) ? (status as EventStatus) : "",
    page: Number.isFinite(page) && page > 0 ? page : 1,
  };
}

/** Events registry: filter and page state live in the URL; search is debounced. */
export function useAdminEvents() {
  const route = useRoute();
  const router = useRouter();

  const items = ref<AdminEventRow[]>([]);
  const meta = ref<PaginationMeta | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const query = computed(() => parseAdminEventsQuery(route.query));
  const searchInput = ref(query.value.q);
  watch(() => query.value.q, (v) => (searchInput.value = v));

  let controller: AbortController | null = null;
  let timer: ReturnType<typeof setTimeout> | null = null;

  function push(patch: Partial<AdminEventsState>): void {
    const n = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (n.q) target.q = n.q;
    if (n.type) target.type = n.type;
    if (n.status) target.status = n.status;
    if (n.page > 1) target.page = String(n.page);
    void router.push({ query: target });
  }

  function setSearch(value: string): void {
    searchInput.value = value;
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      timer = null;
      if (value !== query.value.q) push({ q: value, page: 1 });
    }, EVENTS_DEBOUNCE_MS);
  }

  async function load(): Promise<void> {
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = true;
    error.value = null;
    try {
      const q = query.value;
      const response = await listAdminEvents(
        { q: q.q || undefined, event_type: q.type || undefined, status: q.status || undefined, page: q.page },
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
    setType: (v: EventType | "") => push({ type: v, page: 1 }),
    setStatus: (v: EventStatus | "") => push({ status: v, page: 1 }),
    setPage: (page: number) => push({ page }),
    clear: () => void router.push({ query: {} }),
  };
}
