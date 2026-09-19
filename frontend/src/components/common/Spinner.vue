<script setup lang="ts">
withDefaults(
  defineProps<{
    label?: string;
  }>(),
  { label: "" },
);
</script>

<template>
  <span
    class="inline-block size-5 animate-spin rounded-full border-2 border-line border-t-accent"
    role="status"
    :aria-label="label || $t('common.loading')"
  />
</template>
