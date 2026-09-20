<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import { EVENT_STATUSES, useAdminEvents } from "@/composables/useAdminEvents";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import { EVENT_TYPES, type EventStatus, type EventType } from "@/types/event";

const { t } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("events.manage"));
const { items, meta, loading, error, query, searchInput, retry, setSearch, setType, setStatus, setPage, clear } = useAdminEvents();

const field = "rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
const hasFilters = computed(() => Object.values(query.value).some((v) => v && v !== 1));
const STATUS_CLASS: Record<EventStatus, string> = {
  draft: "bg-warn-soft text-warn", published: "bg-success-soft text-success", hidden: "bg-neutral-soft text-ink-muted",
};
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("events.title") }}</h1>
          <p v-if="meta" class="mt-1 text-sm tabular-nums text-ink-muted">{{ t("events.subtitle", { count: meta.total }) }}</p>
        </div>
        <RouterLink :to="localePath('admin.events.new')" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper hover:bg-ink/85" data-testid="add-event">{{ t("events.add") }}</RouterLink>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-2">
        <input :value="searchInput" type="search" :placeholder="t('events.searchPlaceholder')" :class="[field, 'min-w-64 flex-1']" @input="setSearch(($event.target as HTMLInputElement).value)" />
        <select :value="query.type" :class="field" :aria-label="t('events.type')" @change="setType(($event.target as HTMLSelectElement).value as EventType | '')">
          <option value="">{{ t("events.allTypes") }}</option>
          <option v-for="ty in EVENT_TYPES" :key="ty" :value="ty">{{ t(`events.types.${ty}`) }}</option>
        </select>
        <select :value="query.status" :class="field" :aria-label="t('events.status')" @change="setStatus(($event.target as HTMLSelectElement).value as EventStatus | '')">
          <option value="">{{ t("events.allStatuses") }}</option>
          <option v-for="s in EVENT_STATUSES" :key="s" :value="s">{{ t(`events.statuses.${s}`) }}</option>
        </select>
      </div>

      <div class="mt-6">
        <ErrorState v-if="error" :error="error" @retry="retry" />
        <Spinner v-else-if="loading && items.length === 0" class="mx-auto my-12 block" />
        <EmptyState v-else-if="items.length === 0" :title="t('events.empty')" :description="t('events.emptyHelp')">
          <button v-if="hasFilters" type="button" class="mt-4 rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong" @click="clear">{{ t("archive.clear") }}</button>
        </EmptyState>
        <template v-else>
          <ul class="divide-y divide-line border-y border-line" data-testid="events-list">
            <li v-for="e in items" :key="e.id" class="flex flex-wrap items-start justify-between gap-4 py-4" data-testid="event-row">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink-muted">{{ t(`events.types.${e.event_type}`) }}</span>
                  <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="STATUS_CLASS[e.publication_status]">{{ t(`events.statuses.${e.publication_status}`) }}</span>
                  <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium tabular-nums" :class="e.gap_count > 0 ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success'">{{ e.gap_count > 0 ? t("events.gaps", { count: e.gap_count }) : t("events.noGaps") }}</span>
                </div>
                <h2 class="mt-1 text-lg font-semibold text-ink"><RouterLink :to="localePath('admin.events.edit', { id: e.id })" class="hover:underline"><LocalizedText :text="e.title" /></RouterLink></h2>
                <p class="text-xs text-ink-muted">{{ [e.venue_name, e.city].filter(Boolean).join("، ") }}</p>
              </div>
              <div class="text-end text-xs tabular-nums text-ink-muted">
                <p v-if="e.start">{{ e.start.display ?? e.start.year_from }}</p>
                <p>{{ t("events.participants", { count: e.participant_count }) }}</p>
              </div>
            </li>
          </ul>
          <Pagination class="mt-6" :meta="meta" @change="setPage" />
        </template>
      </div>
    </template>
  </section>
</template>
