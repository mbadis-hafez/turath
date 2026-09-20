<script setup lang="ts">
import { ref } from "vue";
import { useI18n } from "vue-i18n";

import { dashboardExportUrl } from "@/api/dashboard";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import CompletionSidebar from "@/components/dashboard/CompletionSidebar.vue";
import ConflictResolutionModal from "@/components/dashboard/ConflictResolutionModal.vue";
import RecordGroup from "@/components/dashboard/RecordGroup.vue";
import StatTile from "@/components/dashboard/StatTile.vue";
import { useDashboard } from "@/composables/useDashboard";
import { useAuthStore } from "@/stores/auth";
import type {
  CompletenessSeverity,
  DashboardEntityType,
  DashboardRecord,
} from "@/types/completeness";

const { t } = useI18n();
const auth = useAuthStore();

const { stats, records, meta, loading, error, query, retry, setEntityType, setSeverity, setPage } =
  useDashboard();

const ENTITY_TYPES: DashboardEntityType[] = ["artist", "artwork", "archive_item"];
const SEVERITIES: CompletenessSeverity[] = ["blocking", "conflict", "minor", "pending_review", "clear"];

const resolving = ref<DashboardRecord | null>(null);

function exportHref(): string {
  return dashboardExportUrl({
    entity_type: query.value.entityType || undefined,
    severity: query.value.severity || undefined,
  });
}

function onResolved(): void {
  resolving.value = null;
  void retry();
}
</script>

<template>
  <section>
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">
          {{ t("dashboard.title") }}
        </h1>
        <p class="mt-1 text-sm text-ink-muted">
          <template v-if="auth.user">{{ auth.user.name }} · </template>{{ t("dashboard.subtitle") }}
        </p>
      </div>
      <a
        :href="exportHref()"
        download
        class="rounded-md border border-line px-3 py-2 text-sm font-medium text-ink hover:bg-neutral-soft"
      >
        {{ t("dashboard.exportGaps") }}
      </a>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
      <StatTile :label="t('dashboard.tiles.totalRecords')" :value="stats?.total_records ?? '—'" />
      <StatTile :label="t('dashboard.tiles.avgCompleteness')" :value="stats ? `${stats.avg_completeness_pct}%` : '—'" />
      <StatTile :label="t('dashboard.tiles.blockingRecords')" :value="stats?.blocking_record_count ?? '—'" />
      <StatTile :label="t('dashboard.tiles.conflicts')" :value="stats?.conflict_count ?? '—'" />
      <StatTile :label="t('dashboard.tiles.missingFields')" :value="stats?.missing_field_count ?? '—'" />
    </div>

    <div class="mt-8 grid gap-8 lg:grid-cols-[18rem_1fr]">
      <CompletionSidebar :stats="stats" />

      <div id="dashboard-results">
        <div class="mb-4 flex flex-wrap gap-2">
          <select
            :value="query.entityType"
            class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
            :aria-label="t('dashboard.filters.allTypes')"
            @change="setEntityType(($event.target as HTMLSelectElement).value as DashboardEntityType | '')"
          >
            <option value="">{{ t("dashboard.filters.allTypes") }}</option>
            <option v-for="type in ENTITY_TYPES" :key="type" :value="type">{{ t(`dashboard.entity.${type}`) }}</option>
          </select>
          <select
            :value="query.severity"
            class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
            :aria-label="t('dashboard.filters.allSeverities')"
            @change="setSeverity(($event.target as HTMLSelectElement).value as CompletenessSeverity | '')"
          >
            <option value="">{{ t("dashboard.filters.allSeverities") }}</option>
            <option v-for="s in SEVERITIES" :key="s" :value="s">{{ t(`dashboard.severity.${s}`) }}</option>
          </select>
        </div>

        <ErrorState v-if="error" :error="error" @retry="retry" />
        <Spinner v-else-if="loading && records.length === 0" class="mx-auto my-12 block" />
        <EmptyState
          v-else-if="records.length === 0"
          :title="t('dashboard.empty.title')"
          :description="t('dashboard.empty.description')"
        />
        <template v-else>
          <div class="space-y-8">
            <RecordGroup
              v-for="type in ENTITY_TYPES"
              :key="type"
              :entity-type="type"
              :records="records"
              @resolve="resolving = $event"
            />
          </div>
          <Pagination class="mt-8" :meta="meta" @change="setPage" />
        </template>
      </div>
    </div>

    <ConflictResolutionModal
      v-if="resolving"
      :record="resolving"
      @close="resolving = null"
      @resolved="onResolved"
    />
  </section>
</template>
