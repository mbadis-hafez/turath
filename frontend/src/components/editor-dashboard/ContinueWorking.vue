<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { isLocale, type AppLocale } from "@/i18n";
import type { ContinueWorkingItem } from "@/types/editorDashboard";
import { RECORD_ROUTE_NAMES } from "@/utils/editorDashboard";
import { formatRelativeTime } from "@/utils/format";

const props = defineProps<{
  items: ContinueWorkingItem[];
}>();

const { t, locale } = useI18n();
const { localePath } = useLocalePath();

const appLocale = computed<AppLocale>(() => (isLocale(locale.value) ? locale.value : "ar"));

const rows = computed(() =>
  props.items.map((item) => ({
    item,
    to: localePath(RECORD_ROUTE_NAMES[item.entity_type], { id: item.id }),
  })),
);
</script>

<template>
  <section
    id="continue-working"
    aria-labelledby="continue-working-heading"
    class="scroll-mt-24 rounded-lg border border-line bg-surface p-4"
  >
    <h2 id="continue-working-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("editorDashboard.continueWorking.title") }}
    </h2>
    <div v-if="rows.length === 0" class="mt-3" data-testid="continue-empty">
      <p class="text-sm text-ink-muted">{{ t("editorDashboard.continueWorking.empty") }}</p>
      <RouterLink
        :to="localePath('admin.artists.new')"
        class="mt-2 inline-block rounded-md bg-ink px-3 py-1.5 text-xs font-semibold text-paper hover:bg-ink/85"
        >{{ t("editorDashboard.quickActions.newArtist") }}</RouterLink
      >
    </div>
    <template v-else>
      <ul class="mt-3 divide-y divide-line" data-testid="continue-list">
        <li v-for="row in rows" :key="`${row.item.entity_type}:${row.item.id}`" class="py-3 first:pt-0 last:pb-0">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="text-xs text-ink-faint">{{ t(`editorDashboard.entities.${row.item.entity_type}`) }}</p>
              <p class="mt-0.5 truncate font-medium text-ink">
                <LocalizedText :text="row.item.title" />
                <template v-if="!row.item.title.ar && !row.item.title.en">{{
                  t("editorDashboard.untitled")
                }}</template>
              </p>
              <p class="mt-0.5 text-xs text-ink-muted">
                {{ t("editorDashboard.continueWorking.lastEdited", { when: formatRelativeTime(row.item.updated_at, appLocale) }) }}
              </p>
            </div>
            <RouterLink
              :to="row.to"
              class="shrink-0 rounded-md border border-line px-2.5 py-1 text-xs font-medium text-accent hover:border-accent"
              >{{ t("editorDashboard.continueWorking.continue") }}</RouterLink
            >
          </div>
          <div v-if="row.item.completeness_pct !== null" class="mt-2 flex items-center gap-2">
            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-neutral-soft">
              <div
                class="h-full rounded-full bg-accent"
                role="progressbar"
                :aria-valuenow="row.item.completeness_pct"
                aria-valuemin="0"
                aria-valuemax="100"
                :style="{ width: `${row.item.completeness_pct}%` }"
              />
            </div>
            <span class="text-xs tabular-nums text-ink-muted">{{
              t("editorDashboard.complete", { pct: row.item.completeness_pct })
            }}</span>
          </div>
        </li>
      </ul>
      <RouterLink
        :to="localePath('dashboard.completeness')"
        class="mt-3 inline-block text-xs font-medium text-accent hover:underline"
        >{{ t("editorDashboard.continueWorking.viewAll") }}</RouterLink
      >
    </template>
  </section>
</template>
