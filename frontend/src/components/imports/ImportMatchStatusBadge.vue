<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { ImportRowMatchStatus } from "@/types/import";

const props = defineProps<{
  status: ImportRowMatchStatus;
}>();

const { t } = useI18n();

const tone = computed(() => {
  switch (props.status) {
    case "matched_exact":
      return "bg-accent-soft text-accent-strong";
    case "matched_suggested":
      return "bg-info-soft text-info";
    case "ambiguous":
      return "bg-warn-soft text-warn";
    case "error":
      return "bg-danger-soft text-danger";
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
    {{ t(`imports.matchStatus.${status}`) }}
  </span>
</template>
