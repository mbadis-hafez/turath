<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import type { RouteLocationRaw } from "vue-router";

import { useLocalePath } from "@/composables/useLocalePath";
import type { PipelineStage, ReviewPipeline } from "@/types/editorDashboard";

const props = defineProps<{
  pipeline: ReviewPipeline;
}>();

const { t } = useI18n();
const { localePath } = useLocalePath();

const STAGES: PipelineStage[] = [
  "draft",
  "in_progress",
  "ready_for_review",
  "under_review",
  "changes_requested",
  "approved",
  "published",
];

interface StageRow {
  stage: PipelineStage;
  count: number;
  to: RouteLocationRaw | null;
}

const rows = computed<StageRow[]>(() =>
  STAGES.map((stage) => {
    let to: RouteLocationRaw | null = null;
    if (stage === "in_progress") {
      to = localePath("proposals", {}, { status: "draft", mine: "1" });
    } else if (stage === "ready_for_review") {
      to = localePath("proposals", {}, { status: "pending", mine: "1" });
    } else if (stage === "changes_requested") {
      to = localePath("proposals", {}, { status: "changes_requested", mine: "1" });
    } else if (stage === "draft") {
      to = { ...localePath("dashboard"), hash: "#continue-working" };
    }
    return { stage, count: props.pipeline[stage], to };
  }),
);
</script>

<template>
  <section aria-labelledby="pipeline-heading" class="rounded-lg border border-line bg-surface p-4">
    <h2 id="pipeline-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("editorDashboard.pipeline.title") }}
    </h2>
    <ol class="mt-3 space-y-1" data-testid="pipeline">
      <li
        v-for="(row, index) in rows"
        :key="row.stage"
        class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm"
        :class="row.stage === 'changes_requested' && row.count > 0 ? 'bg-warn-soft' : ''"
      >
        <span class="flex min-w-0 items-center gap-2">
          <span
            v-if="index > 0"
            aria-hidden="true"
            class="text-ink-faint ltr:rotate-0 rtl:rotate-180"
            >↓</span
          >
          <component
            :is="row.to ? 'RouterLink' : 'span'"
            :to="row.to ?? undefined"
            class="truncate"
            :class="row.to ? 'text-accent hover:underline' : 'text-ink'"
            >{{ t(`editorDashboard.pipeline.stages.${row.stage}`) }}</component
          >
        </span>
        <span
          class="tabular-nums"
          :class="row.stage === 'changes_requested' && row.count > 0 ? 'font-semibold text-warn' : 'text-ink-muted'"
          >{{ row.count }}</span
        >
      </li>
    </ol>
    <p class="mt-3 border-t border-line pt-2 text-xs text-ink-faint">
      {{ t("editorDashboard.pipeline.infoNote") }}
    </p>
  </section>
</template>
