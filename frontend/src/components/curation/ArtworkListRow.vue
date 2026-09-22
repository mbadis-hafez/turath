<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { FLAG_CLASS, STATUS_CLASS } from "@/components/curation/ArtworkGridCard.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import type { AdminArtworkRow, ArtworkFlag, ArtworkStatus } from "@/types/artworkCuration";

const props = defineProps<{
  artwork: AdminArtworkRow;
}>();

const { t, locale } = useI18n();
const { pick } = useLocalized();
const { localePath } = useLocalePath();

const otherLang = computed(() => (locale.value === "ar" ? "en" : "ar"));
const secondaryTitle = computed(() => {
  const value = props.artwork.title[otherLang.value];
  const primary = pick(props.artwork.title);
  return value && value !== primary?.text ? value : null;
});

function statusClass(status: ArtworkStatus): string | null {
  return STATUS_CLASS[status] ?? null;
}

function flagClass(flag: ArtworkFlag): string | null {
  return FLAG_CLASS[flag] ?? null;
}
</script>

<template>
  <RouterLink
    :to="localePath('admin.artworks.show', { id: artwork.id })"
    class="flex items-start gap-3 rounded-md border border-line bg-surface p-3 transition-colors hover:bg-neutral-soft sm:items-center sm:gap-4"
  >
    <span class="relative block size-16 shrink-0 overflow-hidden rounded-sm bg-neutral-soft sm:size-20">
      <img v-if="artwork.thumbnail_url" :src="artwork.thumbnail_url" alt="" loading="lazy" class="size-full object-cover" />
    </span>
    <span class="min-w-0 flex-1">
      <span class="block">
        <span class="text-base font-semibold text-ink"><LocalizedText :text="artwork.title" /></span>
        <span v-if="secondaryTitle" class="block truncate text-sm text-ink-muted" :lang="otherLang">{{ secondaryTitle }}</span>
      </span>
      <span class="mt-1 block flex flex-wrap items-center gap-x-2 text-xs text-ink-muted">
        <LocalizedText v-if="artwork.artist" :text="artwork.artist.name" />
        <template v-if="artwork.artist && (artwork.year || artwork.holder)"> · </template>
        <template v-if="artwork.year">{{ artwork.year }}</template>
        <template v-if="artwork.year && artwork.holder"> · </template>
        <LocalizedText v-if="artwork.holder" :text="artwork.holder.name" />
      </span>
    </span>
    <span class="flex shrink-0 flex-wrap justify-end gap-1 sm:max-w-48">
      <span v-if="statusClass(artwork.publication_status)" class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="statusClass(artwork.publication_status)">{{ t(`curation.artworkRegistry.statuses.${artwork.publication_status}`) }}</span>
      <span v-for="f in artwork.flags" v-show="flagClass(f)" :key="f" class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="flagClass(f)">{{ t(`curation.artworkRegistry.flags.${f}`) }}</span>
    </span>
  </RouterLink>
</template>
