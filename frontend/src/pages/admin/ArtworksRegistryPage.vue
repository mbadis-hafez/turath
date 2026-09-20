<script setup lang="ts">
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";

import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import ArtworkMergeModal from "@/components/curation/ArtworkMergeModal.vue";
import EntityPicker, { type PickerOption } from "@/components/curation/EntityPicker.vue";
import { searchArtistOptions, searchHolderOptions } from "@/components/curation/ArtworkPickers";
import { useAdminArtworks } from "@/composables/useAdminArtworks";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type { ArtworkFlag, ArtworkStatus } from "@/types/artworkCuration";

const { t } = useI18n();
const { pick } = useLocalized();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("artworks.manage"));

const {
  items, meta, loading, error, query, searchInput, retry,
  setSearch, setArtist, setHolder, setStatus, setMissingDimensions, setPipelineGap, setPage, clear,
} = useAdminArtworks();

const merging = ref(false);
const labels = ref<Record<string, string>>({});

function pickerModel(kind: "artist" | "holder") {
  return computed<PickerOption | null>({
    get: () => {
      const id = kind === "artist" ? query.value.artistId : query.value.holderId;
      return id ? { id, label: labels.value[`${kind}${id}`] ?? `#${id}` } : null;
    },
    set: (v) => {
      if (v) labels.value[`${kind}${v.id}`] = v.label;
      (kind === "artist" ? setArtist : setHolder)(v?.id ?? null);
    },
  });
}
const artistFilter = pickerModel("artist");
const holderFilter = pickerModel("holder");

function onMerged(): void {
  merging.value = false;
  void retry();
}

const STATUSES: ArtworkStatus[] = ["draft", "published", "hidden"];
const STATUS_CLASS: Record<ArtworkStatus, string> = {
  draft: "bg-warn-soft text-warn",
  published: "bg-success-soft text-success",
  hidden: "bg-neutral-soft text-ink-muted",
};
const FLAG_CLASS: Record<ArtworkFlag, string> = {
  untitled: "bg-danger-soft text-danger",
  missing_dimensions: "bg-danger-soft text-danger",
  holder_missing: "bg-danger-soft text-danger",
  year_uncertain: "bg-danger-soft text-danger",
};

const hasFilters = computed(() => Object.values(query.value).some((v) => v && v !== 1));
const toggle = (on: boolean) =>
  on ? "border-danger bg-danger-soft text-danger" : "border-line bg-surface text-ink";
const field = "rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("curation.artworkRegistry.title") }}</h1>
          <p v-if="meta" class="mt-1 text-sm text-ink-muted">
            {{ t("curation.artworkRegistry.subtitle", { count: meta.total }) }}
            <template v-if="meta.candidate_count > 0"> · {{ t("curation.artworkRegistry.candidates", { count: meta.candidate_count }) }}</template>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft" data-testid="merge-open" @click="merging = true">{{ t("curation.artworkRegistry.merge") }}</button>
          <RouterLink :to="localePath('admin.artworks.new')" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper hover:bg-ink/85" data-testid="add-artwork">{{ t("curation.artworkRegistry.add") }}</RouterLink>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-2">
        <input
          :value="searchInput"
          type="search"
          :placeholder="t('curation.artworkRegistry.searchPlaceholder')"
          :class="[field, 'min-w-64 flex-1']"
          @input="setSearch(($event.target as HTMLInputElement).value)"
        />
        <select :value="query.status" :class="field" :aria-label="t('curation.artworkRegistry.status')" @change="setStatus(($event.target as HTMLSelectElement).value as ArtworkStatus | '')">
          <option value="">{{ t("curation.artworkRegistry.allStatuses") }}</option>
          <option v-for="s in STATUSES" :key="s" :value="s">{{ t(`curation.artworkRegistry.statuses.${s}`) }}</option>
        </select>
        <button type="button" class="rounded-md border px-3 py-2 text-sm font-medium" :class="toggle(query.missingDimensions)" :aria-pressed="query.missingDimensions" @click="setMissingDimensions(!query.missingDimensions)">
          {{ t("curation.artworkRegistry.missingDimensions") }}
        </button>
        <div class="w-48"><EntityPicker v-model="artistFilter" :search="searchArtistOptions" :placeholder="t('curation.artworkRegistry.artist')" /></div>
        <div class="w-48"><EntityPicker v-model="holderFilter" :search="searchHolderOptions" :placeholder="t('curation.artworkRegistry.holder')" /></div>
        <button type="button" class="rounded-md border px-3 py-2 text-sm font-medium" :class="toggle(query.pipelineGap)" :aria-pressed="query.pipelineGap" @click="setPipelineGap(!query.pipelineGap)">
          {{ t("curation.artworkRegistry.pipelineGap") }}
        </button>
      </div>

      <div class="mt-6">
        <ErrorState v-if="error" :error="error" @retry="retry" />
        <Spinner v-else-if="loading && items.length === 0" class="mx-auto my-12 block" />
        <EmptyState v-else-if="items.length === 0" :title="t('curation.artworkRegistry.empty')" :description="t('curation.artworkRegistry.emptyHelp')">
          <button v-if="hasFilters" type="button" class="mt-4 rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong" @click="clear">
            {{ t("curation.registry.clear") }}
          </button>
        </EmptyState>
        <template v-else>
          <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <li v-for="a in items" :key="a.id" data-testid="artwork-card">
              <div class="relative aspect-[4/3] overflow-hidden bg-neutral-soft">
                <img v-if="a.thumbnail_url" :src="a.thumbnail_url" alt="" loading="lazy" class="size-full object-cover" />
                <span class="absolute end-3 top-3 flex flex-wrap gap-1">
                  <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="STATUS_CLASS[a.publication_status]">{{ t(`curation.artworkRegistry.statuses.${a.publication_status}`) }}</span>
                  <span v-for="f in a.flags" :key="f" class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="FLAG_CLASS[f]">{{ t(`curation.artworkRegistry.flags.${f}`) }}</span>
                </span>
              </div>
              <h2 class="mt-4 text-xl font-semibold text-ink"><RouterLink :to="localePath('admin.artworks.show', { id: a.id })" class="hover:underline"><LocalizedText :text="a.title" /></RouterLink></h2>
              <p class="text-sm text-ink-muted">{{ pick({ ar: a.title.en, en: a.title.ar })?.text }}</p>
              <p v-if="a.artist" class="mt-2 text-sm font-medium text-ink"><LocalizedText :text="a.artist.name" /></p>
              <p class="mt-1 text-xs text-ink-muted">
                <template v-if="a.year">{{ a.year }}</template>
                <template v-if="a.year && a.holder"> · </template>
                <LocalizedText v-if="a.holder" :text="a.holder.name" />
              </p>
              <p class="mt-3 flex items-center justify-between border-t border-line pt-2 text-xs tabular-nums text-ink-muted">
                <span>{{ t("curation.artworkRegistry.pipeline", { cleared: a.pipeline.cleared, total: a.pipeline.total }) }}</span>
                <span>{{ a.completeness_pct }}%</span>
              </p>
            </li>
          </ul>
          <Pagination class="mt-6" :meta="meta" @change="setPage" />
        </template>
      </div>
      <ArtworkMergeModal v-if="merging" @close="merging = false" @merged="onMerged" />
    </template>
  </section>
</template>
