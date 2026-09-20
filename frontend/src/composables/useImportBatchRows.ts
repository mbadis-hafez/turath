import { computed, onBeforeUnmount, ref, watch, type Ref } from "vue";
import { useRoute, useRouter, type LocationQueryRaw } from "vue-router";

import { listImportBatchRows, updateImportBatchRow } from "@/api/imports";
import type { PaginationMeta } from "@/types/api";
import type {
  ImportBatchRow,
  ImportRowMatchStatus,
  ImportRowResolution,
} from "@/types/import";

export interface ImportBatchRowsQuery {
  matchStatus: ImportRowMatchStatus | "";
  page: number;
}

function stringParam(value: unknown): string {
  return typeof value === "string" ? value : "";
}

export function parseImportBatchRowsQuery(
  query: LocationQueryRaw,
): ImportBatchRowsQuery {
  const page = Number.parseInt(stringParam(query.page), 10);
  return {
    matchStatus: stringParam(query.match_status) as ImportRowMatchStatus | "",
    page: Number.isFinite(page) && page > 0 ? page : 1,
  };
}

/** Owns the row review table for one import batch: filter/page state lives
 * in the URL query, and row resolution edits are written through the API
 * and patched into the local list without a full reload. */
export function useImportBatchRows(batchId: Ref<string>) {
  const route = useRoute();
  const router = useRouter();

  const rows = ref<ImportBatchRow[]>([]);
  const meta = ref<PaginationMeta | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);

  const query = computed(() => parseImportBatchRowsQuery(route.query));

  let abortController: AbortController | null = null;

  function pushQuery(patch: Partial<ImportBatchRowsQuery>): void {
    const next = { ...query.value, ...patch };
    const target: LocationQueryRaw = {};
    if (next.matchStatus !== "") target.match_status = next.matchStatus;
    if (next.page > 1) target.page = String(next.page);
    void router.push({ query: target });
  }

  function setMatchStatus(matchStatus: ImportRowMatchStatus | ""): void {
    pushQuery({ matchStatus, page: 1 });
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
      const response = await listImportBatchRows(
        batchId.value,
        {
          match_status: query.value.matchStatus || undefined,
          page: query.value.page,
          per_page: 50,
        },
        self.signal,
      );
      if (abortController !== self) return;
      rows.value = response.data;
      meta.value = response.meta;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      if (abortController !== self) return;
      rows.value = [];
      meta.value = null;
      error.value = err;
    } finally {
      if (abortController === self) loading.value = false;
    }
  }

  async function setRowResolution(
    rowId: string,
    resolution: ImportRowResolution,
    matchedEntityId?: number | null,
  ): Promise<void> {
    const updated = await updateImportBatchRow(batchId.value, rowId, {
      resolution,
      matched_entity_id: matchedEntityId,
    });
    const index = rows.value.findIndex((r) => r.id === rowId);
    if (index !== -1) rows.value[index] = updated.data;
  }

  watch(
    () => [batchId.value, route.query],
    () => {
      void load();
    },
    { immediate: true, deep: true },
  );

  onBeforeUnmount(() => {
    abortController?.abort();
  });

  return {
    rows,
    meta,
    loading,
    error,
    retry: load,
    query,
    setMatchStatus,
    setPage,
    setRowResolution,
  };
}
