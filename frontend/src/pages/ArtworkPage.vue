<script setup lang="ts">
import { computed, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import ArtworkHeader from "@/components/artworks/ArtworkHeader.vue";
import DimensionsDisplay from "@/components/artworks/DimensionsDisplay.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import PublicEventsList from "@/components/events/PublicEventsList.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import PartialDateDisplay from "@/components/common/PartialDateDisplay.vue";
import NotFoundPage from "@/pages/NotFoundPage.vue";
import { useArtwork } from "@/composables/useArtwork";
import { useDocumentTitle } from "@/composables/useDocumentTitle";
import { useLocalized } from "@/composables/useLocalized";
import { ApiError } from "@/types/api";

const route = useRoute();
const { t } = useI18n();
const { pick } = useLocalized();

const id = computed(() => Number.parseInt(String(route.params.id ?? ""), 10));
const { artwork, loading, error, retry } = useArtwork(id);

const notFound = computed(
  () => error.value instanceof ApiError && error.value.kind === "not_found",
);

const pickedTitle = computed(() => {
  if (!artwork.value) return null;
  return artwork.value.is_untitled ? null : pick(artwork.value.title);
});

useDocumentTitle(
  computed(() => pickedTitle.value?.text ?? t("artworks.untitled")),
);

const signedLabel = computed(() => {
  switch (artwork.value?.signed) {
    case "signed":
      return t("artworks.signed");
    case "unsigned":
      return t("artworks.unsigned");
    default:
      return t("artworks.signedUnknown");
  }
});

const editionLabel = computed(() => {
  const edition = artwork.value?.edition;
  if (!edition) return null;
  const parts: string[] = [];
  if (edition.number) parts.push(edition.number);
  if (edition.size !== null) parts.push(`/${edition.size}`);
  return parts.length > 0 ? parts.join("") : null;
});

const holderLine = computed(() => {
  const holder = artwork.value?.holder;
  if (!holder) return null;
  const name = pick(holder.display_name);
  if (!name) return null;
  const city = pick(holder.city);
  return city ? `${name.text} · ${city.text}` : name.text;
});

const pickedNotes = computed(() =>
  artwork.value ? pick(artwork.value.notes) : null,
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
  pickedNotes,
  (notes) => {
    setMetaDescription(notes ? notes.text.slice(0, 160) : "");
  },
  { immediate: true },
);
</script>

<template>
  <NotFoundPage v-if="notFound" />
  <ErrorState v-else-if="error" :error="error" @retry="retry" />

  <div
    v-else-if="loading && !artwork"
    class="animate-pulse"
    aria-hidden="true"
  >
    <div class="h-8 w-1/2 rounded bg-neutral-soft" />
    <div class="mt-3 h-4 w-1/3 rounded bg-neutral-soft" />
    <div class="mt-8 grid gap-6 sm:grid-cols-2">
      <div v-for="n in 4" :key="n" class="h-4 w-2/3 rounded bg-neutral-soft" />
    </div>
  </div>

  <article v-else-if="artwork">
    <ArtworkHeader :artwork="artwork" />

    <dl
      class="mt-8 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2"
    >
      <div v-if="artwork.medium.ar?.trim() || artwork.medium.en?.trim()">
        <dt class="font-medium text-ink-muted">{{ t("artworks.medium") }}</dt>
        <dd class="mt-1 text-ink"><LocalizedText :text="artwork.medium" /></dd>
      </div>

      <div v-if="artwork.creation">
        <dt class="sr-only">{{ t("artworks.creation") }}</dt>
        <dd class="text-ink">
          <PartialDateDisplay :date="artwork.creation" />
        </dd>
      </div>

      <div>
        <dt class="font-medium text-ink-muted">
          {{ t("artworks.dimensions") }}
        </dt>
        <dd class="mt-1 text-ink">
          <DimensionsDisplay :dimensions="artwork.dimensions" />
        </dd>
      </div>

      <div v-if="artwork.frame_dimensions">
        <dt class="font-medium text-ink-muted">{{ t("artworks.frame") }}</dt>
        <dd class="mt-1 text-ink">
          <DimensionsDisplay :dimensions="artwork.frame_dimensions" frame />
        </dd>
      </div>

      <div v-if="editionLabel">
        <dt class="font-medium text-ink-muted">{{ t("artworks.edition") }}</dt>
        <dd class="mt-1 text-ink">{{ editionLabel }}</dd>
      </div>

      <div>
        <dt class="font-medium text-ink-muted">{{ t("artworks.signed") }}</dt>
        <dd class="mt-1 text-ink">{{ signedLabel }}</dd>
      </div>

      <div v-if="holderLine">
        <dt class="font-medium text-ink-muted">{{ t("artworks.holder") }}</dt>
        <dd class="mt-1 text-ink">{{ holderLine }}</dd>
      </div>
    </dl>

    <section v-if="pickedNotes" class="mt-8">
      <h2 class="text-lg font-semibold text-ink">
        {{ t("artworks.notes") }}
      </h2>
      <p class="mt-2 leading-relaxed text-ink">
        <LocalizedText :text="artwork.notes" />
      </p>
    </section>

    <section v-if="artwork.events && artwork.events.length > 0" class="mt-10">
      <h2 class="text-lg font-semibold text-ink">{{ t("artworks.events") }}</h2>
      <PublicEventsList class="mt-2" :events="artwork.events" />
    </section>
  </article>
</template>
