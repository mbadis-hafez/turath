<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import Spinner from "@/components/common/Spinner.vue";
import type { ActivityEntry, ActivityEvent } from "@/types/activity";
import {
  formatDateTime,
  formatFieldValue,
  formatRelativeTime,
} from "@/utils/format";
import { isLocale, type AppLocale } from "@/i18n";

defineProps<{
  entries: ActivityEntry[];
  loading: boolean;
}>();

const { t, locale } = useI18n();

const appLocale = computed<AppLocale>(() =>
  isLocale(locale.value) ? locale.value : "ar",
);

const EVENT_BADGE_CLASSES: Record<ActivityEvent, string> = {
  created: "bg-accent-soft text-accent-strong",
  updated: "bg-info-soft text-info",
  deleted: "bg-danger-soft text-danger",
  restored: "bg-warn-soft text-warn",
};

function eventBadgeClass(event: ActivityEvent): string {
  return EVENT_BADGE_CLASSES[event] ?? "bg-neutral-soft text-ink-muted";
}

function displayValue(value: unknown): string {
  return formatFieldValue(value) ?? "—";
}
</script>

<template>
  <Spinner v-if="loading" class="mx-auto my-12 block" />
  <EmptyState
    v-else-if="entries.length === 0"
    :title="t('activity.noEntries')"
  />
  <ol v-else class="space-y-4">
    <li
      v-for="entry in entries"
      :key="entry.id"
      class="rounded-lg border border-line bg-surface p-4 sm:p-5"
    >
      <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <span
          class="rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide"
          :class="eventBadgeClass(entry.event)"
          data-testid="event-badge"
        >
          {{ t(`activity.event.${entry.event}`) }}
        </span>
        <span class="font-medium text-ink">{{ entry.subject_label }}</span>
        <span class="text-sm text-ink-muted">
          <template v-if="entry.causer">{{ entry.causer.name }}</template>
          <template v-else>{{ t("activity.system") }}</template>
        </span>
        <time
          class="ms-auto text-sm text-ink-muted"
          :datetime="entry.created_at"
          :title="formatDateTime(entry.created_at, appLocale)"
        >
          {{ formatRelativeTime(entry.created_at, appLocale) }}
        </time>
      </div>

      <p v-if="entry.edit_summary" class="mt-2 text-sm text-ink-muted">
        <span class="font-medium">{{ t("activity.editSummary") }}:</span>
        {{ entry.edit_summary }}
      </p>

      <details v-if="entry.changes.length > 0" class="mt-3">
        <summary
          class="cursor-pointer text-sm font-medium text-accent hover:text-accent-strong"
        >
          {{ t("activity.showChanges") }}
        </summary>
        <div class="mt-3 overflow-x-auto">
          <table class="w-full min-w-96 text-start text-sm">
            <thead>
              <tr class="border-b border-line text-start text-ink-muted">
                <th scope="col" class="py-2 pe-4 text-start font-medium">
                  {{ t("activity.field") }}
                </th>
                <th scope="col" class="py-2 pe-4 text-start font-medium">
                  {{ t("activity.oldValue") }}
                </th>
                <th scope="col" class="py-2 text-start font-medium">
                  {{ t("activity.newValue") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="change in entry.changes"
                :key="change.field"
                class="border-b border-line last:border-b-0"
              >
                <td class="py-2 pe-4 font-medium text-ink">
                  <LocalizedText :text="change.label" />
                </td>
                <td
                  class="py-2 pe-4 text-danger line-through decoration-danger/60"
                >
                  {{ displayValue(change.old) }}
                </td>
                <td class="py-2 text-accent-strong">
                  {{ displayValue(change.new) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </details>
    </li>
  </ol>
</template>
