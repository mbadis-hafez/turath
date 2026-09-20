<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import type { DashboardRecord } from "@/types/completeness";
import {
  rowAction,
  SEVERITY_BADGE_CLASS,
  SEVERITY_BAR_CLASS,
} from "@/utils/severity";

const props = defineProps<{
  record: DashboardRecord;
}>();

const emit = defineEmits<{
  resolve: [record: DashboardRecord];
}>();

const { t, te } = useI18n();
const { localePath } = useLocalePath();

const action = computed(() => rowAction(props.record.severity));

const gaps = computed(() => [
  ...props.record.blocking_gaps,
  ...props.record.minor_gaps,
]);

function fieldLabel(key: string): string {
  return te(`dashboard.field.${key}`) ? t(`dashboard.field.${key}`) : key;
}

/** Records with a public detail page link there; archive items have none yet. */
const recordLink = computed(() => {
  if (props.record.entity_type === "artist" && props.record.slug) {
    return localePath("artists.show", { slug: props.record.slug });
  }
  if (props.record.entity_type === "artwork") {
    return localePath("artworks.show", { id: props.record.id });
  }
  return null;
});
</script>

<template>
  <li class="rounded-lg border border-line bg-surface p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h3 class="truncate text-base font-semibold text-ink">
          <component
            :is="recordLink ? 'RouterLink' : 'span'"
            :to="recordLink ?? undefined"
            :class="recordLink ? 'hover:underline' : ''"
          >
            <LocalizedText v-if="record.title.ar || record.title.en" :text="record.title" />
            <template v-else>{{ t("dashboard.row.untitled") }}</template>
          </component>
        </h3>
        <p class="mt-1 text-xs tabular-nums text-ink-muted">
          {{ t("dashboard.row.completion", { pct: record.completeness_pct }) }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <span
          class="rounded-sm px-1.5 py-0.5 text-xs font-medium"
          :class="SEVERITY_BADGE_CLASS[record.severity]"
          data-testid="severity-badge"
        >
          {{ t(`dashboard.severity.${record.severity}`) }}
        </span>
        <button
          v-if="action === 'resolve'"
          type="button"
          class="rounded-md bg-accent px-3 py-1.5 text-sm font-medium text-surface hover:bg-accent-strong"
          data-testid="row-action"
          @click="emit('resolve', record)"
        >
          {{ t("dashboard.action.resolve") }}
        </button>
        <component
          :is="recordLink ? 'RouterLink' : 'span'"
          v-else-if="action !== 'none'"
          :to="recordLink ?? undefined"
          class="rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink hover:bg-neutral-soft"
          data-testid="row-action"
        >
          {{ t(`dashboard.action.${action}`) }}
        </component>
      </div>
    </div>

    <div
      class="mt-3 h-1.5 overflow-hidden rounded-full bg-neutral-soft"
      role="progressbar"
      :aria-valuenow="record.completeness_pct"
      aria-valuemin="0"
      aria-valuemax="100"
    >
      <div
        class="h-full rounded-full"
        :class="SEVERITY_BAR_CLASS[record.severity]"
        :style="{ width: `${record.completeness_pct}%` }"
        data-testid="severity-bar"
      />
    </div>

    <ul v-if="gaps.length > 0" class="mt-3 flex flex-wrap gap-1.5">
      <li
        v-for="key in gaps"
        :key="key"
        class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs text-ink-muted"
      >
        {{ t("dashboard.row.gapsHint") }}: {{ fieldLabel(key) }}
      </li>
    </ul>
    <p v-if="record.open_conflict_count > 0" class="mt-2 text-xs text-warn">
      {{ t("dashboard.row.conflictsHint", { count: record.open_conflict_count }) }}
    </p>
  </li>
</template>
