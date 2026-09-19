<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { formatNumber } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const props = defineProps<{
  artist: {
    nameAr: string;
    nameEn: string;
    years: string;
    city: string;
    bio: string;
    verified: boolean;
    works: number;
    materials: number;
    events: number;
  };
}>();

const { t, locale } = useI18n();

const fmt = (value: number) => formatNumber(value, locale.value as AppLocale);

const stats = computed(() => [
  {
    label: t("search.featured.works", {
      count: fmt(props.artist.works),
    }),
  },
  {
    label: t("search.featured.materials", {
      count: fmt(props.artist.materials),
    }),
  },
  {
    label: t("search.featured.events", {
      count: fmt(props.artist.events),
    }),
  },
]);
</script>

<template>
  <article class="flex flex-col gap-6 border-t-2 border-ink pt-6 sm:flex-row">
    <div
      class="aspect-[3/4] w-full shrink-0 bg-neutral-soft sm:w-52"
      aria-hidden="true"
    ></div>

    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-3">
        <h2 class="text-3xl font-semibold text-ink text-balance">
          {{ artist.nameAr }}
        </h2>
        <span
          v-if="artist.verified"
          class="inline-flex items-center gap-1 border border-ink px-2 py-0.5 text-xs text-ink"
        >
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
            class="size-3.5 text-accent"
          >
            <path d="M20 6 9 17l-5-5" />
          </svg>
          {{ t("search.featured.verified") }}
        </span>
      </div>
      <p class="mt-1 text-ink-muted">
        {{ artist.nameEn }} ·
        <span class="tabular-nums">{{ artist.years }}</span> ·
        {{ artist.city }}
      </p>
      <p class="mt-4 max-w-2xl leading-relaxed text-pretty text-ink">
        {{ artist.bio }}
      </p>

      <div
        class="mt-6 flex flex-wrap items-center gap-x-10 gap-y-3 border-t border-line pt-4 text-sm"
      >
        <p v-for="stat in stats" :key="stat.label" class="text-ink-muted">
          {{ stat.label }}
        </p>
        <a
          href="#"
          class="ms-auto text-accent underline-offset-4 hover:text-accent-strong hover:underline"
          @click.prevent
        >
          {{ t("search.featured.viewProfile") }}
          <span aria-hidden="true" class="inline-block rtl:-scale-x-100"
            >→</span
          >
        </a>
      </div>
    </div>
  </article>
</template>
