<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string;
    description?: string;
  }>(),
  { title: "", description: "" },
);
</script>

<template>
  <div
    class="rounded-lg border border-dashed border-line bg-surface px-6 py-12 text-center"
  >
    <p v-if="title" class="text-lg font-medium text-ink">{{ title }}</p>
    <p v-if="description" class="mt-1 text-sm text-ink-muted">
      {{ description }}
    </p>
    <slot />
  </div>
</template>
