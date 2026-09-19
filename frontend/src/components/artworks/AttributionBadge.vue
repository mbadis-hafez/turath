<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { AttributionCertainty } from "@/types/artwork";

const props = defineProps<{
  certainty: AttributionCertainty;
}>();

const { t } = useI18n();

const key = computed(() => {
  switch (props.certainty) {
    case "attributed":
      return "artworks.attribution.attributed";
    case "disputed":
      return "artworks.attribution.disputed";
    case "unattributed":
      return "artworks.attribution.unattributed";
    default:
      return null;
  }
});

const tone = computed(() =>
  props.certainty === "disputed" ? "text-warn" : "text-ink-muted",
);
</script>

<template>
  <span
    v-if="key"
    class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium"
    :class="tone"
    :title="t('artworks.attributionHelp')"
  >
    {{ t(key) }}
  </span>
</template>
