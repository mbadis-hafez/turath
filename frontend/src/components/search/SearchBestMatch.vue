<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { getArtist } from "@/api/artists";
import { listArchiveItems } from "@/api/archive";
import { listArtistArtworks } from "@/api/artworks";
import LocalizedText from "@/components/common/LocalizedText.vue";
import VerifiedBadge from "@/components/artists/VerifiedBadge.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { formatLifeDates } from "@/utils/lifeDates";
import { formatNumber } from "@/utils/format";
import type { Artist, ArtistListItem } from "@/types/artist";
import type { AppLocale } from "@/i18n";

const props = defineProps<{
  /** Artist results for the current term, already sorted by relevance-ish order (name match). */
  candidates: ArtistListItem[];
  term: string;
}>();

const { t, locale } = useI18n();
const { pick } = useLocalized();
const { localePath } = useLocalePath();

/**
 * "Best match" here means: the top artist result's name starts with (or
 * equals) the search term. There is no cross-type relevance score in the
 * backend to rank artists/artworks/archive items/events against each
 * other — this is a deliberately simple, transparent heuristic, not a
 * ranker. When nothing qualifies, no hero renders.
 */
const qualifying = computed<ArtistListItem | null>(() => {
  const needle = props.term.trim().toLocaleLowerCase();
  if (needle === "") return null;
  const top = props.candidates[0];
  if (!top) return null;
  const ar = (top.name.ar ?? "").toLocaleLowerCase();
  const en = (top.name.en ?? "").toLocaleLowerCase();
  return ar.startsWith(needle) || en.startsWith(needle) ? top : null;
});

const detail = ref<Artist | null>(null);
const worksCount = ref<number | null>(null);
const materialsCount = ref<number | null>(null);
let controller: AbortController | null = null;

watch(
  qualifying,
  async (artist) => {
    controller?.abort();
    detail.value = null;
    worksCount.value = null;
    materialsCount.value = null;
    if (!artist) return;

    const self = new AbortController();
    controller = self;
    try {
      const [artistRes, worksRes, materialsRes] = await Promise.all([
        getArtist(artist.slug, self.signal),
        listArtistArtworks(artist.id, { per_page: 1 }, self.signal),
        listArchiveItems({ artist_id: artist.id, per_page: 1 }, self.signal),
      ]);
      if (controller !== self) return;
      detail.value = artistRes.data;
      worksCount.value = worksRes.meta.total;
      materialsCount.value = materialsRes.meta.total;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      // A failed enrichment call just means the hero doesn't render — the
      // grouped sections below still show this artist among the results.
      detail.value = null;
    }
  },
  { immediate: true },
);

const to = computed(() =>
  detail.value ? localePath("artists.show", { slug: detail.value.slug }) : {},
);
const primary = computed(() => (detail.value ? pick(detail.value.name) : null));
const secondary = computed(() => {
  if (!detail.value || !primary.value) return null;
  const other =
    primary.value.lang === "ar" ? detail.value.name.en : detail.value.name.ar;
  if (!other?.trim() || other === primary.value.text) return null;
  return other;
});
const dates = computed(() => {
  if (!detail.value) return null;
  const l = locale.value as AppLocale;
  const birth = formatLifeDates(detail.value.birth, l)?.text;
  const death = formatLifeDates(detail.value.death, l)?.text;
  return birth || death ? `${birth ?? "?"} – ${death ?? ""}`.trim() : null;
});
const city = computed(() =>
  detail.value?.birth?.place ? pick(detail.value.birth.place)?.text : null,
);
const hasBio = computed(() => Boolean(detail.value && pick(detail.value.bio)));
const eventsCount = computed(() => detail.value?.events?.length ?? 0);
const fmt = (value: number) => formatNumber(value, locale.value as AppLocale);
</script>

<template>
  <section
    v-if="detail"
    class="border-t-2 border-ink pt-6"
    data-testid="search-best-match"
  >
    <p
      class="mb-3 text-xs font-semibold tracking-wide text-ink-muted uppercase"
    >
      {{ t("search.filters.bestMatch") }}
    </p>
    <RouterLink :to="to" class="flex flex-col gap-6 sm:flex-row">
      <div
        class="aspect-[3/4] w-full shrink-0 overflow-hidden bg-neutral-soft sm:w-52"
      >
        <img
          v-if="detail.portrait_url"
          :src="detail.portrait_url"
          :alt="primary?.text ?? ''"
          class="size-full object-cover"
        />
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-3">
          <h2
            class="text-3xl font-semibold text-balance text-ink"
            :lang="primary?.lang"
            :dir="primary?.dir"
          >
            {{ primary?.text }}
          </h2>
          <VerifiedBadge :status="detail.verified_status" />
        </div>
        <p class="mt-1 text-ink-muted">
          <template v-if="secondary">{{ secondary }} · </template>
          <span v-if="dates" class="tabular-nums font-latin">{{ dates }}</span>
          <template v-if="city"> · {{ city }}</template>
        </p>
        <p
          v-if="hasBio"
          class="mt-4 max-w-2xl leading-relaxed text-pretty text-ink"
        >
          <LocalizedText :text="detail.bio" />
        </p>

        <div
          class="mt-6 flex flex-wrap items-center gap-x-10 gap-y-3 border-t border-line pt-4 text-sm"
        >
          <p v-if="worksCount !== null" class="text-ink-muted">
            {{ t("search.featured.works", { count: fmt(worksCount) }) }}
          </p>
          <p v-if="materialsCount !== null" class="text-ink-muted">
            {{ t("search.featured.materials", { count: fmt(materialsCount) }) }}
          </p>
          <p class="text-ink-muted">
            {{ t("search.featured.events", { count: fmt(eventsCount) }) }}
          </p>
          <span
            class="ms-auto text-accent underline-offset-4 hover:text-accent-strong hover:underline"
          >
            {{ t("search.featured.viewProfile") }}
            <span aria-hidden="true" class="inline-block rtl:-scale-x-100"
              >→</span
            >
          </span>
        </div>
      </div>
    </RouterLink>
  </section>
</template>
