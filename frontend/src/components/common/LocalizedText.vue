<script setup lang="ts">
import { computed } from "vue";

import { useLocalized, type LocalizedValue } from "@/composables/useLocalized";

const props = defineProps<{
  text: LocalizedValue | null;
}>();

const { pick } = useLocalized();
const picked = computed(() => pick(props.text));
</script>

<template>
  <template v-if="picked">
    <span :lang="picked.lang" :dir="picked.dir">{{ picked.text }}</span>
    <span
      v-if="picked.isFallback"
      class="ms-1 rounded-sm bg-neutral-soft px-1 py-0.5 text-xs text-ink-muted"
      role="note"
    >
      {{ $t("common.shownInOtherLanguage") }}
    </span>
  </template>
</template>
