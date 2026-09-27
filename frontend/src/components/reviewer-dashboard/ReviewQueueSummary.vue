<script setup lang="ts">
import { useI18n } from "vue-i18n";

import StatTile from "@/components/dashboard/StatTile.vue";
import type { ReviewQueueSummary } from "@/types/reviewerDashboard";

defineProps<{
  summary: ReviewQueueSummary;
}>();

const { t } = useI18n();
</script>

<template>
  <div
    class="grid grid-cols-2 divide-x divide-line border-b border-line rtl:divide-x-reverse lg:grid-cols-4"
    data-testid="review-queue-summary"
  >
    <StatTile :label="t('reviewerDashboard.summary.waitingReview')" :value="summary.waiting_review" />
    <StatTile :label="t('reviewerDashboard.summary.verificationIssues')" :value="summary.verification_issues" />
    <StatTile :label="t('reviewerDashboard.summary.changesReturned')" :value="summary.changes_returned" />
    <StatTile :label="t('reviewerDashboard.summary.reviewedToday')" :value="summary.reviewed_today" />
  </div>
</template>
