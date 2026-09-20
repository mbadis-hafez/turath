import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { listImportBatches } from "@/api/imports";
import type { PaginationMeta } from "@/types/api";
import type {
  ImportBatch,
  ImportBatchStatus,
  ImportEntityType,
} from "@/types/import";

export interface ImportBatchesListQuery {
  entityType: ImportEntityType | "";
  status: ImportBatchStatus | "";
  page: number;
}

function stringParam(value: unknown): string {
  return typeof value === "string" ? value : "";
}

export function parseImportBatchesQuery(
  query: LocationQueryRaw,
): ImportBatchesListQuery {
  const page = Number.parseInt(stringParam(query.page), 10);
  return {
    entityType: stringParam(query.entity_type) as ImportEntityType | "",
    status: stringParam(query.status) as ImportBatchStatus | "",
    page: Number.isFinite(page) && page > 0 ? page : 1,
  };
}

/** Owns the "past imports" list: filter/page state lives in the URL query. */
export function useImportBatchesList() {
  const route = useRoute();
  const router = useRouter();

  const items = ref<ImportBatch[]>([]);
  const meta = ref<PaginationMeta | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const query = computed(() => parseImportBatchesQuery(route.query));

  let abortController: AbortController | null = null;

  function pushQuery(patch: Partial<ImportBatchesListQuery>): void {
    const next = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (next.entityType !== "") target.entity_type = next.entityType;
    if (next.status !== "") target.status = next.status;
    if (next.page > 1) target.page = String(next.page);
    void router.push({ query: target });
  }

  function setEntityType(entityType: ImportEntityType | ""): void {
    pushQuery({ entityType, page: 1 });
  }

  function setStatus(status: ImportBatchStatus | ""): void {
    pushQuery({ status, page: 1 });
  }

  function setPage(page: number): void {
    pushQuery({ page });
  }

  async function load(): Promise<void> {
    abortController?.abort();
    const self = new AbortController();
    abortController = self;
    loading.value = true;
    error.value = null;
    try {
      const response = await listImportBatches(
        {
          entity_type: query.value.entityType || undefined,
          status: query.value.status || undefined,
          page: query.value.page,
        },
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
  });

  return {
    items,
    meta,
    loading,
    error,
    retry: load,
    query,
    setEntityType,
    setStatus,
    setPage,
  };
}
