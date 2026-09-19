<script setup lang="ts">
import type { DemoArtwork } from "@/data/searchDemo";

defineProps<{ artwork: DemoArtwork }>();
</script>

<template>
  <article>
    <div class="aspect-[4/3] bg-neutral-soft" aria-hidden="true"></div>
    <h3 class="mt-3 leading-snug font-semibold text-ink text-pretty">
      {{ artwork.title }}
    </h3>
    <p class="mt-1 text-sm text-ink-muted">{{ artwork.artist }}</p>
    <p class="text-sm text-ink-muted tabular-nums">
      {{ artwork.year }} · {{ artwork.medium }}
    </p>
    <p class="mt-2 border-t border-line pt-2 text-xs text-ink-muted">
      {{ artwork.collection }}
    </p>
  </article>
</template>
