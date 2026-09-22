<script lang="ts">
export type ArtworkViewMode = "grid" | "list";
</script>

<script setup lang="ts">
import { useI18n } from "vue-i18n";

const props = defineProps<{
  modelValue: ArtworkViewMode;
}>();

const emit = defineEmits<{
  "update:modelValue": [value: ArtworkViewMode];
}>();

const { t } = useI18n();

const MODES: { value: ArtworkViewMode; testid: string; labelKey: string }[] = [
  { value: "grid", testid: "view-toggle-grid", labelKey: "curation.artworkRegistry.view.grid" },
  { value: "list", testid: "view-toggle-list", labelKey: "curation.artworkRegistry.view.list" },
];

function select(mode: ArtworkViewMode): void {
  if (mode !== props.modelValue) emit("update:modelValue", mode);
}
</script>

<template>
  <div class="flex rounded-md border border-line bg-surface p-0.5" role="group" :aria-label="t('curation.artworkRegistry.view.grid') + ' / ' + t('curation.artworkRegistry.view.list')">
    <button
      v-for="mode in MODES"
      :key="mode.value"
      type="button"
      :data-testid="mode.testid"
      :aria-pressed="props.modelValue === mode.value"
      class="rounded px-3 py-2 text-sm font-medium"
      :class="props.modelValue === mode.value ? 'bg-ink text-paper' : 'text-ink-muted hover:text-ink'"
      @click="select(mode.value)"
    >
      {{ t(mode.labelKey) }}
    </button>
  </div>
</template>
