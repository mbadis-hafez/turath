<script setup lang="ts">
import { useI18n } from "vue-i18n";

import ArtistCard from "@/components/artists/ArtistCard.vue";
import ArtistSearchBar from "@/components/artists/ArtistSearchBar.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Pagination from "@/components/common/Pagination.vue";
import { useArtistsList } from "@/composables/useArtistsList";

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
  setSort,
  setVerifiedOnly,
  setPage,
  clearFilters,
} = useArtistsList();

function onPageChange(page: number): void {
  setPage(page);
  requestAnimationFrame(() => {
    document
      .getElementById("artists-results")
      ?.scrollIntoView?.({ block: "start" });
  });
}
</script>

<template>
  <section>
    <h1 class="text-3xl font-semibold tracking-tight text-ink">
      {{ t("artists.title") }}
    </h1>

    <ArtistSearchBar
      class="mt-6"
      :search="searchInput"
      :sort="query.sort"
      :verified-only="query.verifiedOnly"
      @update:search="setSearch"
      @update:sort="setSort"
      @update:verified-only="setVerifiedOnly"
    />

    <div id="artists-results" class="mt-8">
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
            class="animate-pulse rounded-lg border border-line bg-surface p-4"
          >
            <div class="h-5 w-2/3 rounded bg-neutral-soft" />
            <div class="mt-2 h-4 w-1/3 rounded bg-neutral-soft" />
          </div>
        </div>

        <EmptyState
          v-else-if="items.length === 0"
          :title="t('artists.noResults')"
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
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <ArtistCard v-for="artist in items" :key="artist.id" :artist="artist" />
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
