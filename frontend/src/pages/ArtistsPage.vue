<script setup lang="ts">
import { computed, nextTick } from "vue";
import { useI18n } from "vue-i18n";

import ArtistIndexCard from "@/components/artists/ArtistIndexCard.vue";
import ArtistLetterBar from "@/components/artists/ArtistLetterBar.vue";
import ArtistLetterGroup from "@/components/artists/ArtistLetterGroup.vue";
import ArtistsCta from "@/components/artists/ArtistsCta.vue";
import ArtistsToolbar from "@/components/artists/ArtistsToolbar.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import { useArtistsList } from "@/composables/useArtistsList";
import { useLocalePath } from "@/composables/useLocalePath";
import { formatNumber } from "@/utils/format";
import { artistLetter, ARABIC_ALPHABET, ENGLISH_ALPHABET } from "@/utils/artistLetter";
import type { AppLocale } from "@/i18n";
import type { ArtistListItem } from "@/types/artist";

const { t, locale } = useI18n();
const { localePath } = useLocalePath();

const appLocale = computed(() => locale.value as AppLocale);

const {
  items,
  meta,
  facets,
  letters,
  materialsTotal,
  loading,
  loadingMore,
  hasMore,
  error,
  retry,
  query,
  searchInput,
  setSearch,
  setSort,
  setCity,
  setThemeId,
  setItemType,
  loadMore,
  clearFilters,
} = useArtistsList();

const artistCount = computed(() => meta.value?.total ?? 0);
const summary = computed(() =>
  t("artists.summary", {
    artists: t("artists.summaryArtist", {
      count: formatNumber(artistCount.value, appLocale.value),
      n: artistCount.value,
    }),
    materials: t("artists.summaryMaterial", {
      count: formatNumber(materialsTotal.value, appLocale.value),
      n: materialsTotal.value,
    }),
  }),
);

const isAlphabetical = computed(() => query.value.sort === "alphabetical");

const alphabet = computed(() =>
  appLocale.value === "ar" ? ARABIC_ALPHABET : ENGLISH_ALPHABET,
);

function letterFor(artist: ArtistListItem): string | null {
  const name =
    appLocale.value === "ar" ? artist.name.ar : artist.name.en;
  return artistLetter(name, appLocale.value);
}

const groups = computed(() => {
  if (!isAlphabetical.value) return [];
  const map = new Map<string, ArtistListItem[]>();
  for (const artist of items.value) {
    const letter = letterFor(artist);
    if (letter === null) continue;
    if (!map.has(letter)) map.set(letter, []);
    map.get(letter)!.push(artist);
  }
  return Array.from(map.entries()).sort(
    ([a], [b]) => alphabet.value.indexOf(a) - alphabet.value.indexOf(b),
  );
});

async function jumpToLetter(letter: string): Promise<void> {
  while (
    !document.getElementById(`letter-${letter}`) &&
    hasMore.value &&
    !loadingMore.value
  ) {
    await loadMore();
    await nextTick();
  }
  const el = document.getElementById(`letter-${letter}`);
  if (el === null) return;
  const prefersReduced = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
  ).matches;
  el.scrollIntoView({
    behavior: prefersReduced ? "auto" : "smooth",
    block: "start",
  });
}

const hasFilters = computed(
  () =>
    query.value.q !== "" ||
    query.value.city !== "" ||
    query.value.themeId !== null ||
    query.value.itemType !== "",
);
</script>

<template>
  <section>
    <nav
      class="flex items-center gap-2 text-[11.5px] text-ink-faint font-latin"
      aria-label="Breadcrumb"
    >
      <RouterLink :to="localePath('home')" class="hover:text-ink">
        {{ t("nav.home") }}
      </RouterLink>
      <span aria-hidden="true">{{ appLocale === "ar" ? "←" : "→" }}</span>
      <span class="text-ink">{{ t("artists.title") }}</span>
    </nav>

    <div
      class="mt-5 flex flex-wrap items-end justify-between gap-6 border-b-2 border-ink pb-5"
    >
      <div class="max-w-[64ch]">
        <h1
          class="text-balance font-display text-[44px] font-bold leading-[1.3] text-ink"
        >
          {{ t("artists.title") }}
        </h1>
        <p
          class="mt-4 text-base leading-[1.9] text-pretty text-ink-muted"
        >
          {{ t("artists.intro") }}
        </p>
      </div>
      <p
        class="text-[12.5px] text-ink-faint tabular-nums font-latin"
        data-testid="summary"
      >
        {{ summary }}
      </p>
    </div>

    <ArtistsToolbar
      :search="searchInput"
      :sort="query.sort"
      :city="query.city"
      :theme-id="query.themeId"
      :item-type="query.itemType"
      :facets="facets"
      @update:search="setSearch"
      @update:sort="setSort"
      @update:city="setCity"
      @update:theme-id="setThemeId"
      @update:item-type="setItemType"
    />

    <ArtistLetterBar
      v-if="isAlphabetical"
      :letters="letters"
      @click="jumpToLetter"
    />

    <div id="artists-results" class="scroll-mt-24">
      <ErrorState v-if="error" :error="error" @retry="retry" />

      <template v-else>
        <div
          v-if="loading && items.length === 0"
          class="grid grid-cols-1 gap-6 min-[480px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
          aria-hidden="true"
          data-testid="skeleton"
        >
          <div
            v-for="n in 8"
            :key="n"
            class="animate-pulse"
          >
            <div class="aspect-[3/4] bg-neutral-soft" />
            <div class="mt-3 h-6 w-2/3 rounded bg-neutral-soft" />
            <div class="mt-2 h-4 w-1/2 rounded bg-neutral-soft" />
          </div>
        </div>

        <EmptyState
          v-else-if="items.length === 0"
          :title="t('artists.noResults')"
        >
          <button
            v-if="hasFilters"
            type="button"
            class="mt-4 bg-ink px-5 py-2.5 text-sm font-semibold text-paper transition-colors hover:bg-accent"
            data-testid="clear-filters"
            @click="clearFilters"
          >
            {{ t("artists.clearFilters") }}
          </button>
        </EmptyState>

        <template v-else>
          <template v-if="isAlphabetical">
            <ArtistLetterGroup
              v-for="[letter, artists] in groups"
              :key="letter"
              :letter="letter"
              :artists="artists"
            />
          </template>

          <div
            v-else
            class="grid grid-cols-1 gap-6 min-[480px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            data-testid="flat-grid"
            :style="{ rowGap: '26px', columnGap: '26px' }"
          >
            <ArtistIndexCard
              v-for="artist in items"
              :key="artist.id"
              :artist="artist"
            />
          </div>

          <div v-if="hasMore" class="mt-10 text-center">
            <button
              type="button"
              class="border border-ink px-10 py-3 text-base font-semibold text-ink transition-colors hover:bg-ink hover:text-paper disabled:opacity-50"
              :disabled="loadingMore"
              data-testid="load-more"
              @click="loadMore"
            >
              {{ loadingMore ? t("artists.loadingMore") : t("artists.loadMore") }}
            </button>
          </div>
        </template>
      </template>
    </div>

    <ArtistsCta />
  </section>
</template>
