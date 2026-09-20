import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { listAdminArchive } from "@/api/archive";
import type { PaginationMeta } from "@/types/api";
import {
  ARCHIVE_ITEM_TYPES, type AdminArchiveRow, type ArchiveItemType, type ArchiveRowStatus, type RightsStatus,
} from "@/types/archive";

export const ADMIN_ARCHIVE_DEBOUNCE_MS = 300;
export const ROW_STATUSES: ArchiveRowStatus[] = ["draft", "published", "hidden", "incomplete", "under_review"];
export const RIGHTS: RightsStatus[] = ["public_domain", "licensed", "all_rights_reserved", "unknown"];

export interface AdminArchiveState {
  q: string;
  type: ArchiveItemType | "";
  status: ArchiveRowStatus | "";
  rights: RightsStatus | "";
  yearFrom: number | null;
  yearTo: number | null;
  mine: boolean;
  page: number;
}

const str = (v: unknown): string => (typeof v === "string" ? v : "");
const posInt = (v: unknown): number | null => {
  const n = Number.parseInt(str(v), 10);
  return Number.isFinite(n) && n > 0 ? n : null;
};
const oneOf = <T extends string>(list: readonly T[], v: unknown): T | "" => (list.includes(str(v) as T) ? (str(v) as T) : "");

export function parseAdminArchiveQuery(query: LocationQueryRaw): AdminArchiveState {
  return {
    q: str(query.q),
    type: oneOf(ARCHIVE_ITEM_TYPES, query.type),
    status: oneOf(ROW_STATUSES, query.status),
    rights: oneOf(RIGHTS, query.rights),
    yearFrom: posInt(query.year_from),
    yearTo: posInt(query.year_to),
    mine: str(query.mine) === "1",
    page: posInt(query.page) ?? 1,
  };
}

/** Archive registry: all filter/page state lives in the URL; search is debounced. */
export function useAdminArchive() {
  const route = useRoute();
  const router = useRouter();

  const items = ref<AdminArchiveRow[]>([]);
  const meta = ref<(PaginationMeta & { total_all: number; mine_count: number; incomplete_count: number }) | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const query = computed(() => parseAdminArchiveQuery(route.query));
  const searchInput = ref(query.value.q);
  watch(() => query.value.q, (v) => (searchInput.value = v));

  let controller: AbortController | null = null;
  let timer: ReturnType<typeof setTimeout> | null = null;

  function push(patch: Partial<AdminArchiveState>): void {
    const n = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (n.q) target.q = n.q;
    if (n.type) target.type = n.type;
    if (n.status) target.status = n.status;
    if (n.rights) target.rights = n.rights;
    if (n.yearFrom) target.year_from = String(n.yearFrom);
    if (n.yearTo) target.year_to = String(n.yearTo);
    if (n.mine) target.mine = "1";
    if (n.page > 1) target.page = String(n.page);
    void router.push({ query: target });
  }

  function setSearch(value: string): void {
    searchInput.value = value;
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      timer = null;
      if (value !== query.value.q) push({ q: value, page: 1 });
    }, ADMIN_ARCHIVE_DEBOUNCE_MS);
  }

  async function load(): Promise<void> {
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = true;
    error.value = null;
    try {
      const q = query.value;
      const response = await listAdminArchive(
        {
          q: q.q || undefined, item_type: q.type || undefined, status: q.status || undefined, rights_status: q.rights || undefined,
          year_from: q.yearFrom ?? undefined, year_to: q.yearTo ?? undefined, mine: q.mine ? 1 : undefined, page: q.page,
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
    setType: (v: ArchiveItemType | "") => push({ type: v, page: 1 }),
    setStatus: (v: ArchiveRowStatus | "") => push({ status: v, page: 1 }),
    setRights: (v: RightsStatus | "") => push({ rights: v, page: 1 }),
    setYears: (from: number | null, to: number | null) => push({ yearFrom: from, yearTo: to, page: 1 }),
    setMine: (v: boolean) => push({ mine: v, page: 1 }),
    setPage: (page: number) => push({ page }),
    clear: () => void router.push({ query: {} }),
  };
}
