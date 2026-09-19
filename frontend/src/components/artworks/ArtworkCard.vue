<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { formatLifeDates } from "@/utils/lifeDates";
import type { ArtworkCategory, ArtworkListItem } from "@/types/artwork";

const props = defineProps<{
  artwork: ArtworkListItem;
}>();

const { t, locale } = useI18n();
const { localePath } = useLocalePath();

const to = computed(() =>
  localePath("artworks.show", { id: props.artwork.id }),
);

const year = computed(
  () =>
    formatLifeDates(props.artwork.creation, locale.value as "ar" | "en")
      ?.text ?? null,
);

/** Simple per-category glyphs for the image placeholder (F3 adds real images). */
const ICONS: Record<ArtworkCategory, string> = {
  painting: "M4 16h16 M6 16V8l6-4 6 4v8",
  drawing: "M4 18l10-10 2 2L6 20l-3 1z M14 6l2 2",
  printmaking: "M7 8V4h10v4 M5 8h14v10H5z M9 14h6",
  sculpture: "M12 3a5 5 0 015 5c0 2-1 3-1 5h-8c0-2-1-3-1-5a5 5 0 015-5z M8 17h8 M9 21h6",
  mixed_media: "M12 3l2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5z",
  paper_work: "M6 3h8l4 4v14H6z M14 3v4h4",
  photography: "M4 8h3l2-2h6l2 2h3v10H4z M12 16a3.5 3.5 0 100-7 3.5 3.5 0 000 7z",
  installation: "M12 3v18 M5 8l14 8 M19 8L5 16",
  other: "M12 3v18 M3 12h18",
};
</script>

<template>
  <RouterLink
    :to="to"
    class="block overflow-hidden rounded-lg border border-line bg-surface transition-shadow hover:border-accent hover:shadow-md"
  >
    <div
      class="flex aspect-[4/3] items-center justify-center bg-neutral-soft"
      aria-hidden="true"
    >
      <svg
        viewBox="0 0 24 24"
        class="h-12 w-12 text-ink-muted/50"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path :d="ICONS[artwork.category]" />
      </svg>
    </div>

    <div class="p-4">
      <div class="flex items-start justify-between gap-2">
        <h3 class="min-w-0 text-lg font-semibold text-ink">
          <span
            v-if="artwork.is_untitled"
            class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-sm font-medium text-ink-muted"
          >
            {{ t("artworks.untitled") }}
          </span>
          <LocalizedText v-else :text="artwork.title" />
        </h3>
        <span
          class="shrink-0 rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs text-ink-muted"
        >
          {{ t(`artworks.category.${artwork.category}`) }}
        </span>
      </div>

      <p class="mt-1 text-sm text-ink-muted">
        <template v-if="artwork.artist">
          <LocalizedText :text="artwork.artist.name" />
        </template>
        <template v-if="year">
          <span aria-hidden="true"> · </span>{{ year }}
        </template>
      </p>

      <p v-if="artwork.medium.ar?.trim() || artwork.medium.en?.trim()" class="mt-1 text-sm text-ink-muted">
        <LocalizedText :text="artwork.medium" />
      </p>
    </div>
  </RouterLink>
</template>
