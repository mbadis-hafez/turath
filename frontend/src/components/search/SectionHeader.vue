<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { formatNumber } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const props = defineProps<{ title: string; count?: number }>();

const { t, locale } = useI18n();

const formattedCount = computed(() =>
  props.count === undefined
    ? null
    : formatNumber(props.count, locale.value as AppLocale),
);
</script>

<template>
  <header
    class="mb-8 flex items-baseline justify-between gap-4 border-t-2 border-ink pt-4"
  >
    <h2 class="text-2xl font-semibold text-ink text-balance">
      {{ title }}
      <span
        v-if="formattedCount !== null"
        class="ms-1 text-lg font-normal text-ink-muted tabular-nums"
        >{{ formattedCount }}</span
      >
    </h2>
    <a
      href="#"
      class="shrink-0 text-sm text-accent underline-offset-4 hover:text-accent-strong hover:underline"
      @click.prevent
    >
      {{ t("search.viewAll") }}
      <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
    </a>
  </header>
</template>
