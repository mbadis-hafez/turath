<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { useLocalePath } from "@/composables/useLocalePath";
import type { ReviewerContentOverview } from "@/types/reviewerDashboard";

const props = defineProps<{
  overview: ReviewerContentOverview;
}>();

const { t } = useI18n();
const { localePath } = useLocalePath();

const KEYS = ["artists", "artworks", "archive_items", "material_submissions"] as const;

const rows = computed(() =>
  KEYS.map((key) => ({
    key,
    count: props.overview[key],
    to:
      key === "material_submissions"
        ? localePath("admin.materials")
        : localePath("proposals", {}, { status: "pending" }),
  })),
);
</script>

<template>
  <section aria-labelledby="content-overview-heading" class="rounded-lg border border-line bg-surface p-4">
    <h2 id="content-overview-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("reviewerDashboard.contentOverview.title") }}
    </h2>
    <ul class="mt-3 divide-y divide-line" data-testid="content-overview">
      <li v-for="row in rows" :key="row.key">
        <RouterLink :to="row.to" class="flex items-center justify-between gap-2 py-2 text-sm hover:bg-neutral-soft">
          <span class="text-ink">{{ t(`reviewerDashboard.contentOverview.${row.key}`) }}</span>
          <span class="tabular-nums text-ink-muted">{{ row.count }}</span>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
