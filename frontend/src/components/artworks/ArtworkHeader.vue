<script setup lang="ts">
import { useI18n } from "vue-i18n";

import AttributionBadge from "@/components/artworks/AttributionBadge.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import type { ArtworkListItem } from "@/types/artwork";

const props = defineProps<{
  artwork: ArtworkListItem;
}>();

const { t } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();
</script>

<template>
  <header>
    <div
      v-if="artwork.image_url"
      class="mb-6 aspect-[4/3] max-w-2xl overflow-hidden rounded-lg bg-neutral-soft"
    >
      <img
        :src="artwork.image_url"
        :alt="
          artwork.is_untitled
            ? t('artworks.untitled')
            : (pick(props.artwork.title)?.text ?? '')
        "
        class="size-full object-contain"
        data-testid="artwork-image"
      />
    </div>

    <div class="flex flex-wrap items-start justify-between gap-3">
      <h1 class="text-3xl font-semibold tracking-tight text-ink">
        <span
          v-if="artwork.is_untitled"
          class="rounded-sm bg-neutral-soft px-2 py-1 text-xl font-medium text-ink-muted"
        >
          {{ t("artworks.untitled") }}
        </span>
        <LocalizedText v-else :text="artwork.title" />
      </h1>
      <AttributionBadge
        v-if="artwork.attribution_certainty !== 'confirmed'"
        :certainty="artwork.attribution_certainty"
        class="mt-1"
      />
    </div>

    <p v-if="artwork.artist" class="mt-2 text-lg">
      <RouterLink
        :to="localePath('artists.show', { slug: artwork.artist.slug })"
        class="text-accent-strong hover:underline"
      >
        <LocalizedText :text="artwork.artist.name" />
      </RouterLink>
    </p>
  </header>
</template>
