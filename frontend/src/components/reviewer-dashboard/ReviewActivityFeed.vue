<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import { isLocale, type AppLocale } from "@/i18n";
import type { ReviewActivityItem } from "@/types/reviewerDashboard";
import { formatRelativeTime } from "@/utils/format";

const props = defineProps<{
  items: ReviewActivityItem[];
}>();

const { t, locale } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const rows = computed(() =>
  props.items.map((item) => ({
    item,
    ago: formatRelativeTime(item.created_at, isLocale(locale.value) ? (locale.value as AppLocale) : "ar"),
  })),
);
</script>

<template>
  <section aria-labelledby="review-activity-heading" class="rounded-lg border border-line bg-surface p-4">
    <div class="flex items-center justify-between border-b-2 border-ink pb-2">
      <h2 id="review-activity-heading" class="text-sm font-semibold text-ink">
        {{ t("reviewerDashboard.activity.title") }}
      </h2>
      <RouterLink v-if="auth.can('activity.view')" :to="localePath('admin.activity')" class="text-xs font-medium text-accent hover:underline">
        {{ t("reviewerDashboard.recentlyReviewed.viewHistory") }}
      </RouterLink>
    </div>
    <p v-if="rows.length === 0" class="mt-3 text-sm text-ink-muted" data-testid="review-activity-empty">
      {{ t("reviewerDashboard.activity.empty") }}
    </p>
    <ul v-else class="mt-3 space-y-2" data-testid="review-activity-list">
      <li v-for="row in rows" :key="`${row.item.citable_type}:${row.item.id}:${row.item.created_at}`" class="text-sm">
        <span class="text-ink-muted">{{ t(`reviewerDashboard.activity.events.${row.item.event}`) }}</span>
        <span class="text-ink"> — <LocalizedText v-if="row.item.title" :text="row.item.title" /></span>
        <span class="block text-xs text-ink-faint">{{ row.ago }}</span>
      </li>
    </ul>
  </section>
</template>
