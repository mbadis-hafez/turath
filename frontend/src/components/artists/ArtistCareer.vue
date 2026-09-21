<script setup lang="ts">
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import type { ArchiveItem } from "@/types/archive";

defineProps<{ items: ArchiveItem[] }>();
const { t } = useI18n();

/** Only an exact year is stated as a fact; anything vaguer is labelled rather than passed off as one. */
const yearLabel = (i: ArchiveItem): string | null =>
  i.content?.certainty === "exact" && i.content.year_from ? String(i.content.year_from) : null;
</script>

<template>
  <ol class="border-t-2 border-ink" data-testid="career">
    <li v-for="i in items" :key="i.id" class="grid grid-cols-[5.5rem_minmax(0,1fr)_auto] items-baseline gap-x-6 border-b border-line py-4" data-testid="career-row">
      <span class="text-sm tabular-nums" :class="yearLabel(i) ? 'text-ink-muted' : 'text-ink-faint'" data-testid="career-year">{{ yearLabel(i) ?? t("artists.career.yearUncertain") }}</span>
      <span class="text-lg text-ink"><LocalizedText :text="i.title" /></span>
      <span v-if="i.legacy_ref" class="text-xs text-ink-faint" dir="ltr">{{ i.legacy_ref }}</span>
    </li>
  </ol>
</template>
