<script setup lang="ts">
defineProps<{ title: string; linkLabel: string }>();
</script>

<template>
  <header
    class="mb-7 flex items-baseline justify-between gap-4 border-b-2 border-ink pb-3"
  >
    <h2 class="text-[26px] font-bold text-ink text-balance font-display">
      {{ title }}
    </h2>
    <a
      href="#"
      class="shrink-0 text-[12.5px] text-accent underline-offset-4 hover:text-accent-strong hover:underline"
      @click.prevent
    >
      {{ linkLabel }}
      <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
    </a>
  </header>
</template>
