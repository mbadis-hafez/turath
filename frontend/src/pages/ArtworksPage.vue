<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import ArtworkCard from "@/components/artworks/ArtworkCard.vue";
import ArtworkFilterSidebar from "@/components/artworks/ArtworkFilterSidebar.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useArtworksList } from "@/composables/useArtworksList";
import { formatNumber } from "@/utils/format";
import type { AppLocale } from "@/i18n";
import type { ArtworkSort } from "@/types/artwork";

const { t, locale } = useI18n();

const {
  items,
  meta,
  loading,
  error,
  retry,
  query,
  searchInput,
  setSearch,
  setCategory,
  setArtist,
  setYearFrom,
  setYearTo,
  setSort,
  setPage,
  clearFilters,
} = useArtworksList();

const appLocale = computed(() => locale.value as AppLocale);
const total = computed(() => meta.value?.total ?? 0);
const summary = computed(() => t("artworks.summary", { count: formatNumber(total.value, appLocale.value), n: total.value }));

const SORTS: ArtworkSort[] = ["-created_at", "created_at", "title_ar", "title_en", "creation_year_from", "-creation_year_from"];

function onPageChange(page: number): void {
  setPage(page);
  requestAnimationFrame(() => {
    document.getElementById("artworks-results")?.scrollIntoView?.({ block: "start" });
  });
}
</script>

<template>
  <section>
    <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">{{ t("artworks.title") }}</nav>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 border-b-2 border-ink pb-6">
      <div class="max-w-[64ch]">
        <h1 class="text-balance font-display text-5xl font-semibold tracking-tight text-ink">{{ t("artworks.title") }}</h1>
        <p class="mt-3 text-base leading-relaxed text-ink-muted">{{ t("artworks.intro") }}</p>
      </div>
      <p class="font-latin text-sm text-ink-muted tabular-nums" data-testid="summary">{{ summary }}</p>
    </div>

    <div class="mt-8 grid gap-10 lg:grid-cols-[16rem_minmax(0,1fr)]">
      <div>
        <input :value="searchInput" type="search" :placeholder="t('artworks.searchPlaceholder')" class="mb-6 w-full border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-ink focus:outline-none" data-testid="search" @input="setSearch(($event.target as HTMLInputElement).value)" />
        <ArtworkFilterSidebar
          :category="query.category"
          :artist="query.artist"
          :year-from="query.yearFrom"
          :year-to="query.yearTo"
          @category="setCategory"
          @artist="setArtist"
          @year-from="setYearFrom"
          @year-to="setYearTo"
          @clear="clearFilters"
        />
      </div>

      <div id="artworks-results">
        <div class="flex justify-end pb-4">
          <label class="flex items-center gap-2 text-sm text-ink-muted">
            <span>{{ t("artworks.browse.sort") }}</span>
            <select :value="query.sort" class="border border-line bg-surface px-2 py-1.5 text-sm text-ink focus:border-ink focus:outline-none" data-testid="sort" @change="setSort(($event.target as HTMLSelectElement).value as ArtworkSort)">
              <option v-for="s in SORTS" :key="s" :value="s">{{ t(`artworks.browse.sortOptions.${s}`) }}</option>
            </select>
          </label>
        </div>

        <ErrorState v-if="error" :error="error" @retry="retry" />
        <Spinner v-else-if="loading && items.length === 0" class="mx-auto my-12 block" />
        <EmptyState v-else-if="items.length === 0" :title="t('artworks.noResults')">
          <button type="button" class="mt-4 bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong" @click="clearFilters">{{ t("artworks.browse.clear") }}</button>
        </EmptyState>

        <template v-else>
          <div class="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3" data-testid="results">
            <ArtworkCard v-for="artwork in items" :key="artwork.id" :artwork="artwork" />
          </div>
          <Pagination v-if="meta" class="mt-12" :meta="meta" @change="onPageChange" />
        </template>
      </div>
    </div>
  </section>
</template>
