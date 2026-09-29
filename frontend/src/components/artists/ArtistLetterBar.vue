<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { ARABIC_ALPHABET, ENGLISH_ALPHABET } from "@/utils/artistLetter";
import type { ArtistLetters } from "@/types/artist";
import type { AppLocale } from "@/i18n";

const props = defineProps<{
  letters: ArtistLetters | null;
}>();

const emit = defineEmits<{
  click: [letter: string];
}>();

const { locale } = useI18n();

const appLocale = computed(() => locale.value as AppLocale);

const alphabet = computed(() =>
  appLocale.value === "ar" ? ARABIC_ALPHABET : ENGLISH_ALPHABET,
);

const enabled = computed(() => {
  if (!props.letters) return new Set<string>();
  return new Set(appLocale.value === "ar" ? props.letters.ar : props.letters.en);
});

function onClick(letter: string): void {
  if (enabled.value.has(letter)) emit("click", letter);
}
</script>

<template>
  <div
    class="flex flex-wrap gap-1 border-b border-line py-4"
    role="list"
    :aria-label="$t('artists.sort.alphabetical')"
    data-testid="letter-bar"
  >
    <button
      v-for="letter in alphabet"
      :key="letter"
      type="button"
      role="listitem"
      :disabled="!enabled.has(letter)"
      :aria-disabled="!enabled.has(letter)"
      class="flex size-[34px] items-center justify-center font-display text-[18px] font-bold transition-colors focus:outline-none"
      :class="
        enabled.has(letter)
          ? 'border border-line text-ink hover:border-ink hover:bg-neutral-soft'
          : 'border border-transparent text-ink-faint/50'
      "
      :tabindex="enabled.has(letter) ? 0 : -1"
      :data-testid="`letter-${letter}`"
      @click="onClick(letter)"
    >
      {{ letter }}
    </button>
  </div>
</template>
