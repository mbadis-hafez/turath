<script setup lang="ts">
import { computed } from "vue";

import LifeDates from "@/components/artists/LifeDates.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import VerifiedBadge from "@/components/artists/VerifiedBadge.vue";
import { useLocalized } from "@/composables/useLocalized";
import { formatLifeDates } from "@/utils/lifeDates";
import { useI18n } from "vue-i18n";
import type { Artist, Bilingual } from "@/types/artist";

const props = defineProps<{
  artist: Artist;
}>();

const { t, locale } = useI18n();
const { pick } = useLocalized();

function hasAnyText(value: Bilingual | null | undefined): boolean {
  return Boolean(value?.ar?.trim() || value?.en?.trim());
}

function dateInfo(date: Artist["birth"] | null): Artist["birth"] | null {
  if (!date) return null;
  return formatLifeDates(date, locale.value as "ar" | "en") !== null ||
    hasAnyText(date.place)
    ? date
    : null;
}

/** Other-language name, shown as a subtitle when it differs. */
const secondary = computed(() => {
  const primary = pick(props.artist.name);
  if (!primary) return null;
  const other =
    primary.lang === "ar" ? props.artist.name.en : props.artist.name.ar;
  if (!other?.trim() || other === primary.text) return null;
  return {
    text: other,
    lang: primary.lang === "ar" ? ("en" as const) : ("ar" as const),
    dir: primary.lang === "ar" ? ("ltr" as const) : ("rtl" as const),
  };
});

const birthInfo = computed(() => dateInfo(props.artist.birth));
const deathInfo = computed(() => dateInfo(props.artist.death));
</script>

<template>
  <header>
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-3xl font-semibold tracking-tight text-ink">
          <LocalizedText :text="artist.name" />
        </h1>
        <p
          v-if="secondary"
          class="mt-1 text-lg text-ink-muted"
          :lang="secondary.lang"
          :dir="secondary.dir"
        >
          {{ secondary.text }}
        </p>
      </div>
      <VerifiedBadge :status="artist.verified_status" class="mt-1" />
    </div>

    <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-sm text-ink-muted">
      <div v-if="birthInfo" class="flex gap-1">
        <dt class="sr-only">{{ t("artists.born") }}</dt>
        <dd>
          <LifeDates :date="birthInfo" :label="t('artists.born')" />
          <template v-if="hasAnyText(birthInfo.place)">
            <span aria-hidden="true"> · </span>
            <LocalizedText :text="birthInfo.place" />
          </template>
        </dd>
      </div>
      <div v-if="deathInfo" class="flex gap-1">
        <dt class="sr-only">{{ t("artists.died") }}</dt>
        <dd>
          <LifeDates :date="deathInfo" :label="t('artists.died')" />
          <template v-if="hasAnyText(deathInfo.place)">
            <span aria-hidden="true"> · </span>
            <LocalizedText :text="deathInfo.place" />
          </template>
        </dd>
      </div>
    </dl>
  </header>
</template>
