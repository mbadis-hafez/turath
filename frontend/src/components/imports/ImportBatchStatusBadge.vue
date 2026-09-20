<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { ImportBatchStatus } from "@/types/import";

const props = defineProps<{
  status: ImportBatchStatus;
}>();

const { t } = useI18n();

const tone = computed(() => {
  switch (props.status) {
    case "committed":
      return "bg-accent-soft text-accent-strong";
    case "failed":
      return "bg-danger-soft text-danger";
    case "cancelled":
      return "bg-neutral-soft text-ink-muted";
    default:
      return "bg-neutral-soft text-ink";
  }
});
</script>

<template>
  <span
    class="inline-flex items-center rounded-sm px-1.5 py-0.5 text-xs font-medium"
    :class="tone"
  >
    {{ t(`imports.status.${status}`) }}
  </span>
</template>
