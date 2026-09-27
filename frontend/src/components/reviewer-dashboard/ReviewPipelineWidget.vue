<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { ReviewPipeline, ReviewPipelineStage } from "@/types/reviewerDashboard";

const props = defineProps<{
  pipeline: ReviewPipeline;
}>();

const { t } = useI18n();

const STAGES: ReviewPipelineStage[] = ["ready_for_review", "changes_requested", "approved", "rejected"];

const rows = computed(() => STAGES.map((stage) => ({ stage, count: props.pipeline[stage] })));
</script>

<template>
  <section aria-labelledby="pipeline-heading" class="rounded-lg border border-line bg-surface p-4">
    <h2 id="pipeline-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("reviewerDashboard.pipeline.title") }}
    </h2>
    <ol class="mt-3 space-y-1" data-testid="review-pipeline">
      <li
        v-for="(row, index) in rows"
        :key="row.stage"
        class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm"
        :class="row.stage === 'changes_requested' && row.count > 0 ? 'bg-warn-soft' : ''"
      >
        <span class="flex min-w-0 items-center gap-2">
          <span v-if="index > 0" aria-hidden="true" class="text-ink-faint ltr:rotate-0 rtl:rotate-180">↓</span>
          <span class="truncate text-ink">{{ t(`reviewerDashboard.pipeline.stages.${row.stage}`) }}</span>
        </span>
        <span
          class="tabular-nums"
          :class="row.stage === 'changes_requested' && row.count > 0 ? 'font-semibold text-warn' : 'text-ink-muted'"
          >{{ row.count }}</span
        >
      </li>
    </ol>
    <p class="mt-3 border-t border-line pt-2 text-xs text-ink-faint">
      {{ t("reviewerDashboard.pipeline.infoNote") }}
    </p>
  </section>
</template>
