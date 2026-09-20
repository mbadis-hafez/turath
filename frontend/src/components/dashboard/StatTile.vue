<script setup lang="ts">
defineProps<{
  label: string;
  value: string | number;
}>();
</script>

<template>
  <div class="rounded-lg border border-line bg-surface p-4">
    <p class="text-xs font-medium text-ink-muted">{{ label }}</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums text-ink">{{ value }}</p>
  </div>
</template>
