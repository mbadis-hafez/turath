<script lang="ts">
import type { ArtworkFlag, ArtworkStatus } from "@/types/artworkCuration";

export const STATUS_CLASS: Record<ArtworkStatus, string> = {
  draft: "bg-warn-soft text-warn",
  published: "bg-success-soft text-success",
  hidden: "bg-neutral-soft text-ink-muted",
};

export const FLAG_CLASS: Record<ArtworkFlag, string> = {
  untitled: "bg-danger-soft text-danger",
  missing_dimensions: "bg-danger-soft text-danger",
  holder_missing: "bg-danger-soft text-danger",
  year_uncertain: "bg-danger-soft text-danger",
};
</script>

<script setup lang="ts">
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import type { AdminArtworkRow } from "@/types/artworkCuration";

defineProps<{
  artwork: AdminArtworkRow;
}>();

const { t } = useI18n();
const { pick } = useLocalized();
const { localePath } = useLocalePath();
</script>

<template>
  <div>
    <div class="relative aspect-[4/3] overflow-hidden bg-neutral-soft">
      <img v-if="artwork.thumbnail_url" :src="artwork.thumbnail_url" alt="" loading="lazy" class="size-full object-cover" />
      <span class="absolute end-3 top-3 flex flex-wrap gap-1">
        <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="STATUS_CLASS[artwork.publication_status]">{{ t(`curation.artworkRegistry.statuses.${artwork.publication_status}`) }}</span>
        <span v-for="f in artwork.flags" :key="f" class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="FLAG_CLASS[f]">{{ t(`curation.artworkRegistry.flags.${f}`) }}</span>
      </span>
    </div>
    <h2 class="mt-4 text-xl font-semibold text-ink"><RouterLink :to="localePath('admin.artworks.show', { id: artwork.id })" class="hover:underline"><LocalizedText :text="artwork.title" /></RouterLink></h2>
    <p class="text-sm text-ink-muted">{{ pick({ ar: artwork.title.en, en: artwork.title.ar })?.text }}</p>
    <p v-if="artwork.artist" class="mt-2 text-sm font-medium text-ink"><LocalizedText :text="artwork.artist.name" /></p>
    <p class="mt-1 text-xs text-ink-muted">
      <template v-if="artwork.year">{{ artwork.year }}</template>
      <template v-if="artwork.year && artwork.holder"> · </template>
      <LocalizedText v-if="artwork.holder" :text="artwork.holder.name" />
    </p>
    <p class="mt-3 flex items-center justify-between border-t border-line pt-2 text-xs tabular-nums text-ink-muted">
      <span>{{ t("curation.artworkRegistry.pipeline", { cleared: artwork.pipeline.cleared, total: artwork.pipeline.total }) }}</span>
      <span>{{ artwork.completeness_pct }}%</span>
    </p>
  </div>
</template>
