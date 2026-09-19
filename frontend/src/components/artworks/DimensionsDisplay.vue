<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { Dimensions } from "@/types/artwork";

const props = defineProps<{
  dimensions: Dimensions;
  frame?: boolean;
}>();

const { t } = useI18n();

/** Structured segments joined by "×"; null segments (and their separators) are omitted. */
const structured = computed<string | null>(() => {
  const segments = [
    props.dimensions.height_cm,
    props.dimensions.width_cm,
    props.dimensions.depth_cm,
  ].filter((value): value is number => value !== null);
  if (segments.length === 0) return null;
  return `${segments.join(" × ")} cm`;
});

const raw = computed(() => props.dimensions.raw?.trim() ?? "");

const hasAnything = computed(
  () => structured.value !== null || raw.value !== "",
);
</script>

<template>
  <span v-if="hasAnything">
    <span v-if="structured">{{ structured }}</span>
    <span
      v-else
      class="text-ink-muted italic"
      :title="t('artworks.dimensions')"
      >{{ raw }}</span
    >
  </span>
</template>
