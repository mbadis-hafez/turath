<script setup lang="ts">
import { useI18n } from "vue-i18n";

import DiffValue from "@/components/proposals/DiffValue.vue";
import { useLocalized } from "@/composables/useLocalized";
import type { Bilingual } from "@/types/artist";

defineProps<{
  /** field → { before, after } in display order. */
  rows: { field: string; before: unknown; after: unknown; conflicted?: boolean }[];
  labels: Record<string, Bilingual>;
}>();

const { t } = useI18n();
const { pick } = useLocalized();
const label = (labels: Record<string, Bilingual>, field: string) => pick(labels[field] ?? { ar: null, en: null })?.text ?? field;
</script>

<template>
  <ul class="divide-y divide-line border-y border-line" data-testid="field-diffs">
    <li v-for="row in rows" :key="row.field" class="py-3" data-testid="field-diff">
      <div class="flex flex-wrap items-center gap-2">
        <p class="text-xs font-medium text-ink-muted">{{ label(labels, row.field) }}</p>
        <span v-if="row.conflicted" class="rounded-sm bg-warn-soft px-1.5 py-0.5 text-xs font-medium text-warn" data-testid="field-conflict">{{ t("proposals.conflicted") }}</span>
      </div>
      <div class="mt-1 grid gap-2 text-sm sm:grid-cols-2">
        <p><DiffValue :value="row.before" tone="old" /></p>
        <p class="border-s-2 border-success ps-3"><DiffValue :value="row.after" tone="new" /></p>
      </div>
    </li>
  </ul>
</template>
