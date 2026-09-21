<script setup lang="ts">
import { computed, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import ArtistArchiveGrid from "@/components/artists/ArtistArchiveGrid.vue";
import ArtistCareer from "@/components/artists/ArtistCareer.vue";
import ArtistHero from "@/components/artists/ArtistHero.vue";
import ArtistRecordSidebar from "@/components/artists/ArtistRecordSidebar.vue";
import ArtworkCard from "@/components/artworks/ArtworkCard.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import PublicEventsList from "@/components/events/PublicEventsList.vue";
import { useArtist } from "@/composables/useArtist";
import { useArtistArchive } from "@/composables/useArtistArchive";
import { useArtistArtworks } from "@/composables/useArtistArtworks";
import { useDocumentTitle } from "@/composables/useDocumentTitle";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import NotFoundPage from "@/pages/NotFoundPage.vue";
import { ApiError } from "@/types/api";

const route = useRoute();
const { t } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();

const slug = computed(() => String(route.params.slug ?? ""));
const { artist, loading, error, retry } = useArtist(slug);

const notFound = computed(() => error.value instanceof ApiError && error.value.kind === "not_found");

const pickedName = computed(() => (artist.value ? pick(artist.value.name) : null));
useDocumentTitle(computed(() => pickedName.value?.text ?? null));

const pickedBio = computed(() => (artist.value ? pick(artist.value.bio) : null));

function setMetaDescription(content: string): void {
  let element = document.querySelector('meta[name="description"]');
  if (!element) {
    element = document.createElement("meta");
    element.setAttribute("name", "description");
    document.head.append(element);
  }
  element.setAttribute("content", content);
}
watch(pickedBio, (bio) => setMetaDescription(bio ? bio.text.slice(0, 160) : ""), { immediate: true });

const artistId = computed(() => artist.value?.id ?? 0);
const { items: artworks, loading: artworksLoading, error: artworksError, retry: retryArtworks, count: artworksCount } = useArtistArtworks(artistId);
const { items: archiveItems, chronological, total: archiveTotal, error: archiveError, retry: retryArchive } = useArtistArchive(artistId);

const gridItems = computed(() => archiveItems.value.slice(0, 6));
const viewAll = computed(() => localePath("archive.records", {}, { artist_id: String(artistId.value) }));
</script>

<template>
  <NotFoundPage v-if="notFound" />
  <ErrorState v-else-if="error" :error="error" @retry="retry" />

  <div v-else-if="loading && !artist" class="animate-pulse" aria-hidden="true">
    <div class="h-8 w-1/2 rounded bg-neutral-soft" />
    <div class="mt-3 h-4 w-1/3 rounded bg-neutral-soft" />
    <div class="mt-8 h-4 w-2/3 rounded bg-neutral-soft" />
  </div>

  <article v-else-if="artist">
    <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
      <RouterLink :to="localePath('artists.index')" class="hover:text-ink">{{ t("artists.title") }}</RouterLink>
      <span aria-hidden="true"> &rsaquo; </span>
      <span class="text-ink">{{ pickedName?.text }}</span>
    </nav>

    <ArtistHero class="mt-6" :artist="artist" :archive-count="archiveTotal" :works-count="artworksCount" />

    <div class="mt-10 grid gap-12 border-t-2 border-ink pt-10 lg:grid-cols-[minmax(0,1fr)_20rem]" data-testid="artist-body">
      <div class="space-y-14 lg:order-1">
        <section data-testid="career-section">
          <h2 class="border-b-2 border-ink pb-3 text-2xl font-semibold text-ink">{{ t("artists.career.title") }}</h2>
          <ErrorState v-if="archiveError" :error="archiveError" @retry="retryArchive" />
          <p v-else-if="chronological.length === 0" class="py-6 text-ink-muted">{{ t("artists.career.empty") }}</p>
          <ArtistCareer v-else :items="chronological" />
        </section>

        <section v-if="gridItems.length > 0" data-testid="archive-section">
          <div class="flex items-baseline justify-between border-b-2 border-ink pb-3">
            <h2 class="text-2xl font-semibold text-ink">{{ t("artists.archive.title") }}</h2>
            <RouterLink v-if="archiveTotal > gridItems.length" :to="viewAll" class="text-xs text-accent-strong hover:underline" data-testid="view-all">{{ t("artists.archive.viewAll") }}</RouterLink>
          </div>
          <ArtistArchiveGrid class="mt-6" :items="gridItems" />
        </section>

        <section data-testid="artworks-section">
          <h2 class="border-b-2 border-ink pb-3 text-2xl font-semibold text-ink">{{ t("artists.tabArtworks") }} <span class="text-base font-normal tabular-nums text-ink-muted">({{ artworksCount }})</span></h2>
          <ErrorState v-if="artworksError" :error="artworksError" @retry="retryArtworks" />
          <p v-else-if="!artworksLoading && artworks.length === 0" class="py-6 text-ink-muted">{{ t("artworks.noneForArtist") }}</p>
          <div v-else class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <ArtworkCard v-for="artwork in artworks" :key="artwork.id" :artwork="artwork" />
          </div>
        </section>

        <section data-testid="events-section">
          <h2 class="border-b-2 border-ink pb-3 text-2xl font-semibold text-ink">{{ t("artists.tabEvents") }} <span class="text-base font-normal tabular-nums text-ink-muted">({{ artist.events?.length ?? 0 }})</span></h2>
          <p v-if="!artist.events || artist.events.length === 0" class="py-6 text-ink-muted">{{ t("artists.noEvents") }}</p>
          <PublicEventsList v-else class="mt-4" :events="artist.events" />
        </section>
      </div>

      <ArtistRecordSidebar :artist="artist" class="lg:order-2" />
    </div>
  </article>
</template>
