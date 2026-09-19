<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{ text: string; term: string }>();

const parts = computed(() => {
  const { text, term } = props;
  if (!term || !text.includes(term)) {
    return [{ str: text, hit: false }];
  }
  const segments = text.split(term);
  return segments.flatMap((str, index) => {
    const chunk = [{ str, hit: false }];
    if (index < segments.length - 1) chunk.push({ str: term, hit: true });
    return chunk;
  });
});
</script>

<template>
  <span>
    <template v-for="(part, index) in parts" :key="index">
      <mark v-if="part.hit" class="bg-yellow-200 text-inherit">{{
        part.str
      }}</mark
      ><template v-else>{{ part.str }}</template>
    </template>
  </span>
</template>
