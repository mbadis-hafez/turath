import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { fetchAdminUsers } from "@/api/users";
import type { PaginationMeta } from "@/types/api";
import type { AdminUserRow } from "@/types/user";

export const USERS_DEBOUNCE_MS = 300;

export interface UsersQuery {
  search: string;
  role: string;
  page: number;
}

function str(v: unknown): string {
  return typeof v === "string" ? v : "";
}

export function parseUsersQuery(query: LocationQueryRaw): UsersQuery {
  const page = Number.parseInt(str(query.page), 10);
  return {
    search: str(query.search),
    role: str(query.role),
    page: Number.isFinite(page) && page > 0 ? page : 1,
  };
}

/** Admin user directory: filter/page state lives in the URL; the text input is debounced. */
export function useAdminUsers() {
  const route = useRoute();
  const router = useRouter();

  const items = ref<AdminUserRow[]>([]);
  const meta = ref<PaginationMeta | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const query = computed(() => parseUsersQuery(route.query));
  const searchInput = ref(query.value.search);
  watch(() => query.value.search, (v) => (searchInput.value = v));

  let controller: AbortController | null = null;
  let timer: ReturnType<typeof setTimeout> | null = null;

  function push(patch: Partial<UsersQuery>): void {
    const next = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (next.search) target.search = next.search;
    if (next.role) target.role = next.role;
    if (next.page > 1) target.page = String(next.page);
    void router.push({ query: target });
  }

  function setSearch(value: string): void {
    searchInput.value = value;
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      timer = null;
      if (value !== query.value.search) push({ search: value, page: 1 });
    }, USERS_DEBOUNCE_MS);
  }

  async function load(): Promise<void> {
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = true;
    error.value = null;
    try {
      const q = query.value;
      const response = await fetchAdminUsers(
        {
          search: q.search || undefined,
          role: q.role || undefined,
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
    items,
    meta,
    loading,
    error,
    query,
    searchInput,
    retry: load,
    setSearch,
    setRole: (role: string) => push({ role, page: 1 }),
    setPage: (page: number) => push({ page }),
    clear: () => void router.push({ query: {} }),
  };
}
