<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import LifeDates from "@/components/artists/LifeDates.vue";
import VerifiedBadge from "@/components/artists/VerifiedBadge.vue";
import { useLocalized } from "@/composables/useLocalized";
import { useLocalePath } from "@/composables/useLocalePath";
import { formatLifeDates } from "@/utils/lifeDates";
import type { ArtistListItem } from "@/types/artist";

const props = defineProps<{
  artist: ArtistListItem;
}>();

const { locale } = useI18n();
const { pick } = useLocalized();
const { localePath } = useLocalePath();

const to = computed(() =>
  localePath("artists.show", { slug: props.artist.slug }),
);

/** Other-language name, shown as a secondary line when it differs. */
const secondary = computed(() => {
  const primary = pick(props.artist.name);
  if (!primary) return null;
  const other = primary.lang === "ar" ? props.artist.name.en : props.artist.name.ar;
  if (!other?.trim() || other === primary.text) return null;
  return {
    text: other,
    lang: primary.lang === "ar" ? ("en" as const) : ("ar" as const),
    dir: primary.lang === "ar" ? ("ltr" as const) : ("rtl" as const),
  };
});

const hasLifeDates = computed(
  () =>
    formatLifeDates(props.artist.birth, locale.value as "ar" | "en") !== null ||
    formatLifeDates(props.artist.death, locale.value as "ar" | "en") !== null,
);
</script>

<template>
  <RouterLink
    :to="to"
    class="block rounded-lg border border-line bg-surface p-4 transition-shadow hover:border-accent hover:shadow-md"
  >
    <div class="flex items-start justify-between gap-2">
      <div class="min-w-0">
        <h3 class="text-lg font-semibold text-ink">
          <LocalizedText :text="artist.name" />
        </h3>
        <p
          v-if="secondary"
          class="mt-0.5 text-sm text-ink-muted"
          :lang="secondary.lang"
          :dir="secondary.dir"
        >
          {{ secondary.text }}
        </p>
        <p v-if="hasLifeDates" class="mt-2 text-sm text-ink-muted">
          <LifeDates v-if="artist.birth" :date="artist.birth" />
          <span v-if="artist.death && (artist.death.display || artist.death.year_from !== null)">
            <span aria-hidden="true">–</span>
            <LifeDates :date="artist.death" />
          </span>
        </p>
      </div>
      <VerifiedBadge :status="artist.verified_status" class="shrink-0" />
    </div>
  </RouterLink>
</template>
