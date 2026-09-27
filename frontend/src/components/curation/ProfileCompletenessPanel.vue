<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { useLocalized } from "@/composables/useLocalized";
import type { ChecklistItem } from "@/types/artistCuration";
import type { CompletenessSectionKey, ProfileCompletenessSummary } from "@/types/completeness";

const props = withDefaults(defineProps<{
  summary: ProfileCompletenessSummary;
  /** Full checklist with met flags (curation page); omitted on the create page. */
  items?: ChecklistItem[];
  /** "danger" keeps the red framing used for incomplete records. */
  tone?: "neutral" | "danger";
}>(), { items: undefined, tone: "neutral" });

const { t, te } = useI18n();
const { pick } = useLocalized();

const SECTIONS: CompletenessSectionKey[] = ["identity", "biography", "media"];

const frameClass = computed(() => (props.tone === "danger" ? "border-danger bg-danger-soft" : "border-line bg-surface"));
const headingClass = computed(() => (props.tone === "danger" ? "text-danger" : "text-ink"));
const barClass = computed(() => (props.summary.complete ? "bg-success" : "bg-danger"));

/** Missing-field labels resolve from curation.checklistItem.* so they follow the UI locale. */
function missingLabel(key: string, label: { ar: string; en: string }): string {
  const i18nKey = `curation.checklistItem.${key}`;
  if (te(i18nKey)) return t(i18nKey);
  return pick(label)?.text ?? key;
}
</script>

<template>
  <section class="rounded-lg border p-4" :class="frameClass" data-testid="completeness-panel">
    <h2 class="text-base font-semibold" :class="headingClass">{{ t("curation.detail.checklist") }}</h2>
    <p class="mt-2 text-2xl font-semibold tabular-nums text-ink" data-testid="completeness-pct">{{ summary.percentage }}%</p>
    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-neutral-soft">
      <div class="h-full rounded-full" :class="barClass" :style="{ width: `${summary.percentage}%` }" />
    </div>
    <p class="mt-1 text-xs tabular-nums text-ink-muted" data-testid="required-met">{{ t("curation.detail.requiredMet", { met: summary.met_count, total: summary.total_count }) }}</p>

    <ul v-if="items?.length" class="mt-3 space-y-2" data-testid="checklist">
      <li v-for="item in items" :key="item.key" class="flex items-center gap-2 text-sm" :class="item.met ? 'text-ink-muted' : 'text-ink'">
        <input type="checkbox" class="size-4" :checked="item.met" disabled :aria-label="t(`curation.checklistItem.${item.key}`)" />
        <span>{{ t(`curation.checklistItem.${item.key}`) }}</span>
        <span v-if="!item.supported" class="text-xs text-ink-muted">({{ t("curation.detail.unsupported") }})</span>
      </li>
    </ul>

    <ul class="mt-3 space-y-2" data-testid="completeness-sections">
      <li v-for="section in SECTIONS" :key="section">
        <div class="flex items-center justify-between text-xs">
          <span class="text-ink">{{ t(`curation.sections.${section}`) }}</span>
          <span class="tabular-nums text-ink-muted">{{ summary.sections[section].met }}/{{ summary.sections[section].total }}</span>
        </div>
        <div class="mt-1 h-1 overflow-hidden rounded-full bg-neutral-soft">
          <div class="h-full rounded-full" :class="summary.sections[section].percentage === 100 ? 'bg-success' : 'bg-warn'" :style="{ width: `${summary.sections[section].percentage}%` }" />
        </div>
      </li>
    </ul>

    <template v-if="summary.missing.length">
      <h3 class="mt-3 text-xs font-semibold text-danger">{{ t("curation.detail.missingRequired") }}</h3>
      <ul class="mt-1 space-y-1" data-testid="missing-fields">
        <li v-for="item in summary.missing" :key="item.key" class="text-xs text-ink">
          {{ missingLabel(item.key, item.label) }}
        </li>
      </ul>
    </template>
  </section>
</template>
