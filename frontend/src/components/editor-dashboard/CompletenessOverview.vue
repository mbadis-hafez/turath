<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import type { CompletenessBuckets, CompletenessSection } from "@/types/editorDashboard";
import { RECORD_ROUTE_NAMES } from "@/utils/editorDashboard";

const props = defineProps<{
  completeness: CompletenessSection;
}>();

const { t } = useI18n();
const { localePath } = useLocalePath();

const BUCKETS: { key: keyof CompletenessBuckets; bar: string }[] = [
  { key: "complete", bar: "bg-success" },
  { key: "high", bar: "bg-accent" },
  { key: "medium", bar: "bg-info" },
  { key: "low", bar: "bg-warn" },
];

const total = computed(() =>
  BUCKETS.reduce((sum, bucket) => sum + props.completeness.buckets[bucket.key], 0),
);

const maxBucket = computed(() =>
  Math.max(1, ...BUCKETS.map((bucket) => props.completeness.buckets[bucket.key])),
);
</script>

<template>
  <section aria-labelledby="completeness-heading" class="rounded-lg border border-line bg-surface p-4">
    <h2 id="completeness-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("editorDashboard.completeness.title") }}
    </h2>
    <p v-if="total === 0" class="mt-3 text-sm text-ink-muted" data-testid="completeness-empty">
      {{ t("editorDashboard.completeness.empty") }}
    </p>
    <template v-else>
      <ul class="mt-3 space-y-3" data-testid="completeness-buckets">
        <li v-for="bucket in BUCKETS" :key="bucket.key">
          <div class="flex items-baseline justify-between text-sm">
            <span class="text-ink">{{ t(`editorDashboard.completeness.buckets.${bucket.key}`) }}</span>
            <span class="tabular-nums text-ink-muted">{{ completeness.buckets[bucket.key] }}</span>
          </div>
          <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-neutral-soft">
            <div
              class="h-full rounded-full"
              :class="bucket.bar"
              :style="{ width: `${(completeness.buckets[bucket.key] / maxBucket) * 100}%` }"
            />
          </div>
        </li>
      </ul>
      <template v-if="completeness.lowest.length > 0">
        <h3 class="mt-4 text-xs font-semibold tracking-widest text-ink-faint uppercase">
          {{ t("editorDashboard.completeness.lowest") }}
        </h3>
        <ul class="mt-2 space-y-1.5">
          <li
            v-for="item in completeness.lowest"
            :key="`${item.entity_type}:${item.id}`"
            class="flex items-baseline justify-between gap-2 text-sm"
          >
            <RouterLink
              :to="localePath(RECORD_ROUTE_NAMES[item.entity_type], { id: item.id })"
              class="truncate text-accent hover:underline"
            >
              <LocalizedText :text="item.title" />
              <template v-if="!item.title.ar && !item.title.en">{{ t("editorDashboard.untitled") }}</template>
            </RouterLink>
            <span class="shrink-0 text-xs tabular-nums text-ink-muted">
              {{ t("editorDashboard.complete", { pct: item.completeness_pct }) }}
            </span>
          </li>
        </ul>
      </template>
    </template>
    <p class="mt-3 border-t border-line pt-2 text-xs text-ink-faint">
      {{ t("editorDashboard.completeness.note") }}
    </p>
  </section>
</template>
