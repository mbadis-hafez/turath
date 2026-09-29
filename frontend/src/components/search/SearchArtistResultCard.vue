<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { formatLifeDates } from "@/utils/lifeDates";
import type { ArtistListItem } from "@/types/artist";
import type { AppLocale } from "@/i18n";

const props = defineProps<{
  artist: ArtistListItem;
}>();

const { pick } = useLocalized();
const { localePath } = useLocalePath();
const { locale } = useI18n();

const to = computed(() =>
  localePath("artists.show", { slug: props.artist.slug }),
);
const primary = computed(() => pick(props.artist.name));

const dates = computed(() => {
  const l = locale.value as AppLocale;
  const birth = formatLifeDates(props.artist.birth, l)?.text;
  const death = formatLifeDates(props.artist.death, l)?.text;
  if (!birth && !death) return null;
  return `${birth ?? "?"} – ${death ?? (props.artist.living_status === "living" ? "" : "?")}`.trim();
});
</script>

<template>
  <RouterLink
    :to="to"
    class="flex items-center gap-3 border border-line p-3.5 hover:border-ink"
    data-testid="search-artist-result"
  >
    <div class="size-11 shrink-0 overflow-hidden rounded-full bg-neutral-soft">
      <img
        v-if="artist.portrait_url"
        :src="artist.portrait_url"
        :alt="primary?.text ?? ''"
        class="size-full object-cover"
      />
    </div>
    <div class="min-w-0">
      <p
        class="truncate text-sm font-semibold text-ink"
        :lang="primary?.lang"
        :dir="primary?.dir"
      >
        {{ primary?.text ?? "—" }}
      </p>
      <p
        v-if="dates"
        class="mt-0.5 text-xs tabular-nums text-ink-muted font-latin"
      >
        {{ dates }}
      </p>
    </div>
  </RouterLink>
</template>
