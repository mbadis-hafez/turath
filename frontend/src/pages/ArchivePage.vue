<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import ArchiveCard from "@/components/archive/ArchiveCard.vue";
import ArchiveFacetSidebar from "@/components/archive/ArchiveFacetSidebar.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useArchiveList } from "@/composables/useArchiveList";
import { useAuthStore } from "@/stores/auth";

const { t } = useI18n();
const auth = useAuthStore();

const {
  items, total, facets, loading, loadingMore, hasMore, error, query, searchInput, retry, loadMore, setSearch,
  toggleType, togglePlace, toggleTheme, toggleAccess, setView, setIncludeUnpublished, clearArtist, clearFilters,
} = useArchiveList();

const canManage = computed(() => auth.can("archive.manage"));
const hasFilters = computed(() => query.value.q !== "" || query.value.types.length > 0 || query.value.places.length > 0 || query.value.themeIds.length > 0 || query.value.access.length > 0 || query.value.artistId !== null);
const viewButton = (active: boolean) => `px-4 py-2 text-sm font-medium border ${active ? "border-ink bg-ink text-paper" : "border-line bg-surface text-ink hover:bg-neutral-soft"}`;
</script>

<template>
  <section>
    <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">{{ t("archive.title") }}</nav>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 border-b-2 border-ink pb-6">
      <div>
        <h1 class="text-balance font-display text-5xl font-semibold tracking-tight text-ink">{{ t("archive.browse.title") }}</h1>
        <p class="mt-3 text-lg text-ink-muted tabular-nums" data-testid="summary">{{ t("archive.browse.summary", { count: total }) }}</p>
      </div>
      <div class="flex" role="group" :aria-label="t('archive.browse.view')">
        <button type="button" :class="viewButton(query.view === 'grid')" :aria-pressed="query.view === 'grid'" data-testid="view-grid" @click="setView('grid')">{{ t("archive.browse.grid") }}</button>
        <button type="button" :class="viewButton(query.view === 'list')" :aria-pressed="query.view === 'list'" data-testid="view-list" @click="setView('list')">{{ t("archive.browse.list") }}</button>
      </div>
    </div>

    <div class="mt-8 grid gap-10 lg:grid-cols-[16rem_minmax(0,1fr)]">
      <div>
        <input :value="searchInput" type="search" :placeholder="t('archive.searchPlaceholder')" class="mb-6 w-full border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-ink focus:outline-none" data-testid="search" @input="setSearch(($event.target as HTMLInputElement).value)" />
        <ArchiveFacetSidebar
          :facets="facets"
          :query="query"
          :can-manage="canManage"
          @type="toggleType"
          @place="togglePlace"
          @theme="toggleTheme"
          @access="toggleAccess"
          @unpublished="setIncludeUnpublished"
          @clear="clearFilters"
        />
      </div>

      <div>
        <p v-if="query.artistId" class="mb-4 flex items-center gap-3 text-sm text-ink-muted" data-testid="artist-filter">
          {{ t("archive.artistFilter") }}
          <button type="button" class="text-accent-strong hover:underline" data-testid="clear-artist" @click="clearArtist">{{ t("archive.clearArtist") }}</button>
        </p>

        <ErrorState v-if="error" :error="error" @retry="retry" />
        <Spinner v-else-if="loading && items.length === 0" class="mx-auto my-12 block" />
        <EmptyState v-else-if="items.length === 0" :title="t('archive.empty')" :description="t('archive.emptyHelp')">
          <button v-if="hasFilters" type="button" class="mt-4 rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong" @click="clearFilters">{{ t("archive.clear") }}</button>
        </EmptyState>
        <template v-else>
          <div :class="query.view === 'grid' ? 'grid gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3' : ''" data-testid="results" :data-view="query.view">
            <ArchiveCard v-for="i in items" :key="i.id" :item="i" :layout="query.view" />
          </div>
          <div v-if="hasMore" class="mt-12 text-center">
            <button type="button" class="border border-ink px-10 py-3 text-lg font-semibold text-ink transition-colors hover:bg-ink hover:text-paper disabled:opacity-50" :disabled="loadingMore" data-testid="load-more" @click="loadMore">
              {{ loadingMore ? t("archive.browse.loading") : t("archive.browse.loadMore") }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </section>
</template>
