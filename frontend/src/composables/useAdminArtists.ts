import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { listAdminArtists } from "@/api/artistCuration";
import type { PaginationMeta } from "@/types/api";
import type { AdminArtistRow, OwnerType } from "@/types/artistCuration";

export const REGISTRY_DEBOUNCE_MS = 300;

export interface RegistryQuery {
  q: string;
  unverified: boolean;
  city: string;
  ownerType: OwnerType | "";
  themeId: number | null;
  priority: boolean;
  page: number;
}

function str(v: unknown): string {
  return typeof v === "string" ? v : "";
}

export function parseRegistryQuery(query: LocationQueryRaw): RegistryQuery {
  const page = Number.parseInt(str(query.page), 10);
  const theme = Number.parseInt(str(query.theme_id), 10);
  return {
    q: str(query.q),
    unverified: str(query.unverified) === "1",
    city: str(query.city),
    ownerType: str(query.owner_type) as OwnerType | "",
    themeId: Number.isFinite(theme) && theme > 0 ? theme : null,
    priority: str(query.priority) === "1",
    page: Number.isFinite(page) && page > 0 ? page : 1,
  };
}

/** Artists registry: all filter/page state lives in the URL; text inputs are debounced. */
export function useAdminArtists() {
  const route = useRoute();
  const router = useRouter();

  const items = ref<AdminArtistRow[]>([]);
  const meta = ref<PaginationMeta | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const query = computed(() => parseRegistryQuery(route.query));
  const searchInput = ref(query.value.q);
  const cityInput = ref(query.value.city);
  watch(() => query.value.q, (v) => (searchInput.value = v));
  watch(() => query.value.city, (v) => (cityInput.value = v));

  let controller: AbortController | null = null;
  const timers: Record<string, ReturnType<typeof setTimeout> | null> = {};

  function push(patch: Partial<RegistryQuery>): void {
    const next = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (next.q) target.q = next.q;
    if (next.unverified) target.unverified = "1";
    if (next.city) target.city = next.city;
    if (next.ownerType) target.owner_type = next.ownerType;
    if (next.themeId) target.theme_id = String(next.themeId);
    if (next.priority) target.priority = "1";
    if (next.page > 1) target.page = String(next.page);
    void router.push({ query: target });
  }

  function debounced(key: "q" | "city", value: string): void {
    if (key === "q") searchInput.value = value;
    else cityInput.value = value;
    if (timers[key]) clearTimeout(timers[key]!);
    timers[key] = setTimeout(() => {
      timers[key] = null;
      if (value !== query.value[key]) push({ [key]: value, page: 1 });
    }, REGISTRY_DEBOUNCE_MS);
  }

  async function load(): Promise<void> {
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = true;
    error.value = null;
    try {
      const q = query.value;
      const response = await listAdminArtists(
        {
          q: q.q || undefined,
          unverified: q.unverified ? 1 : undefined,
          city: q.city || undefined,
          owner_type: q.ownerType || undefined,
          theme_id: q.themeId ?? undefined,
          has_priority_materials: q.priority ? 1 : undefined,
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
    Object.values(timers).forEach((t) => t && clearTimeout(t));
  });

  return {
    items,
    meta,
    loading,
    error,
    query,
    searchInput,
    cityInput,
    retry: load,
    setSearch: (v: string) => debounced("q", v),
    setCity: (v: string) => debounced("city", v),
    setUnverified: (v: boolean) => push({ unverified: v, page: 1 }),
    setOwnerType: (v: OwnerType | "") => push({ ownerType: v, page: 1 }),
    setTheme: (v: number | null) => push({ themeId: v, page: 1 }),
    setPriority: (v: boolean) => push({ priority: v, page: 1 }),
    setPage: (page: number) => push({ page }),
    clear: () => void router.push({ query: {} }),
  };
}
