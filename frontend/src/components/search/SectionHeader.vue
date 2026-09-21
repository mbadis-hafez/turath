<script setup lang="ts">
import { computed } from "vue";
import type { RouteLocationRaw } from "vue-router";
import { useI18n } from "vue-i18n";

import { formatNumber } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const props = defineProps<{ title: string; count?: number; to?: RouteLocationRaw }>();

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
    <RouterLink
      v-if="to"
      :to="to"
      class="shrink-0 text-sm text-accent underline-offset-4 hover:text-accent-strong hover:underline"
      data-testid="view-all"
    >
      {{ t("search.viewAll") }}
      <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
    </RouterLink>
  </header>
</template>
