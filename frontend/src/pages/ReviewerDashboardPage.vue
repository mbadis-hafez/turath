<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";

import ErrorState from "@/components/common/ErrorState.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ContentOverview from "@/components/reviewer-dashboard/ContentOverview.vue";
import DashboardGreeting from "@/components/reviewer-dashboard/DashboardGreeting.vue";
import NeedsReview from "@/components/reviewer-dashboard/NeedsReview.vue";
import PriorityReviewQueue from "@/components/reviewer-dashboard/PriorityReviewQueue.vue";
import RecentlyReviewed from "@/components/reviewer-dashboard/RecentlyReviewed.vue";
import ReturnedContent from "@/components/reviewer-dashboard/ReturnedContent.vue";
import ReviewActivityFeed from "@/components/reviewer-dashboard/ReviewActivityFeed.vue";
import ReviewerDashboardSkeleton from "@/components/reviewer-dashboard/ReviewerDashboardSkeleton.vue";
import ReviewerQuickActions from "@/components/reviewer-dashboard/ReviewerQuickActions.vue";
import ReviewPipelineWidget from "@/components/reviewer-dashboard/ReviewPipelineWidget.vue";
import ReviewQueueSummaryWidget from "@/components/reviewer-dashboard/ReviewQueueSummary.vue";
import VerificationIssues from "@/components/reviewer-dashboard/VerificationIssues.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useReviewerDashboard } from "@/composables/useReviewerDashboard";
import { useAuthStore } from "@/stores/auth";
import { reviewLinkFor } from "@/utils/reviewerDashboard";

const REVIEW_QUEUE_PERMISSIONS = [
  "review_queue.archivist_review",
  "review_queue.data_audit",
  "review_queue.second_source_needed",
  "review_queue.editorial_review",
  "review_queue.material_intake",
];

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const { localePath } = useLocalePath();
const { data, loading, error, retry } = useReviewerDashboard();

const isReviewer = computed(() => REVIEW_QUEUE_PERMISSIONS.some((p) => auth.can(p)));

function reviewNext(): void {
  const first = data.value?.priority_queue[0];
  if (!first) return;
  void router.push(reviewLinkFor(localePath, first.citable_type, first.review_type));
}
</script>

<template>
  <section>
    <ReviewerDashboardSkeleton v-if="loading && !data" />
    <ErrorState v-else-if="error" :error="error" @retry="retry" />
    <EmptyState
      v-else-if="!isReviewer"
      :title="t('reviewerDashboard.noPermission.title')"
      :description="t('reviewerDashboard.noPermission.description')"
    />
    <template v-else-if="data">
      <DashboardGreeting :name="auth.user?.name ?? ''" :waiting-review="data.summary.waiting_review" />

      <ReviewQueueSummaryWidget :summary="data.summary" class="mt-6" />

      <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_20rem]">
        <NeedsReview :items="data.needs_review" />
        <div class="space-y-6">
          <PriorityReviewQueue :items="data.priority_queue" />
          <ReviewerQuickActions :has-next="data.priority_queue.length > 0" @review-next="reviewNext" />
        </div>
      </div>

      <ReviewPipelineWidget :pipeline="data.pipeline" class="mt-6" />

      <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div id="verification-issues">
          <VerificationIssues :items="data.verification_issues" />
        </div>
        <ContentOverview :overview="data.content_overview" />
      </div>

      <ReturnedContent :items="data.returned_content" class="mt-6" />

      <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div id="recently-reviewed">
          <RecentlyReviewed :items="data.recently_reviewed" />
        </div>
        <ReviewActivityFeed :items="data.recent_activity" />
      </div>
    </template>
  </section>
</template>
