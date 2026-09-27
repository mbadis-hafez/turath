<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { isLocale, type AppLocale } from "@/i18n";
import type { NeedsReviewItem } from "@/types/reviewerDashboard";
import { formatRelativeTime } from "@/utils/format";
import { reviewLinkFor } from "@/utils/reviewerDashboard";

const props = defineProps<{
  items: NeedsReviewItem[];
}>();

const { t, locale } = useI18n();
const { localePath } = useLocalePath();

const rows = computed(() =>
  props.items.map((item) => ({
    item,
    to: reviewLinkFor(localePath, item.citable_type, item.review_type),
    submittedAgo: formatRelativeTime(
      item.submitted_at,
      isLocale(locale.value) ? (locale.value as AppLocale) : "ar",
    ),
  })),
);
</script>

<template>
  <section aria-labelledby="priority-queue-heading" class="rounded-lg border border-line bg-surface p-4">
    <h2 id="priority-queue-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("reviewerDashboard.priorityQueue.title") }}
    </h2>
    <p v-if="rows.length === 0" class="mt-3 text-sm text-ink-muted">
      {{ t("reviewerDashboard.priorityQueue.empty") }}
    </p>
    <ol v-else class="mt-3 space-y-2" data-testid="priority-queue-list">
      <li v-for="(row, index) in rows" :key="row.item.id" class="flex items-start gap-2">
        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-ink text-xs font-semibold text-paper tabular-nums">{{ index + 1 }}</span>
        <RouterLink :to="row.to" class="min-w-0 flex-1 rounded-md px-1 py-0.5 hover:bg-neutral-soft">
          <p class="truncate text-sm font-medium text-ink">
            <LocalizedText v-if="row.item.title" :text="row.item.title" />
            <template v-else>{{ t("reviewerDashboard.needsReview.untitled") }}</template>
          </p>
          <p class="text-xs text-ink-muted">{{ row.submittedAgo }}</p>
        </RouterLink>
      </li>
    </ol>
  </section>
</template>
