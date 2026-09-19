<script setup lang="ts">
import { useI18n } from "vue-i18n";

import type { DemoArtist } from "@/data/searchDemo";

defineProps<{ artist: DemoArtist }>();

const { t } = useI18n();
</script>

<template>
  <article>
    <div class="aspect-[3/4] bg-neutral-soft" aria-hidden="true"></div>
    <h3 class="mt-3 flex items-center gap-1.5 font-semibold text-ink">
      {{ artist.nameAr }}
      <svg
        v-if="artist.verified"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.2"
        stroke-linecap="round"
        stroke-linejoin="round"
        role="img"
        :aria-label="t('search.featured.verified')"
        class="size-4 shrink-0 text-accent"
      >
        <path d="M20 6 9 17l-5-5" />
      </svg>
    </h3>
    <p class="mt-0.5 text-sm text-ink-muted">{{ artist.nameEn }}</p>
    <p class="text-sm text-ink-muted tabular-nums">{{ artist.years }}</p>
  </article>
</template>
