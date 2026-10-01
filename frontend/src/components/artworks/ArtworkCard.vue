<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { formatLifeDates } from "@/utils/lifeDates";
import type { ArtworkListItem } from "@/types/artwork";

const props = defineProps<{
  artwork: ArtworkListItem;
}>();

const { t, locale } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();

const to = computed(() =>
  localePath("artworks.show", { id: props.artwork.id }),
);

const year = computed(
  () =>
    formatLifeDates(props.artwork.creation, locale.value as "ar" | "en")
      ?.text ?? null,
);

const yearApproximate = computed(() => {
  const certainty = props.artwork.creation?.certainty;
  return certainty === "circa" || certainty === "unknown";
});

const secondaryTitle = computed(() => {
  if (props.artwork.is_untitled) return null;
  const primary = pick(props.artwork.title);
  if (!primary) return null;
  const other = primary.lang === "ar" ? props.artwork.title.en : props.artwork.title.ar;
  if (!other?.trim() || other === primary.text) return null;
  return { text: other, lang: primary.lang === "ar" ? ("en" as const) : ("ar" as const), dir: primary.lang === "ar" ? ("ltr" as const) : ("rtl" as const) };
});

const hasMedium = computed(() => Boolean(props.artwork.medium.ar?.trim() || props.artwork.medium.en?.trim()));
</script>

<template>
  <RouterLink :to="to" class="block" :aria-label="artwork.is_untitled ? t('artworks.untitled') : (pick(artwork.title)?.text ?? '')">
    <div class="relative aspect-[4/3] overflow-hidden bg-neutral-soft">
      <img v-if="artwork.image_url" :src="artwork.image_url" alt="" class="size-full object-cover" data-testid="artwork-image" />
      <div v-else class="flex size-full items-center justify-center p-4 text-center text-sm text-ink-faint" data-testid="artwork-no-image">
        {{ t("artworks.noImage") }}
      </div>
    </div>

    <h3 class="mt-3 font-display text-[21px] font-bold leading-[1.45] text-ink">
      <span v-if="artwork.is_untitled" class="bg-neutral-soft px-1.5 py-0.5 text-base font-medium text-ink-muted">{{ t("artworks.untitled") }}</span>
      <LocalizedText v-else :text="artwork.title" />
    </h3>
    <p v-if="secondaryTitle" class="mt-1 font-latin text-[11.5px] leading-relaxed text-ink-faint" :lang="secondaryTitle.lang" :dir="secondaryTitle.dir">{{ secondaryTitle.text }}</p>

    <p v-if="artwork.artist" class="mt-2.5 text-sm text-ink">
      <LocalizedText :text="artwork.artist.name" />
    </p>

    <p v-if="year" class="mt-1 flex flex-wrap items-center gap-2 font-latin text-xs text-ink-muted">
      <span>{{ year }}</span>
      <span v-if="yearApproximate" class="border border-dashed border-line px-1.5 py-0.5 text-[10.5px] text-ink-faint" data-testid="approx-year-badge">{{ t("artworks.approxYear") }}</span>
    </p>

    <p v-if="hasMedium" class="mt-2.5 border-t border-line pt-2.5 text-sm leading-relaxed text-ink-muted">
      <LocalizedText :text="artwork.medium" />
    </p>
  </RouterLink>
</template>
