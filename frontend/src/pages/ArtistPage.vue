<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import AlsoKnownAs from "@/components/artists/AlsoKnownAs.vue";
import ArtistHeader from "@/components/artists/ArtistHeader.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import NotFoundPage from "@/pages/NotFoundPage.vue";
import Tabs from "@/components/common/Tabs.vue";
import { useArtist } from "@/composables/useArtist";
import { useDocumentTitle } from "@/composables/useDocumentTitle";
import { useLocalized } from "@/composables/useLocalized";
import { ApiError } from "@/types/api";

const route = useRoute();
const { t } = useI18n();
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

// Tab structure is ready for F2/F3/F7 (Artworks / Archive / History).
const activeTab = ref("overview");
const tabs = computed(() => [
  { key: "overview", label: t("artists.overview") },
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
    </Tabs>
  </article>
</template>
