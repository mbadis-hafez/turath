<script setup lang="ts">
import ArtistIndexCard from "./ArtistIndexCard.vue";
import type { ArtistListItem } from "@/types/artist";

defineProps<{
  letter: string;
  artists: ArtistListItem[];
}>();
</script>

<template>
  <section
    :id="`letter-${letter}`"
    class="flex flex-col gap-6 border-b border-line py-7 md:flex-row md:gap-8"
    :aria-labelledby="`letter-heading-${letter}`"
    data-testid="letter-group"
  >
    <h2
      :id="`letter-heading-${letter}`"
      class="shrink-0 font-display text-[44px] font-bold leading-none text-ink md:w-16"
    >
      {{ letter }}
    </h2>
    <div
      class="grid flex-1 grid-cols-1 gap-6 min-[480px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
      :style="{ rowGap: '26px', columnGap: '26px' }"
    >
      <ArtistIndexCard
        v-for="artist in artists"
        :key="artist.id"
        :artist="artist"
      />
    </div>
  </section>
</template>
