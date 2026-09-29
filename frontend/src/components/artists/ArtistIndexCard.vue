<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { useLocalized } from "@/composables/useLocalized";
import { useLocalePath } from "@/composables/useLocalePath";
import { formatNumber } from "@/utils/format";
import type { ArtistListItem } from "@/types/artist";
import type { AppLocale } from "@/i18n";

const props = defineProps<{
  artist: ArtistListItem;
}>();

const { t, locale } = useI18n();
const { pick } = useLocalized();
const { localePath } = useLocalePath();

const appLocale = computed(() => locale.value as AppLocale);

const to = computed(() =>
  localePath("artists.show", { slug: props.artist.slug }),
);

const primary = computed(() => pick(props.artist.name));

const secondary = computed(() => {
  const p = primary.value;
  if (!p) return null;
  const other = p.lang === "ar" ? props.artist.name.en : props.artist.name.ar;
  if (!other?.trim() || other === p.text) return null;
  return {
    text: other,
    lang: p.lang === "ar" ? ("en" as const) : ("ar" as const),
    dir: p.lang === "ar" ? ("ltr" as const) : ("rtl" as const),
  };
});

const city = computed(() => pick(props.artist.city));

const materialsLabel = computed(() =>
  t("artists.materialsCount", {
    count: formatNumber(props.artist.materials_count ?? 0, appLocale.value),
    n: props.artist.materials_count ?? 0,
  }),
);

const isUnverified = computed(
  () => props.artist.verified_status !== "verified",
);
</script>

<template>
  <RouterLink :to="to" class="block" :aria-label="primary?.text ?? ''">
    <div class="aspect-[3/4] overflow-hidden bg-neutral-soft">
      <img
        v-if="artist.portrait_url"
        :src="artist.portrait_url"
        :alt="primary?.text ?? ''"
        class="size-full object-cover"
        data-testid="artist-portrait"
      />
    </div>

    <p
      class="mt-3 font-display text-[21px] font-bold leading-[1.4] text-ink"
      :lang="primary?.lang"
      :dir="primary?.dir"
    >
      {{ primary?.text ?? "—" }}
    </p>

    <p
      v-if="secondary"
      class="mt-1 text-[11.5px] leading-relaxed text-ink-faint font-latin"
      :lang="secondary.lang"
      :dir="secondary.dir"
      data-testid="artist-secondary-name"
    >
      {{ secondary.text }}
    </p>

    <div
      class="mt-2.5 flex items-center justify-between border-t border-line pt-2 text-[13px] text-ink-muted"
    >
      <span :lang="city?.lang" :dir="city?.dir">{{ city?.text ?? "—" }}</span>
      <span
        class="text-[11.5px] tabular-nums font-latin"
        data-testid="artist-materials"
      >
        {{ materialsLabel }}
      </span>
    </div>

    <p
      v-if="isUnverified"
      class="mt-2 text-[10.5px] leading-relaxed text-warn font-latin"
      data-testid="artist-pending"
    >
      {{ t("artists.pendingNote") }}
    </p>
  </RouterLink>
</template>
