<script setup lang="ts">
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import type { ArchiveItem } from "@/types/archive";

defineProps<{ items: ArchiveItem[] }>();
const { t } = useI18n();

const year = (i: ArchiveItem): string | null => (i.content?.year_from ? String(i.content.year_from) : null);
</script>

<template>
  <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-testid="archive-grid">
    <li v-for="i in items" :key="i.id" data-testid="archive-card">
      <div class="relative aspect-[4/3] bg-neutral-soft">
        <span v-if="i.restricted" class="absolute end-3 top-3 border border-dashed border-ink-muted bg-surface px-2 py-0.5 text-xs text-ink-muted" data-testid="restricted-badge">{{ t("artists.archive.restricted") }}</span>
      </div>
      <p class="mt-3 flex items-center justify-between text-xs text-ink-muted">
        <span class="bg-neutral-soft px-2 py-1 font-semibold uppercase tracking-wide text-ink">{{ i.item_type }}</span>
        <span class="tabular-nums" data-testid="card-year">{{ year(i) ?? t("artists.archive.unavailable") }}</span>
      </p>
      <h3 class="mt-2 text-balance text-xl font-semibold leading-snug text-ink"><LocalizedText :text="i.title" /></h3>
    </li>
  </ul>
</template>
