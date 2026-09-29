<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import ArtworkCard from "@/components/artworks/ArtworkCard.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import PartialDateDisplay from "@/components/common/PartialDateDisplay.vue";
import Spinner from "@/components/common/Spinner.vue";
import SearchArchiveResultRow from "@/components/search/SearchArchiveResultRow.vue";
import SearchArtistResultCard from "@/components/search/SearchArtistResultCard.vue";
import SearchBar from "@/components/search/SearchBar.vue";
import SearchBestMatch from "@/components/search/SearchBestMatch.vue";
import SearchFacetSidebar from "@/components/search/SearchFacetSidebar.vue";
import SearchFilterChips from "@/components/search/SearchFilterChips.vue";
import SectionHeader from "@/components/search/SectionHeader.vue";
import {
  MIN_TERM_LENGTH,
  useSiteSearch,
  type SectionKey,
} from "@/composables/useSiteSearch";
import { useLocalePath } from "@/composables/useLocalePath";
import { formatNumber } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const { t, locale } = useI18n();
const { localePath } = useLocalePath();

const {
  sections,
  archiveFacets,
  active,
  anyLoading,
  totalResults,
  nothingFound,
  retry,
  query,
  searchInput,
  setSearch,
  toggleType,
  toggleItemType,
  setVerifiedOnly,
  clearFilters,
} = useSiteSearch();

const viewAll = (name: string) =>
  localePath(name, {}, { q: query.value.q.trim() });
const eventLink = (id: number) => localePath("events.show", { id });
const count = computed(() =>
  formatNumber(totalResults.value, locale.value as AppLocale),
);
const tooShort = computed(
  () =>
    searchInput.value.trim().length > 0 &&
    searchInput.value.trim().length < MIN_TERM_LENGTH,
);

const totals = computed<Record<SectionKey, number>>(() => ({
  artists: sections.artists.total,
  artworks: sections.artworks.total,
  archive: sections.archive.total,
  events: sections.events.total,
}));

const shows = (key: SectionKey) => query.value.types.includes(key);
</script>

<template>
  <section>
    <h1 class="sr-only">{{ t("search.searchLabel") }}</h1>
    <SearchBar :model-value="searchInput" @update:model-value="setSearch" />

    <div v-if="active" class="mt-4 flex flex-wrap items-center gap-3">
      <p
        class="min-h-6 text-sm text-ink-muted tabular-nums"
        aria-live="polite"
        data-testid="summary"
      >
        <template v-if="!anyLoading && totalResults > 0">{{
          t("search.resultsCount", { count })
        }}</template>
      </p>
      <SearchFilterChips
        :item-types="query.itemTypes"
        :verified-only="query.verifiedOnly"
        @remove-item-type="toggleItemType"
        @remove-verified-only="setVerifiedOnly(false)"
      />
    </div>
    <p
      v-else-if="tooShort"
      class="mt-4 min-h-6 text-sm text-ink-muted"
      data-testid="summary"
    >
      {{ t("search.tooShort", { min: MIN_TERM_LENGTH }) }}
    </p>

    <div
      v-if="!active && !tooShort"
      class="py-16 text-center"
      data-testid="prompt"
    >
      <p class="text-lg text-ink-muted">{{ t("search.prompt") }}</p>
      <div class="mt-6 flex flex-wrap justify-center gap-3">
        <RouterLink
          :to="localePath('artists.index')"
          class="border border-ink px-5 py-2 text-sm font-semibold text-ink hover:bg-ink hover:text-paper"
          >{{ t("search.empty.browseArtists") }}</RouterLink
        >
        <RouterLink
          :to="localePath('archive.records')"
          class="border border-ink px-5 py-2 text-sm font-semibold text-ink hover:bg-ink hover:text-paper"
          >{{ t("search.empty.browseArchive") }}</RouterLink
        >
      </div>
    </div>

    <div
      v-else-if="nothingFound"
      class="py-16 text-center"
      data-testid="no-results"
    >
      <h2 class="text-2xl font-semibold text-ink">
        {{ t("search.empty.title") }}
      </h2>
      <p class="mx-auto mt-3 max-w-xl text-pretty text-ink-muted">
        {{ t("search.empty.body") }}
      </p>
      <div class="mt-6 flex flex-wrap justify-center gap-3">
        <RouterLink
          :to="localePath('artists.index')"
          class="border border-ink px-5 py-2 text-sm font-semibold text-ink hover:bg-ink hover:text-paper"
          >{{ t("search.empty.browseArtists") }}</RouterLink
        >
        <RouterLink
          :to="localePath('archive.records')"
          class="border border-ink px-5 py-2 text-sm font-semibold text-ink hover:bg-ink hover:text-paper"
          >{{ t("search.empty.browseArchive") }}</RouterLink
        >
      </div>
    </div>

    <div v-else-if="active" class="mt-6 grid gap-10 lg:grid-cols-[13rem_1fr]">
      <SearchFacetSidebar
        :types="query.types"
        :totals="totals"
        :item-types="query.itemTypes"
        :archive-facets="archiveFacets"
        :verified-only="query.verifiedOnly"
        @toggle-type="toggleType"
        @toggle-item-type="toggleItemType"
        @update:verified-only="setVerifiedOnly"
        @clear="clearFilters"
      />

      <div class="min-w-0 space-y-14">
        <Spinner
          v-if="anyLoading && totalResults === 0"
          class="mx-auto my-8 block"
        />

        <SearchBestMatch
          v-if="shows('artists')"
          :candidates="sections.artists.items"
          :term="query.q"
        />

        <section
          v-if="
            shows('artists') &&
            (sections.artists.error || sections.artists.total > 0)
          "
          data-testid="section-artists"
        >
          <SectionHeader
            :title="t('search.types.artists')"
            :count="sections.artists.total"
            :to="
              sections.artists.total > sections.artists.items.length
                ? viewAll('artists.index')
                : undefined
            "
          />
          <ErrorState
            v-if="sections.artists.error"
            :error="sections.artists.error"
            @retry="retry('artists')"
          />
          <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li
              v-for="a in sections.artists.items"
              :key="a.id"
              data-testid="result-artist"
            >
              <SearchArtistResultCard :artist="a" />
            </li>
          </ul>
        </section>

        <section
          v-if="
            shows('archive') &&
            (sections.archive.error || sections.archive.total > 0)
          "
          data-testid="section-archive"
        >
          <SectionHeader
            :title="t('search.types.materials')"
            :count="sections.archive.total"
            :to="
              sections.archive.total > sections.archive.items.length
                ? viewAll('archive.records')
                : undefined
            "
          />
          <ErrorState
            v-if="sections.archive.error"
            :error="sections.archive.error"
            @retry="retry('archive')"
          />
          <div v-else>
            <SearchArchiveResultRow
              v-for="i in sections.archive.items"
              :key="i.id"
              :item="i"
              :highlight="query.q"
            />
          </div>
        </section>

        <section
          v-if="
            shows('artworks') &&
            (sections.artworks.error || sections.artworks.total > 0)
          "
          data-testid="section-artworks"
        >
          <SectionHeader
            :title="t('search.types.artworks')"
            :count="sections.artworks.total"
            :to="
              sections.artworks.total > sections.artworks.items.length
                ? viewAll('artworks.index')
                : undefined
            "
          />
          <ErrorState
            v-if="sections.artworks.error"
            :error="sections.artworks.error"
            @retry="retry('artworks')"
          />
          <ul v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <li
              v-for="w in sections.artworks.items"
              :key="w.id"
              data-testid="result-artwork"
            >
              <ArtworkCard :artwork="w" />
            </li>
          </ul>
        </section>

        <section
          v-if="
            shows('events') &&
            (sections.events.error || sections.events.total > 0)
          "
          data-testid="section-events"
        >
          <SectionHeader
            :title="t('search.types.events')"
            :count="sections.events.total"
          />
          <ErrorState
            v-if="sections.events.error"
            :error="sections.events.error"
            @retry="retry('events')"
          />
          <ul v-else class="divide-y divide-line border-y border-line">
            <li
              v-for="e in sections.events.items"
              :key="e.id"
              class="flex flex-wrap items-baseline justify-between gap-3 py-4"
              data-testid="result-event"
            >
              <div>
                <span
                  class="me-2 bg-neutral-soft px-2 py-0.5 text-xs font-medium text-ink-muted"
                  >{{ t(`events.types.${e.event_type}`) }}</span
                >
                <RouterLink
                  :to="eventLink(e.id)"
                  class="text-lg font-semibold text-ink hover:underline"
                  ><LocalizedText :text="e.title"
                /></RouterLink>
                <p v-if="e.venue_name || e.city" class="text-sm text-ink-muted">
                  {{ [e.venue_name, e.city].filter(Boolean).join("، ") }}
                </p>
              </div>
              <span v-if="e.start" class="text-sm tabular-nums text-ink-muted"
                ><PartialDateDisplay :date="e.start"
              /></span>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </section>
</template>
