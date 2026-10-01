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
  selected: boolean;
}>();

const emit = defineEmits<{
  toggle: [id: number];
  duplicate: [artwork: AdminArtworkRow];
  delete: [artwork: AdminArtworkRow];
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

const iconBtn = "rounded-md border border-line p-1.5 text-ink-muted hover:bg-neutral-soft hover:text-ink";
</script>

<template>
  <tr class="border-b border-line align-middle hover:bg-neutral-soft/50" data-testid="artwork-card">
    <td class="w-10 px-3 py-3"><input type="checkbox" class="size-4 accent-ink" :checked="selected" :aria-label="pick(artwork.title)?.text" data-testid="row-select" @change="emit('toggle', artwork.id)" /></td>
    <td class="px-3 py-3"><span class="block size-14 shrink-0 overflow-hidden rounded-sm bg-neutral-soft"><img v-if="artwork.thumbnail_url" :src="artwork.thumbnail_url" alt="" loading="lazy" class="size-full object-cover" /></span></td>
    <td class="min-w-0 px-3 py-3">
      <RouterLink :to="localePath('admin.artworks.detail', { id: artwork.id })" class="block text-base font-semibold text-ink hover:underline"><LocalizedText :text="artwork.title" /></RouterLink>
      <span v-if="secondaryTitle" class="block truncate text-sm text-ink-muted" :lang="otherLang">{{ secondaryTitle }}</span>
      <span v-if="artwork.legacy_ref" class="block font-latin text-[10.5px] text-ink-faint" dir="ltr">{{ artwork.legacy_ref }}</span>
    </td>
    <td class="px-3 py-3 text-sm text-ink"><LocalizedText v-if="artwork.artist" :text="artwork.artist.name" /><template v-else>—</template></td>
    <td class="px-3 py-3 text-sm text-ink-muted">
      <template v-if="artwork.year">{{ artwork.year }}</template><template v-else>—</template>
      <span v-if="artwork.medium.ar || artwork.medium.en" class="block text-xs"><LocalizedText :text="artwork.medium" /></span>
    </td>
    <td class="px-3 py-3 text-sm text-ink-muted"><LocalizedText v-if="artwork.holder" :text="artwork.holder.name" /><template v-else>—</template></td>
    <td class="px-3 py-3 text-sm tabular-nums" :class="artwork.image_count > 0 ? 'text-ink-muted' : 'font-semibold text-danger'" data-testid="row-image-count">
      {{ artwork.image_count > 0 ? t("curation.artworkRegistry.imageCount", { count: artwork.image_count }) : t("curation.artworkRegistry.noImages") }}
    </td>
    <td class="px-3 py-3">
      <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="statusClass(artwork.publication_status)">{{ t(`curation.artworkRegistry.statuses.${artwork.publication_status}`) }}</span>
      <span v-for="f in artwork.flags" v-show="flagClass(f)" :key="f" class="ms-1 rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="flagClass(f)">{{ t(`curation.artworkRegistry.flags.${f}`) }}</span>
    </td>
    <td class="px-3 py-3">
      <div class="flex items-center gap-1.5">
        <RouterLink :to="localePath('admin.artworks.detail', { id: artwork.id })" :class="iconBtn" :aria-label="t('curation.artworkRegistry.actions.view')" data-testid="row-view">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="size-4"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></svg>
        </RouterLink>
        <RouterLink :to="localePath('admin.artworks.show', { id: artwork.id })" :class="iconBtn" :aria-label="t('curation.artworkRegistry.actions.edit')" data-testid="row-edit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="size-4"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" /></svg>
        </RouterLink>
        <button type="button" :class="iconBtn" :aria-label="t('curation.artworkRegistry.actions.duplicate')" data-testid="row-duplicate" @click="emit('duplicate', artwork)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="size-4"><rect x="9" y="9" width="12" height="12" rx="1" /><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1" /></svg>
        </button>
        <button type="button" :class="[iconBtn, 'hover:text-danger']" :aria-label="t('curation.artworkRegistry.actions.delete')" data-testid="row-delete" @click="emit('delete', artwork)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="size-4">
            <path d="M3 6h18" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
          </svg>
        </button>
      </div>
    </td>
  </tr>
</template>
