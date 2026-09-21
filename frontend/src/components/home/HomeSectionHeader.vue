<script setup lang="ts">
import type { RouteLocationRaw } from "vue-router";

withDefaults(
  defineProps<{ title: string; linkLabel?: string; to?: RouteLocationRaw }>(),
  { linkLabel: "", to: undefined },
);
</script>

<template>
  <header
    class="mb-7 flex items-baseline justify-between gap-4 border-b-2 border-ink pb-3"
  >
    <h2 class="text-[26px] font-bold text-ink text-balance font-display">
      {{ title }}
    </h2>
    <RouterLink
      v-if="to && linkLabel"
      :to="to"
      class="shrink-0 text-[12.5px] text-accent underline-offset-4 hover:text-accent-strong hover:underline"
    >
      {{ linkLabel }}
      <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
    </RouterLink>
  </header>
</template>
