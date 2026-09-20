<script setup lang="ts">
defineProps<{
  label: string;
  value: string | number;
  hint?: string;
}>();
</script>

<template>
  <div class="p-5">
    <p class="text-4xl font-semibold tabular-nums text-ink">{{ value }}</p>
    <p class="mt-2 text-sm font-medium text-ink">{{ label }}</p>
    <p v-if="hint" class="mt-1 text-pretty text-xs text-ink-muted">{{ hint }}</p>
  </div>
</template>
