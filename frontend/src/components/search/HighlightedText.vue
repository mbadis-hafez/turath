<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{ text: string; term: string }>();

/**
 * Case-insensitive split so an English query matches regardless of casing;
 * Arabic has no case to fold, so this is a no-op there. Not full
 * `ArabicNormalizer` parity (diacritics/alef forms) — that logic only
 * exists server-side; this is a best-effort client-side highlight.
 */
const parts = computed(() => {
  const { text, term } = props;
  const needle = term.trim();
  if (!needle) return [{ str: text, hit: false }];

  const haystack = text.toLocaleLowerCase();
  const lowerNeedle = needle.toLocaleLowerCase();
  const segments: { str: string; hit: boolean }[] = [];
  let cursor = 0;

  while (cursor <= text.length) {
    const at = haystack.indexOf(lowerNeedle, cursor);
    if (at === -1) {
      segments.push({ str: text.slice(cursor), hit: false });
      break;
    }
    if (at > cursor) segments.push({ str: text.slice(cursor, at), hit: false });
    segments.push({ str: text.slice(at, at + needle.length), hit: true });
    cursor = at + needle.length;
  }

  return segments.length > 0 ? segments : [{ str: text, hit: false }];
});
</script>

<template>
  <span>
    <template v-for="(part, index) in parts" :key="index">
      <mark v-if="part.hit" class="bg-warn-soft text-inherit">{{
        part.str
      }}</mark
      ><template v-else>{{ part.str }}</template>
    </template>
  </span>
</template>
