<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { FileOcrBundle, OcrStageName, OcrStageRun } from "@/types/ocr";

const props = defineProps<{
  bundle: FileOcrBundle;
  pendingCount: number;
  averageConfidence: number | null;
  canRerun: boolean;
}>();
const emit = defineEmits<{ "view-fields": []; rerun: []; "rerun-stage": [stage: OcrStageName] }>();
const { t, te } = useI18n();

// Files processed before stage tracking have no stage rows; there is nothing useful to list for them.
const stages = computed(() => (props.bundle.stages?.some((s) => s.status !== null) ? props.bundle.stages : []));

function stageText(stage: OcrStageRun): string {
  if (stage.stale) return t("archive.ocr.stages.status.stale");
  if (stage.status === null) return t("archive.ocr.stages.status.notRun");
  if (stage.reason !== null && te(`archive.ocr.stages.reasons.${stage.reason}`)) {
    return t(`archive.ocr.stages.reasons.${stage.reason}`, { attempts: stage.attempts });
  }
  return t(`archive.ocr.stages.status.${stage.status}`);
}

function stageClass(stage: OcrStageRun): string {
  if (stage.status === "failed") return "text-danger";
  if (stage.stale || stage.reason === "retrying") return "text-warn";
  return stage.status === "succeeded" || stage.reason === "up_to_date" ? "text-ink" : "text-ink-muted";
}

/** What the AI stage cost: calls paid for versus answers reused from the cache. */
function correctionCost(stage: OcrStageRun): string | null {
  const s = stage.summary;
  if (stage.stage !== "correct" || s === null || typeof s.provider_calls !== "number" || typeof s.cache_hits !== "number") return null;
  return t("archive.ocr.stages.correctSummary", { calls: s.provider_calls, cached: s.cache_hits });
}

const checklist = computed(() => [
  { key: "recognized", done: true },
  { key: "arabicExtracted", done: props.bundle.texts.ar.length > 0 },
  { key: "englishExtracted", done: props.bundle.texts.en.length > 0 },
  { key: "fieldsIdentified", done: props.bundle.fields.length > 0, count: props.bundle.fields.length },
]);

const STATUS_CLASS: Record<string, string> = {
  pending: "border-line bg-surface text-ink-muted",
  processing: "border-line bg-surface text-ink-muted",
  completed: "border-line bg-surface text-ink",
  failed: "border-danger bg-danger-soft text-danger",
};
const cardClass = computed(() => STATUS_CLASS[props.bundle.status ?? "pending"]);
</script>

<template>
  <section class="rounded-lg border p-4" :class="cardClass" data-testid="ocr-status-card">
    <div class="flex items-center justify-between gap-2">
      <h2 v-if="bundle.status === 'completed'" class="text-sm font-bold text-ink">{{ t("archive.ocr.processingTitle") }}</h2>
      <h2 v-else class="text-xs font-semibold uppercase tracking-wide">{{ t("archive.ocr.title") }}</h2>
      <span class="text-xs font-medium text-ink-muted" data-testid="ocr-status-label">
        {{ bundle.status === "completed" ? t("archive.ocr.badge") : t(`archive.ocr.status.${bundle.status}`) }}
      </span>
    </div>

    <div v-if="bundle.status === 'pending' || bundle.status === 'processing'" class="mt-3" data-testid="ocr-progress">
      <div class="h-1.5 overflow-hidden rounded-full bg-neutral-soft">
        <div class="h-full rounded-full bg-accent transition-all" :style="{ width: `${bundle.progress_pct ?? 0}%` }" />
      </div>
      <p class="mt-1 text-xs tabular-nums text-ink-muted">{{ t("archive.ocr.progress", { pct: bundle.progress_pct ?? 0 }) }}</p>
    </div>

    <template v-else-if="bundle.status === 'failed'">
      <p class="mt-2 text-sm">{{ bundle.failure_reason || t("archive.ocr.failedGeneric") }}</p>
      <button v-if="canRerun" type="button" class="mt-3 w-full rounded-md border border-danger px-3 py-1.5 text-xs font-medium text-danger hover:bg-danger-soft" data-testid="rerun-ocr-button" @click="emit('rerun')">
        {{ t("archive.ocr.runOcr") }}
      </button>
    </template>

    <template v-else-if="bundle.status === 'completed'">
      <ul class="mt-4 space-y-2.5 text-sm" data-testid="ocr-checklist">
        <li v-for="step in checklist" :key="step.key" class="flex items-center gap-2" :class="step.done ? 'text-ink' : 'text-ink-muted'">
          <span aria-hidden="true" :class="step.done ? 'text-accent-strong' : ''">{{ step.done ? "✓" : "○" }}</span>
          <span>{{ t(`archive.ocr.checklist.${step.key}`, { count: step.count }) }}</span>
        </li>
      </ul>

      <div v-if="averageConfidence !== null" class="mt-4 flex items-center gap-3 text-sm">
        <span class="flex-1 text-ink-muted">{{ t("archive.ocr.averageConfidence") }}</span>
        <span class="h-1.5 w-24 overflow-hidden rounded-full bg-neutral-soft"><span class="block h-full rounded-full bg-accent" :style="{ width: `${averageConfidence}%` }" /></span>
        <span class="font-mono text-base font-bold tabular-nums">{{ averageConfidence }}%</span>
      </div>
      <p v-if="pendingCount > 0" class="mt-2 text-sm text-warn" data-testid="ocr-review-note">{{ t("archive.ocr.needsReview", { count: pendingCount }) }}</p>
      <p v-else class="mt-2 text-sm text-ink-muted">{{ t("archive.ocr.allReviewed") }}</p>

      <button
        v-if="bundle.fields.length > 0"
        type="button"
        class="mt-3 w-full rounded-md border border-ink px-3 py-1.5 text-xs font-medium text-ink hover:bg-neutral-soft"
        data-testid="view-extracted-fields"
        @click="emit('view-fields')"
      >
        {{ t("archive.ocr.viewFields") }}
      </button>
      <button
        v-if="canRerun"
        type="button"
        class="mt-2 w-full rounded-md px-3 py-1.5 text-xs font-medium text-ink-muted hover:text-ink"
        data-testid="rerun-ocr-button"
        @click="emit('rerun')"
      >
        {{ t("archive.ocr.rerunOcr") }}
      </button>
    </template>

    <div v-if="stages.length > 0" class="mt-4 border-t border-line pt-3" data-testid="ocr-stages">
      <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t("archive.ocr.stages.title") }}</h3>
      <ul class="mt-2 space-y-2 text-sm">
        <li v-for="stage in stages" :key="stage.stage" :data-testid="`ocr-stage-${stage.stage}`">
          <div class="flex items-center justify-between gap-2">
            <span class="text-ink">{{ t(`archive.ocr.stages.names.${stage.stage}`) }}</span>
            <span class="flex items-center gap-2">
              <span class="text-xs" :class="stageClass(stage)" data-testid="ocr-stage-status">{{ stageText(stage) }}</span>
              <button
                v-if="canRerun && stage.can_run"
                type="button"
                class="text-xs font-medium text-ink-muted underline hover:text-ink"
                :data-testid="`ocr-stage-rerun-${stage.stage}`"
                @click="emit('rerun-stage', stage.stage)"
              >
                {{ t("archive.ocr.stages.rerun") }}
              </button>
            </span>
          </div>
          <p v-if="correctionCost(stage)" class="text-xs tabular-nums text-ink-muted" data-testid="ocr-stage-cost">{{ correctionCost(stage) }}</p>
          <p v-if="stage.error && (stage.status === 'failed' || stage.reason === 'retrying')" class="mt-0.5 break-words text-xs text-ink-muted" data-testid="ocr-stage-error">
            {{ stage.error }}
          </p>
        </li>
      </ul>
    </div>
  </section>
</template>
