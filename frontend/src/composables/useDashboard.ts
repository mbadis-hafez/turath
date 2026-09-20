import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { getDashboardStats, listDashboardRecords } from "@/api/dashboard";
import type { PaginationMeta } from "@/types/api";
import type {
  CompletenessSeverity,
  DashboardEntityType,
  DashboardRecord,
  DashboardStats,
} from "@/types/completeness";

export interface DashboardQuery {
  entityType: DashboardEntityType | "";
  severity: CompletenessSeverity | "";
  page: number;
}

function str(value: unknown): string {
  return typeof value === "string" ? value : "";
}

export function parseDashboardQuery(query: LocationQueryRaw): DashboardQuery {
  const page = Number.parseInt(str(query.page), 10);
  return {
    entityType: str(query.entity_type) as DashboardEntityType | "",
    severity: str(query.severity) as CompletenessSeverity | "",
    page: Number.isFinite(page) && page > 0 ? page : 1,
  };
}

/** Stat tiles + grouped record list; filters/page live in the URL. */
export function useDashboard() {
  const route = useRoute();
  const router = useRouter();

  const stats = ref<DashboardStats | null>(null);
  const records = ref<DashboardRecord[]>([]);
  const meta = ref<PaginationMeta | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const query = computed(() => parseDashboardQuery(route.query));
  let controller: AbortController | null = null;

  function push(patch: Partial<DashboardQuery>): void {
    const next = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (next.entityType) target.entity_type = next.entityType;
    if (next.severity) target.severity = next.severity;
    if (next.page > 1) target.page = String(next.page);
    void router.push({ query: target });
  }

  async function load(): Promise<void> {
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = true;
    error.value = null;
    try {
      const [s, r] = await Promise.all([
        getDashboardStats(self.signal),
        listDashboardRecords(
          {
            entity_type: query.value.entityType || undefined,
            severity: query.value.severity || undefined,
            page: query.value.page,
          },
          self.signal,
        ),
      ]);
      if (controller !== self) return;
      stats.value = s.data;
      records.value = r.data;
      meta.value = r.meta;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      if (controller !== self) return;
      error.value = err;
    } finally {
      if (controller === self) loading.value = false;
    }
  }

  watch(() => route.query, () => void load(), { immediate: true, deep: true });
  onBeforeUnmount(() => controller?.abort());

  return {
    stats,
    records,
    meta,
    loading,
    error,
    query,
    retry: load,
    setEntityType: (v: DashboardEntityType | "") => push({ entityType: v, page: 1 }),
    setSeverity: (v: CompletenessSeverity | "") => push({ severity: v, page: 1 }),
    setPage: (page: number) => push({ page }),
  };
}
