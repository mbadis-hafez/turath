<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { isLocale, type AppLocale } from "@/i18n";
import { useAuthStore } from "@/stores/auth";
import type { RecentActivityItem } from "@/types/editorDashboard";
import { RECORD_ROUTE_NAMES } from "@/utils/editorDashboard";
import { formatRelativeTime } from "@/utils/format";

const props = defineProps<{
  items: RecentActivityItem[];
}>();

const { t, te, locale } = useI18n();
const auth = useAuthStore();
const { localePath } = useLocalePath();

const appLocale = computed<AppLocale>(() => (isLocale(locale.value) ? locale.value : "ar"));

function eventLabel(event: string): string {
  const key = `editorDashboard.activity.events.${event}`;
  return te(key) ? t(key) : event;
}

const rows = computed(() =>
  props.items.map((item) => ({
    item,
    to: localePath(RECORD_ROUTE_NAMES[item.entity_type], { id: item.id }),
  })),
);
</script>

<template>
  <section aria-labelledby="recent-activity-heading" class="rounded-lg border border-line bg-surface p-4">
    <h2 id="recent-activity-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("editorDashboard.activity.title") }}
    </h2>
    <p v-if="rows.length === 0" class="mt-3 text-sm text-ink-muted" data-testid="activity-empty">
      {{ t("editorDashboard.activity.empty") }}
    </p>
    <template v-else>
      <ul class="mt-3 divide-y divide-line" data-testid="activity-list">
        <li v-for="(row, index) in rows" :key="`${row.item.entity_type}:${row.item.id}:${index}`" class="py-2.5 first:pt-0 last:pb-0">
          <div class="flex items-baseline justify-between gap-2">
            <div class="min-w-0">
              <p class="truncate text-sm">
                <span class="text-xs text-ink-faint">{{ t(`editorDashboard.entities.${row.item.entity_type}`) }}</span>
                <RouterLink :to="row.to" class="ms-1 font-medium text-ink hover:text-accent">
                  <LocalizedText :text="row.item.title" />
                  <template v-if="!row.item.title.ar && !row.item.title.en">{{
                    t("editorDashboard.untitled")
                  }}</template>
                </RouterLink>
              </p>
              <p class="text-xs text-ink-muted">
                {{ eventLabel(row.item.event) }} ·
                {{ formatRelativeTime(row.item.created_at, appLocale) }}
              </p>
            </div>
          </div>
        </li>
      </ul>
      <RouterLink
        v-if="auth.can('activity.view')"
        :to="localePath('admin.activity')"
        class="mt-3 inline-block text-xs font-medium text-accent hover:underline"
        >{{ t("editorDashboard.activity.viewAll") }}</RouterLink
      >
    </template>
  </section>
</template>
