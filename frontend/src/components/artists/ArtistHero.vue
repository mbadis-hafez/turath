<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalized } from "@/composables/useLocalized";
import type { Artist } from "@/types/artist";

const props = defineProps<{ artist: Artist; archiveCount: number; worksCount: number }>();
const { t } = useI18n();
const { pick } = useLocalized();

/** The other-language name, shown as a subtitle when it differs from the primary one. */
const secondary = computed(() => {
  const primary = pick(props.artist.name);
  if (!primary) return null;
  const other = primary.lang === "ar" ? props.artist.name.en : props.artist.name.ar;
  if (!other?.trim() || other === primary.text) return null;
  return { text: other, lang: primary.lang === "ar" ? ("en" as const) : ("ar" as const), dir: primary.lang === "ar" ? ("ltr" as const) : ("rtl" as const) };
});

const place = computed(() => pick(props.artist.birth?.place ?? { ar: null, en: null }));
const nationality = computed(() => (props.artist.nationality ? pick(props.artist.nationality) : null));
const hasBio = computed(() => Boolean(pick(props.artist.bio)));
const eventsCount = computed(() => props.artist.events?.length ?? 0);
const unverified = computed(() => props.artist.verified_status !== "verified");
</script>

<template>
  <header class="grid gap-8 md:grid-cols-[minmax(0,1fr)_17rem]" data-testid="artist-hero">
    <div class="md:order-2">
      <div class="aspect-[3/4] overflow-hidden bg-neutral-soft md:aspect-[3/4]">
        <img v-if="artist.portrait_url" :src="artist.portrait_url" :alt="pick(artist.name)?.text ?? ''" class="size-full object-cover" data-testid="portrait" />
      </div>
    </div>

    <div class="md:order-1">
      <h1 class="text-balance font-display text-5xl font-semibold leading-tight tracking-tight text-ink"><LocalizedText :text="artist.name" /></h1>
      <p v-if="secondary || place || nationality" class="mt-2 text-lg text-ink-muted">
        <bdi v-if="secondary" :lang="secondary.lang" :dir="secondary.dir">{{ secondary.text }}</bdi>
        <template v-if="secondary && (place || nationality)"> · </template>
        <template v-if="place"><bdi :lang="place.lang">{{ place.text }}</bdi></template>
        <template v-if="place && nationality">، </template>
        <template v-if="nationality"><bdi :lang="nationality.lang">{{ nationality.text }}</bdi></template>
      </p>

      <p v-if="hasBio" class="mt-5 max-w-3xl text-pretty text-lg leading-loose text-ink"><LocalizedText :text="artist.bio" /></p>
      <p v-else class="mt-5 text-ink-muted">{{ t("artists.noBio") }}</p>

      <ul v-if="artist.themes?.length" class="mt-6 flex flex-wrap gap-3" data-testid="themes">
        <li v-for="th in artist.themes" :key="th.id" class="border border-line bg-surface px-4 py-2 text-sm text-ink"><LocalizedText :text="th.label" /></li>
      </ul>

      <ul class="mt-6 flex flex-wrap gap-x-8 gap-y-2 border-t border-line pt-4 text-xs text-ink-muted" data-testid="stats">
        <li class="tabular-nums">{{ t("artists.stats.archive", { count: archiveCount }) }}</li>
        <li class="tabular-nums">{{ t("artists.stats.works", { count: worksCount }) }}</li>
        <li class="tabular-nums">{{ t("artists.stats.events", { count: eventsCount }) }}</li>
        <li v-if="unverified" class="text-danger" data-testid="stat-unverified">{{ t("artists.stats.unverified") }}</li>
      </ul>
    </div>
  </header>
</template>
