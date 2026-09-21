<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import AlsoKnownAs from "@/components/artists/AlsoKnownAs.vue";
import ArtistHeader from "@/components/artists/ArtistHeader.vue";
import ArtworkCard from "@/components/artworks/ArtworkCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import NotFoundPage from "@/pages/NotFoundPage.vue";
import Tabs from "@/components/common/Tabs.vue";
import { useAuthStore } from "@/stores/auth";
import { useLocalePath } from "@/composables/useLocalePath";
import PublicEventsList from "@/components/events/PublicEventsList.vue";
import { useArtist } from "@/composables/useArtist";
import { useArtistArtworks } from "@/composables/useArtistArtworks";
import { useDocumentTitle } from "@/composables/useDocumentTitle";
import { useLocalized } from "@/composables/useLocalized";
import { ApiError } from "@/types/api";

const route = useRoute();
const { t } = useI18n();
const auth = useAuthStore();
const { localePath } = useLocalePath();
const { pick } = useLocalized();

const slug = computed(() => String(route.params.slug ?? ""));
const { artist, loading, error, retry } = useArtist(slug);

const notFound = computed(
  () => error.value instanceof ApiError && error.value.kind === "not_found",
);

const pickedName = computed(() =>
  artist.value ? pick(artist.value.name) : null,
);
useDocumentTitle(computed(() => pickedName.value?.text ?? null));

const pickedBio = computed(() =>
  artist.value ? pick(artist.value.bio) : null,
);

function setMetaDescription(content: string): void {
  let element = document.querySelector('meta[name="description"]');
  if (!element) {
    element = document.createElement("meta");
    element.setAttribute("name", "description");
    document.head.append(element);
  }
  element.setAttribute("content", content);
}

watch(
  pickedBio,
  (bio) => {
    setMetaDescription(bio ? bio.text.slice(0, 160) : "");
  },
  { immediate: true },
);

// Tab structure is ready for F3/F7 (Archive / History).
const activeTab = ref("overview");
const artistId = computed(() => artist.value?.id ?? 0);
const {
  items: artworks,
  loading: artworksLoading,
  error: artworksError,
  retry: retryArtworks,
  count: artworksCount,
} = useArtistArtworks(artistId);
const tabs = computed(() => [
  { key: "overview", label: t("artists.overview") },
  {
    key: "artworks",
    label: `${t("artists.tabArtworks")} (${artworksCount.value})`,
  },
  {
    key: "events",
    label: `${t("artists.tabEvents")} (${artist.value?.events?.length ?? 0})`,
  },
]);
</script>

<template>
  <NotFoundPage v-if="notFound" />
  <ErrorState v-else-if="error" :error="error" @retry="retry" />

  <div
    v-else-if="loading && !artist"
    class="animate-pulse"
    aria-hidden="true"
  >
    <div class="h-8 w-1/2 rounded bg-neutral-soft" />
    <div class="mt-3 h-4 w-1/3 rounded bg-neutral-soft" />
    <div class="mt-8 h-4 w-2/3 rounded bg-neutral-soft" />
  </div>

  <article v-else-if="artist">
    <ArtistHeader :artist="artist" />

    <RouterLink
      v-if="auth.can('proposals.submit')"
      :to="localePath('suggest', { type: 'artists', id: artist.id })"
      class="mt-4 inline-block rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft"
      data-testid="suggest-edit"
    >
      {{ $t("proposals.suggestEdit") }}
    </RouterLink>

    <Tabs v-model:active-key="activeTab" :tabs="tabs" class="mt-8">
      <template #overview>
        <AlsoKnownAs
          v-if="artist.also_known_as.length > 0"
          :variants="artist.also_known_as"
        />

        <section :class="artist.also_known_as.length > 0 ? 'mt-8' : ''">
          <h2 class="text-lg font-semibold text-ink">
            {{ t("artists.biography") }}
          </h2>
          <p v-if="pickedBio" class="mt-2 leading-relaxed text-ink">
            <LocalizedText :text="artist.bio" />
          </p>
          <p v-else class="mt-2 text-ink-muted">{{ t("artists.noBio") }}</p>
        </section>
      </template>

      <template #events>
        <EmptyState v-if="!artist.events || artist.events.length === 0" :title="t('artists.noEvents')" />
        <PublicEventsList v-else :events="artist.events" />
      </template>

      <template #artworks>
        <ErrorState
          v-if="artworksError"
          :error="artworksError"
          @retry="retryArtworks"
        />

        <div
          v-else-if="artworksLoading && artworks.length === 0"
          class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
          aria-hidden="true"
        >
          <div
            v-for="n in 3"
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
          v-else-if="artworks.length === 0"
          :title="t('artworks.noneForArtist')"
        />

        <div
          v-else
          class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
          <ArtworkCard
            v-for="artwork in artworks"
            :key="artwork.id"
            :artwork="artwork"
          />
        </div>
      </template>
    </Tabs>
  </article>
</template>
