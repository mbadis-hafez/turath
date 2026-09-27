<script setup lang="ts">
import { useI18n } from "vue-i18n";
import type { RouteLocationRaw } from "vue-router";

import { useLocalePath } from "@/composables/useLocalePath";

defineProps<{
  hasNext: boolean;
}>();

const emit = defineEmits<{
  "review-next": [];
}>();

const { t } = useI18n();
const { localePath } = useLocalePath();

const links: { key: string; to: RouteLocationRaw }[] = [
  { key: "viewQueue", to: localePath("proposals", {}, { status: "pending" }) },
  { key: "viewVerification", to: { ...localePath("dashboard.reviewer"), hash: "#verification-issues" } },
  { key: "viewRecentlyReviewed", to: { ...localePath("dashboard.reviewer"), hash: "#recently-reviewed" } },
];
</script>

<template>
  <section aria-labelledby="quick-actions-heading" class="rounded-lg border border-line bg-surface p-4">
    <h2 id="quick-actions-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("reviewerDashboard.quickActions.title") }}
    </h2>
    <ul class="mt-3 space-y-2">
      <li v-if="hasNext">
        <button
          type="button"
          class="flex w-full items-center gap-2 rounded-md border border-line px-3 py-2 text-sm font-medium text-ink transition-colors hover:border-accent hover:text-accent"
          data-testid="quick-review-next"
          @click="emit('review-next')"
        >
          <span aria-hidden="true" class="text-accent">›</span>
          {{ t("reviewerDashboard.quickActions.reviewNext") }}
        </button>
      </li>
      <li v-for="link in links" :key="link.key">
        <RouterLink
          :to="link.to"
          class="flex items-center gap-2 rounded-md border border-line px-3 py-2 text-sm font-medium text-ink transition-colors hover:border-accent hover:text-accent"
          :data-testid="`quick-${link.key}`"
        >
          <span aria-hidden="true" class="text-accent">›</span>
          {{ t(`reviewerDashboard.quickActions.${link.key}`) }}
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
