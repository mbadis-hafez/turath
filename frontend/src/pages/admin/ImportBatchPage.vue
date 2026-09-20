<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { cancelImportBatch, commitImportBatch, revalidateImportBatch } from "@/api/imports";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import ImportBatchStatusBadge from "@/components/imports/ImportBatchStatusBadge.vue";
import ImportMatchStatusBadge from "@/components/imports/ImportMatchStatusBadge.vue";
import { useImportBatch } from "@/composables/useImportBatch";
import { useImportBatchRows } from "@/composables/useImportBatchRows";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type { ImportRowMatchStatus, ImportRowResolution } from "@/types/import";
import { formatDateTime, formatFieldValue } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbiddenError = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("imports.manage"));

const batchId = computed(() => String(route.params.id));

const { batch, loading: batchLoading, error: batchError, retry: reloadBatch } =
  useImportBatch(batchId);

const {
  rows,
  meta,
  loading: rowsLoading,
  error: rowsError,
  retry: reloadRows,
  query,
  setMatchStatus,
  setPage,
  setRowResolution,
} = useImportBatchRows(batchId);

const MATCH_STATUSES: ImportRowMatchStatus[] = [
  "new",
  "matched_exact",
  "matched_suggested",
  "ambiguous",
  "error",
];
const RESOLUTIONS: ImportRowResolution[] = [
  "pending",
  "create_new",
  "link_existing",
  "skip",
];

async function reloadAll(): Promise<void> {
  await Promise.all([reloadBatch(), reloadRows()]);
}

const pendingRowIds = ref(new Set<string>());

async function onResolutionChange(rowId: string, resolution: ImportRowResolution): Promise<void> {
  pendingRowIds.value.add(rowId);
  try {
    const row = rows.value.find((r) => r.id === rowId);
    await setRowResolution(rowId, resolution, row?.matched_entity_id ?? undefined);
  } finally {
    pendingRowIds.value.delete(rowId);
  }
}

async function onMatchedEntityIdChange(rowId: string, value: string): Promise<void> {
  const id = value.trim() === "" ? null : Number.parseInt(value, 10);
  if (value.trim() !== "" && !Number.isFinite(id)) return;
  pendingRowIds.value.add(rowId);
  try {
    const row = rows.value.find((r) => r.id === rowId);
    await setRowResolution(rowId, row?.resolution ?? "link_existing", id);
  } finally {
    pendingRowIds.value.delete(rowId);
  }
}

const revalidating = ref(false);
async function revalidate(): Promise<void> {
  revalidating.value = true;
  try {
    await revalidateImportBatch(batchId.value);
    await reloadAll();
  } finally {
    revalidating.value = false;
  }
}

const reviewedConfirmed = ref(false);
const committing = ref(false);
const commitError = ref<unknown>(null);

async function commit(): Promise<void> {
  commitError.value = null;
  committing.value = true;
  try {
    await commitImportBatch(batchId.value);
    await reloadAll();
  } catch (err) {
    commitError.value = err;
  } finally {
    committing.value = false;
  }
}

const cancelling = ref(false);
async function cancel(): Promise<void> {
  if (!window.confirm(t("imports.review.cancelConfirm"))) return;
  cancelling.value = true;
  try {
    await cancelImportBatch(batchId.value);
    await reloadBatch();
  } finally {
    cancelling.value = false;
  }
}

const canAct = computed(
  () => batch.value !== null && !["committed", "cancelled"].includes(batch.value.status),
);

function mappedDataEntries(data: Record<string, unknown>): Array<[string, string]> {
  return Object.entries(data)
    .map(([key, value]) => [key, formatFieldValue(value) ?? "—"] as [string, string])
    .filter(([, value]) => value !== "—");
}

function goBack(): void {
  void router.push(localePath("admin.imports"));
}
</script>

<template>
  <section>
    <button
      type="button"
      class="text-sm text-ink-muted hover:text-ink"
      @click="goBack"
    >
      &larr; {{ t("imports.review.backToImports") }}
    </button>

    <ErrorState v-if="!canManage" :error="forbiddenError" class="mt-4" />
    <ErrorState v-else-if="batchError" :error="batchError" class="mt-4" @retry="reloadBatch" />
    <Spinner v-else-if="batchLoading && !batch" class="mx-auto my-12 block" />

    <template v-else-if="batch">
      <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="text-balance text-2xl font-semibold tracking-tight text-ink">
            {{ batch.original_filename }}
          </h1>
          <p class="mt-1 text-sm text-ink-muted">
            {{ t(`imports.entityType.${batch.entity_type}`) }} ·
            {{
              t("imports.review.summary", {
                rowCount: batch.row_count,
                newCount: batch.new_count,
                matchedCount: batch.matched_count,
                errorCount: batch.error_count,
              })
            }}
          </p>
        </div>
        <ImportBatchStatusBadge :status="batch.status" />
      </div>

      <div v-if="canAct" class="mt-4 flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink hover:bg-neutral-soft disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="revalidating"
          @click="revalidate"
        >
          {{ revalidating ? t("common.loading") : t("imports.review.revalidate") }}
        </button>
        <button
          type="button"
          class="rounded-md border border-line px-3 py-1.5 text-sm font-medium text-danger hover:bg-danger-soft disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="cancelling"
          @click="cancel"
        >
          {{ cancelling ? t("imports.review.cancelling") : t("imports.review.cancel") }}
        </button>
      </div>

      <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
        <h2 class="text-lg font-semibold text-ink">{{ t("imports.review.title") }}</h2>
        <select
          :value="query.matchStatus"
          class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
          @change="setMatchStatus(($event.target as HTMLSelectElement).value as ImportRowMatchStatus | '')"
        >
          <option value="">{{ t("imports.review.allStatuses") }}</option>
          <option v-for="status in MATCH_STATUSES" :key="status" :value="status">
            {{ t(`imports.matchStatus.${status}`) }}
          </option>
        </select>
      </div>

      <div id="import-rows-results" class="mt-4">
        <ErrorState v-if="rowsError" :error="rowsError" @retry="reloadRows" />
        <Spinner v-else-if="rowsLoading && rows.length === 0" class="mx-auto my-12 block" />
        <EmptyState v-else-if="rows.length === 0" :title="t('imports.review.noRows')" />
        <template v-else>
          <ul class="space-y-3">
            <li
              v-for="row in rows"
              :key="row.id"
              class="rounded-lg border border-line bg-surface p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                  <span class="tabular-nums text-sm font-medium text-ink-muted">
                    {{ t("imports.review.rowNumber") }} {{ row.row_number }}
                  </span>
                  <ImportMatchStatusBadge :status="row.match_status" />
                  <span v-if="row.match_confidence" class="text-xs text-ink-muted">
                    ({{ t(`imports.confidence.${row.match_confidence}`) }})
                  </span>
                </div>

                <div v-if="canAct" class="flex flex-wrap items-center gap-2">
                  <select
                    :value="row.resolution"
                    class="rounded-md border border-line bg-surface px-2 py-1 text-sm text-ink focus:border-accent focus:outline-none disabled:opacity-50"
                    :disabled="pendingRowIds.has(row.id)"
                    @change="
                      onResolutionChange(row.id, ($event.target as HTMLSelectElement).value as ImportRowResolution)
                    "
                  >
                    <option v-for="resolution in RESOLUTIONS" :key="resolution" :value="resolution">
                      {{ t(`imports.resolution.${resolution}`) }}
                    </option>
                  </select>
                  <input
                    v-if="row.resolution === 'link_existing'"
                    type="number"
                    :value="row.matched_entity_id ?? ''"
                    :placeholder="t('imports.review.matchedEntityId')"
                    class="w-32 rounded-md border border-line bg-surface px-2 py-1 text-sm text-ink focus:border-accent focus:outline-none"
                    :disabled="pendingRowIds.has(row.id)"
                    @change="onMatchedEntityIdChange(row.id, ($event.target as HTMLInputElement).value)"
                  />
                </div>
                <div v-else-if="row.commit_result" class="text-sm text-ink-muted">
                  {{ t("imports.review.result") }}:
                  <span class="font-medium text-ink">{{ t(`imports.commitResult.${row.commit_result}`) }}</span>
                  <span v-if="row.resulting_entity_id"> (#{{ row.resulting_entity_id }})</span>
                </div>
              </div>

              <dl
                v-if="mappedDataEntries(row.mapped_data).length > 0"
                class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1 text-sm sm:grid-cols-2 lg:grid-cols-3"
              >
                <div v-for="[key, value] in mappedDataEntries(row.mapped_data)" :key="key" class="truncate">
                  <dt class="inline text-ink-muted">{{ key }}:</dt>
                  <dd class="inline ms-1 text-ink">{{ value }}</dd>
                </div>
              </dl>

              <ul v-if="row.validation_errors.length > 0" class="mt-3 space-y-1">
                <li
                  v-for="(err, i) in row.validation_errors"
                  :key="i"
                  class="text-sm text-danger"
                >
                  {{ err.field }}: {{ err.message }}
                </li>
              </ul>
            </li>
          </ul>
          <Pagination class="mt-6" :meta="meta" @change="setPage" />
        </template>
      </div>

      <div v-if="canAct" class="mt-8 space-y-3 rounded-lg border border-line bg-surface p-4">
        <label class="flex items-center gap-2 text-sm text-ink">
          <input v-model="reviewedConfirmed" type="checkbox" class="size-4" />
          {{ t("imports.review.confirmReviewed") }}
        </label>
        <p class="text-xs text-ink-muted">{{ t("imports.review.commitDisabledHint") }}</p>
        <p v-if="commitError" class="text-sm text-danger">
          {{
            commitError instanceof ApiError && commitError.kind === "validation"
              ? t("imports.review.commitError")
              : commitError instanceof Error
                ? commitError.message
                : t("errors.generic")
          }}
        </p>
        <button
          type="button"
          class="rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!reviewedConfirmed || committing"
          @click="commit"
        >
          {{ committing ? t("imports.review.committing") : t("imports.review.commit") }}
        </button>
      </div>

      <p v-if="batch.status === 'committed' && batch.committed_at" class="mt-6 text-sm text-ink-muted">
        {{ t("imports.review.committedAt") }}: {{ formatDateTime(batch.committed_at, locale as AppLocale) }}
      </p>
    </template>
  </section>
</template>
