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
  selected: boolean;
}>();

const emit = defineEmits<{
  toggle: [id: number];
  duplicate: [artwork: AdminArtworkRow];
  delete: [artwork: AdminArtworkRow];
}>();

const { t } = useI18n();
const { pick } = useLocalized();
const { localePath } = useLocalePath();

const iconBtn = "flex-1 rounded-md border border-line p-1.5 text-ink-muted hover:bg-neutral-soft hover:text-ink";
</script>

<template>
  <div class="flex h-full flex-col" data-testid="artwork-card">
    <div class="relative aspect-[4/3] overflow-hidden bg-neutral-soft">
      <img v-if="artwork.thumbnail_url" :src="artwork.thumbnail_url" alt="" loading="lazy" class="size-full object-cover" />
      <label class="absolute start-3 top-3 flex size-6 items-center justify-center bg-paper/90">
        <input type="checkbox" class="size-4 accent-ink" :checked="selected" :aria-label="pick(artwork.title)?.text" data-testid="card-select" @change="emit('toggle', artwork.id)" />
      </label>
      <span class="absolute end-3 top-3 flex flex-wrap justify-end gap-1">
        <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="STATUS_CLASS[artwork.publication_status]" data-testid="card-status">{{ t(`curation.artworkRegistry.statuses.${artwork.publication_status}`) }}</span>
        <span v-for="f in artwork.flags" :key="f" class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="FLAG_CLASS[f]">{{ t(`curation.artworkRegistry.flags.${f}`) }}</span>
      </span>
      <span class="absolute bottom-3 start-3 rounded-sm bg-paper/90 px-1.5 py-0.5 text-xs font-medium tabular-nums text-ink" data-testid="card-image-count">
        {{ artwork.image_count > 0 ? t("curation.artworkRegistry.imageCount", { count: artwork.image_count }) : t("curation.artworkRegistry.noImages") }}
      </span>
    </div>

    <div class="flex flex-1 flex-col">
      <h2 class="mt-4 text-xl font-semibold text-ink"><RouterLink :to="localePath('admin.artworks.detail', { id: artwork.id })" class="hover:underline"><LocalizedText :text="artwork.title" /></RouterLink></h2>
      <p class="text-sm text-ink-muted">{{ pick({ ar: artwork.title.en, en: artwork.title.ar })?.text }}</p>
      <p v-if="artwork.artist" class="mt-2 text-sm font-medium text-ink"><LocalizedText :text="artwork.artist.name" /></p>
      <p class="mt-1 text-xs text-ink-muted">
        <template v-if="artwork.year">{{ artwork.year }}</template>
        <template v-if="artwork.year && (artwork.medium.ar || artwork.medium.en)"> · </template>
        <LocalizedText v-if="artwork.medium.ar || artwork.medium.en" :text="artwork.medium" />
      </p>
      <p v-if="artwork.legacy_ref" class="mt-1 font-latin text-[10.5px] text-ink-faint" dir="ltr">{{ artwork.legacy_ref }}</p>

      <div class="mt-auto flex items-center gap-2 border-t border-line pt-3">
        <RouterLink :to="localePath('admin.artworks.detail', { id: artwork.id })" class="flex-1 rounded-md border border-ink px-2 py-1.5 text-center text-xs font-semibold text-ink hover:bg-neutral-soft" data-testid="card-open">
          {{ t("curation.artworkRegistry.actions.open") }}
        </RouterLink>
        <RouterLink :to="localePath('admin.artworks.show', { id: artwork.id })" :class="iconBtn" :aria-label="t('curation.artworkRegistry.actions.edit')" data-testid="card-edit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="mx-auto size-4"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" /></svg>
        </RouterLink>
        <button type="button" :class="iconBtn" :aria-label="t('curation.artworkRegistry.actions.duplicate')" data-testid="card-duplicate" @click="emit('duplicate', artwork)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="mx-auto size-4"><rect x="9" y="9" width="12" height="12" rx="1" /><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1" /></svg>
        </button>
        <button type="button" :class="[iconBtn, 'hover:text-danger']" :aria-label="t('curation.artworkRegistry.actions.delete')" data-testid="card-delete" @click="emit('delete', artwork)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="mx-auto size-4">
            <path d="M3 6h18" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>
