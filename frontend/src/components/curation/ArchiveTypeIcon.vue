<script setup lang="ts">
import { computed } from "vue";

import type { ArchiveItemType } from "@/types/archive";

const props = defineProps<{ type: ArchiveItemType }>();

const shape = computed(() => {
  switch (props.type) {
    case "image": case "poster": case "portfolio":
      return "M4 5h16v14H4zM4 16l4-4 3 3 4-5 5 6";
    case "audio":
      return "M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3zM6 11a6 6 0 0 0 12 0M12 17v4";
    case "video":
      return "M3 6h13v12H3zM16 10l5-3v10l-5-3";
    case "article": case "catalogue": case "documentation_card":
      return "M5 4h14v16H5zM8 8h8M8 12h8M8 16h5";
    default:
      return "M7 3h8l4 4v14H7zM15 3v4h4M10 12h6M10 16h6";
  }
});
</script>

<template>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="size-6">
    <path :d="shape" />
  </svg>
</template>
