<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import HighlightedText from "@/components/search/HighlightedText.vue";
import type { DemoMaterial, DemoMaterialKind } from "@/data/searchDemo";

const props = defineProps<{
  material: DemoMaterial;
  highlight: string;
}>();

const { t } = useI18n();

const kindLabel = computed(() => {
  const key = (
    {
      article: "articles",
      photo: "photos",
      catalog: "catalogs",
      av: "av",
    } satisfies Record<DemoMaterialKind, string>
  )[props.material.kind];
  return t(`search.materialTypes.${key}`);
});
</script>

<template>
  <article class="flex gap-4 py-5">
    <div
      class="flex size-14 shrink-0 items-center justify-center bg-neutral-soft text-ink-muted"
      aria-hidden="true"
    >
      <svg
        v-if="material.kind === 'article'"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="size-6"
      >
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <path d="M14 2v6h6M8 13h8M8 17h5" />
      </svg>
      <svg
        v-else-if="material.kind === 'photo'"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="size-6"
      >
        <rect width="18" height="18" x="3" y="3" rx="2" />
        <circle cx="9" cy="9" r="2" />
        <path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21" />
      </svg>
      <svg
        v-else-if="material.kind === 'catalog'"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="size-6"
      >
        <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20" />
      </svg>
      <svg
        v-else
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="size-6"
      >
        <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z" />
        <path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3" />
      </svg>
    </div>

    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="bg-ink px-2 py-0.5 font-medium text-paper">{{
          kindLabel
        }}</span>
        <span
          v-if="material.restricted"
          class="border border-dashed border-ink-muted px-2 py-0.5 text-ink-muted"
          >{{ t("search.restricted") }}</span
        >
        <span class="ms-auto text-ink-muted tabular-nums">{{
          material.date
        }}</span>
      </div>
      <h3 class="mt-2 text-lg font-semibold text-ink">
        <HighlightedText :text="material.title" :term="highlight" />
      </h3>
      <p class="mt-1 leading-relaxed text-pretty text-ink-muted">
        <HighlightedText :text="material.snippet" :term="highlight" />
      </p>
      <p class="mt-1 text-xs text-ink-muted">{{ material.meta }}</p>
    </div>
  </article>
</template>
