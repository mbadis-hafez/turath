<script setup lang="ts">
import { useI18n } from "vue-i18n";

const model = defineModel<string>({ required: true });

const { t } = useI18n();
</script>

<template>
  <form role="search" class="relative" @submit.prevent>
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.8"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
      class="pointer-events-none absolute start-0 top-1/2 size-5 -translate-y-1/2 text-ink-muted"
    >
      <circle cx="11" cy="11" r="8" />
      <path d="m21 21-4.3-4.3" />
    </svg>
    <input
      v-model="model"
      type="text"
      class="w-full border-0 border-b-2 border-ink bg-transparent py-4 ps-8 pe-8 text-2xl text-ink placeholder:text-ink-muted/60 focus:border-ink"
      :placeholder="t('search.placeholder')"
      :aria-label="t('search.searchLabel')"
    />
    <button
      v-if="model"
      type="button"
      class="absolute end-0 top-1/2 -translate-y-1/2 text-ink-muted transition-colors hover:text-ink"
      :aria-label="t('search.clear')"
      @click="model = ''"
    >
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        class="size-5"
      >
        <path d="M18 6 6 18M6 6l12 12" />
      </svg>
    </button>
  </form>
</template>
