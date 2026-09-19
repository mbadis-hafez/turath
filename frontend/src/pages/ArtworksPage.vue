<script setup lang="ts">
import { useI18n } from "vue-i18n";

import ArtworkCard from "@/components/artworks/ArtworkCard.vue";
import ArtworkFilterBar from "@/components/artworks/ArtworkFilterBar.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Pagination from "@/components/common/Pagination.vue";
import { useArtworksList } from "@/composables/useArtworksList";

const { t } = useI18n();

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
  setPage,
  clearFilters,
} = useArtworksList();

function onPageChange(page: number): void {
  setPage(page);
  requestAnimationFrame(() => {
    document
      .getElementById("artworks-results")
      ?.scrollIntoView?.({ block: "start" });
  });
}
</script>

<template>
  <section>
    <h1 class="text-3xl font-semibold tracking-tight text-ink">
      {{ t("artworks.title") }}
    </h1>

    <ArtworkFilterBar
      class="mt-6"
      :search="searchInput"
      :category="query.category"
      :artist="query.artist"
      :year-from="query.yearFrom"
      :year-to="query.yearTo"
      @update:search="setSearch"
      @update:category="setCategory"
      @update:artist="setArtist"
      @update:year-from="setYearFrom"
      @update:year-to="setYearTo"
    />

    <div id="artworks-results" class="mt-8">
      <ErrorState v-if="error" :error="error" @retry="retry" />

      <template v-else>
        <div
          v-if="loading && items.length === 0"
          class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
          aria-hidden="true"
        >
          <div
            v-for="n in 6"
            :key="n"
            class="animate-pulse overflow-hidden rounded-lg border border-line bg-surface"
          >
            <div class="aspect-[4/3] bg-neutral-soft" />
            <div class="p-4">
              <div class="h-5 w-2/3 rounded bg-neutral-soft" />
              <div class="mt-2 h-4 w-1/3 rounded bg-neutral-soft" />
            </div>
          </div>
        </div>

        <EmptyState
          v-else-if="items.length === 0"
          :title="t('artworks.noResults')"
        >
          <button
            type="button"
            class="mt-4 rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong"
            @click="clearFilters"
          >
            {{ t("artists.clearFilters") }}
          </button>
        </EmptyState>

        <template v-else>
          <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
          >
            <ArtworkCard v-for="artwork in items" :key="artwork.id" :artwork="artwork" />
          </div>
          <Pagination
            v-if="meta"
            class="mt-8"
            :meta="meta"
            @change="onPageChange"
          />
        </template>
      </template>
    </div>
  </section>
</template>
