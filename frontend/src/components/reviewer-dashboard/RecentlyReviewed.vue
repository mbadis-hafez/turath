<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { isLocale, type AppLocale } from "@/i18n";
import type { RecentlyReviewedItem } from "@/types/reviewerDashboard";
import { formatRelativeTime } from "@/utils/format";

const props = defineProps<{
  items: RecentlyReviewedItem[];
}>();

const { t, locale } = useI18n();

const DECISION_BADGE: Record<RecentlyReviewedItem["decision"], string> = {
  approved: "bg-success-soft text-success",
  rejected: "bg-danger-soft text-danger",
  changes_requested: "bg-warn-soft text-warn",
};

const rows = computed(() =>
  props.items.map((item) => ({
    item,
    ago: item.reviewed_at
      ? formatRelativeTime(item.reviewed_at, isLocale(locale.value) ? (locale.value as AppLocale) : "ar")
      : null,
  })),
);
</script>

<template>
  <section aria-labelledby="recently-reviewed-heading" class="rounded-lg border border-line bg-surface p-4">
    <h2 id="recently-reviewed-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("reviewerDashboard.recentlyReviewed.title") }}
    </h2>
    <p v-if="rows.length === 0" class="mt-3 text-sm text-ink-muted" data-testid="recently-reviewed-empty">
      {{ t("reviewerDashboard.recentlyReviewed.empty") }}
    </p>
    <ul v-else class="mt-3 divide-y divide-line" data-testid="recently-reviewed-list">
      <li v-for="row in rows" :key="row.item.id" class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-ink">
            <LocalizedText v-if="row.item.title" :text="row.item.title" />
            <template v-else>{{ t("reviewerDashboard.needsReview.untitled") }}</template>
          </p>
          <p class="text-xs text-ink-muted">{{ row.ago }}</p>
        </div>
        <span class="shrink-0 rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="DECISION_BADGE[row.item.decision]">
          {{ t(`reviewerDashboard.recentlyReviewed.decision.${row.item.decision}`) }}
        </span>
      </li>
    </ul>
  </section>
</template>
