<script setup lang="ts">
import { computed } from "vue";

import ErrorState from "@/components/common/ErrorState.vue";
import ArchiveMaterials from "@/components/editor-dashboard/ArchiveMaterials.vue";
import ChangesRequested from "@/components/editor-dashboard/ChangesRequested.vue";
import CompletenessOverview from "@/components/editor-dashboard/CompletenessOverview.vue";
import ContentOverview from "@/components/editor-dashboard/ContentOverview.vue";
import ContinueWorking from "@/components/editor-dashboard/ContinueWorking.vue";
import DashboardGreeting from "@/components/editor-dashboard/DashboardGreeting.vue";
import DashboardSkeleton from "@/components/editor-dashboard/DashboardSkeleton.vue";
import MyWorkSummary from "@/components/editor-dashboard/MyWorkSummary.vue";
import NeedsAttention from "@/components/editor-dashboard/NeedsAttention.vue";
import QuickActions from "@/components/editor-dashboard/QuickActions.vue";
import RecentActivity from "@/components/editor-dashboard/RecentActivity.vue";
import ReviewPipeline from "@/components/editor-dashboard/ReviewPipeline.vue";
import { useEditorDashboard } from "@/composables/useEditorDashboard";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();
const { localePath } = useLocalePath();
const { data, loading, error, retry } = useEditorDashboard();

const changesItems = computed(
  () => data.value?.needs_attention.filter((item) => item.kind === "changes_requested") ?? [],
);
const changesCount = computed(() => data.value?.my_work.changes_requested ?? 0);
</script>

<template>
  <section>
    <DashboardGreeting v-if="auth.user" :name="auth.user.name" />

    <ErrorState v-if="error" class="mt-8" :error="error" @retry="retry" />
    <DashboardSkeleton v-else-if="loading && !data" class="mt-0" />
    <template v-else-if="data">
      <MyWorkSummary :my-work="data.my_work" />

      <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
        <NeedsAttention :items="data.needs_attention" />
        <QuickActions />
      </div>

      <div class="mt-6 grid gap-6" :class="changesCount > 0 ? 'lg:grid-cols-2' : ''">
        <ChangesRequested v-if="changesCount > 0" :count="changesCount" :items="changesItems" />
        <ReviewPipeline :pipeline="data.review_pipeline" />
      </div>

      <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <ContentOverview :overview="data.content_overview" />
        <CompletenessOverview :completeness="data.completeness" />
      </div>

      <ArchiveMaterials class="mt-6" :archive="data.archive" />

      <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <ContinueWorking :items="data.continue_working" />
        <RecentActivity :items="data.recent_activity" />
      </div>

      <p class="mt-8 border-t border-line pt-4">
        <RouterLink
          :to="localePath('dashboard.completeness')"
          class="text-sm font-medium text-accent hover:underline"
          >{{ $t("editorDashboard.completenessReport") }}</RouterLink
        >
      </p>
    </template>
  </section>
</template>
