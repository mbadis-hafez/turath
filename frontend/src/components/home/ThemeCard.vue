<script setup lang="ts">
import { useI18n } from "vue-i18n";

import { formatNumber } from "@/utils/format";
import type { AppLocale } from "@/i18n";
import type { HomeTheme } from "@/data/homeDemo";

const props = defineProps<{ theme: HomeTheme; locale: AppLocale }>();

const { t } = useI18n();

const text = (value: { ar: string; en: string }) =>
  props.locale === "ar" ? value.ar : value.en;

const countLabel = t("home.themes.materials", {
  count: formatNumber(props.theme.count, props.locale),
});
</script>

<template>
  <article class="group cursor-pointer">
    <div
      class="aspect-[3/2] bg-neutral-soft transition-colors group-hover:bg-sand"
      aria-hidden="true"
    ></div>
    <h3
      class="mt-3.5 leading-snug font-bold text-ink text-balance font-display text-[22px]"
    >
      {{ text(theme.title) }}
    </h3>
    <p class="mt-1.5 text-[11.5px] leading-relaxed text-ink-faint font-latin">
      {{ theme.title.en }}
    </p>
    <p class="mt-2.5 text-sm leading-[1.85] text-ink-muted text-pretty">
      {{ text(theme.note) }}
    </p>
    <p
      class="mt-2.5 border-t border-line pt-2 text-[11.5px] text-ink tabular-nums font-latin"
    >
      {{ countLabel }}
    </p>
  </article>
</template>
