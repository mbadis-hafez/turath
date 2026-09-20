<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import ImportBatchStatusBadge from "@/components/imports/ImportBatchStatusBadge.vue";
import ImportUploadForm from "@/components/imports/ImportUploadForm.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Pagination from "@/components/common/Pagination.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useImportBatchesList } from "@/composables/useImportBatchesList";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type { ImportBatch, ImportBatchStatus, ImportEntityType } from "@/types/import";
import { formatDateTime } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const { t, locale } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbiddenError = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("imports.manage"));

const { items, meta, loading, error, retry, query, setEntityType, setStatus, setPage } =
  useImportBatchesList();

const ENTITY_TYPES: ImportEntityType[] = [
  "artist",
  "artwork",
  "holder",
  "archive_item",
];
const STATUSES: ImportBatchStatus[] = [
  "uploaded",
  "validated",
  "committed",
  "failed",
  "cancelled",
];

function onCreated(batch: ImportBatch): void {
  void retry();
  void batch;
}

function formatDate(value: string): string {
  return formatDateTime(value, locale.value as AppLocale);
}
</script>

<template>
  <section>
    <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">
      {{ t("imports.title") }}
    </h1>

    <ErrorState v-if="!canManage" :error="forbiddenError" class="mt-8" />
    <template v-else>
      <ImportUploadForm class="mt-6" @created="onCreated" />

      <div class="mt-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <h2 class="text-lg font-semibold text-ink">
            {{ t("imports.batches.title") }}
          </h2>
          <div class="flex flex-wrap gap-2">
            <select
              :value="query.entityType"
              class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
              @change="
                setEntityType(($event.target as HTMLSelectElement).value as ImportEntityType | '')
              "
            >
              <option value="">{{ t("imports.batches.entityType") }}</option>
              <option v-for="type in ENTITY_TYPES" :key="type" :value="type">
                {{ t(`imports.entityType.${type}`) }}
              </option>
            </select>
            <select
              :value="query.status"
              class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
              @change="
                setStatus(($event.target as HTMLSelectElement).value as ImportBatchStatus | '')
              "
            >
              <option value="">{{ t("imports.batches.status") }}</option>
              <option v-for="status in STATUSES" :key="status" :value="status">
                {{ t(`imports.status.${status}`) }}
              </option>
            </select>
          </div>
        </div>

        <div id="import-batches-results" class="mt-4">
          <ErrorState v-if="error" :error="error" @retry="retry" />
          <template v-else-if="!loading && items.length === 0">
            <EmptyState
              :title="t('imports.batches.empty')"
              :description="t('imports.batches.emptyDescription')"
            />
          </template>
          <template v-else>
            <div class="overflow-x-auto rounded-lg border border-line">
              <table class="w-full text-start text-sm">
                <thead class="border-b border-line bg-neutral-soft text-ink-muted">
                  <tr>
                    <th class="px-3 py-2 text-start font-medium">{{ t("imports.batches.filename") }}</th>
                    <th class="px-3 py-2 text-start font-medium">{{ t("imports.batches.entityType") }}</th>
                    <th class="px-3 py-2 text-start font-medium">{{ t("imports.batches.status") }}</th>
                    <th class="px-3 py-2 text-start font-medium tabular-nums">{{ t("imports.batches.rows") }}</th>
                    <th class="px-3 py-2 text-start font-medium">{{ t("imports.batches.uploadedBy") }}</th>
                    <th class="px-3 py-2 text-start font-medium">{{ t("imports.batches.createdAt") }}</th>
                    <th class="px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="batch in items"
                    :key="batch.id"
                    class="border-b border-line last:border-b-0"
                  >
                    <td class="max-w-56 truncate px-3 py-2 text-ink">{{ batch.original_filename }}</td>
                    <td class="px-3 py-2 text-ink-muted">{{ t(`imports.entityType.${batch.entity_type}`) }}</td>
                    <td class="px-3 py-2"><ImportBatchStatusBadge :status="batch.status" /></td>
                    <td class="px-3 py-2 tabular-nums text-ink-muted">{{ batch.row_count }}</td>
                    <td class="px-3 py-2 text-ink-muted">{{ batch.uploaded_by?.name ?? t("imports.batches.system") }}</td>
                    <td class="px-3 py-2 text-ink-muted">{{ formatDate(batch.created_at) }}</td>
                    <td class="px-3 py-2 text-end">
                      <RouterLink
                        :to="localePath('admin.imports.show', { id: batch.id })"
                        class="text-sm font-medium text-accent hover:underline"
                      >
                        {{ t("imports.batches.view") }}
                      </RouterLink>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <Pagination class="mt-6" :meta="meta" @change="setPage" />
          </template>
        </div>
      </div>
    </template>
  </section>
</template>
