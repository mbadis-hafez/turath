<script setup lang="ts">
import { useI18n } from "vue-i18n";

import { formatNumber } from "@/utils/format";
import type { AppLocale } from "@/i18n";
import type { HomeTheme } from "@/types/home";

const props = defineProps<{ theme: HomeTheme; locale: AppLocale }>();

const { t } = useI18n();

const text = (value: { ar: string | null; en: string | null }) =>
  props.locale === "ar" ? (value.ar ?? value.en ?? "") : (value.en ?? value.ar ?? "");

const countLabel = () => t("home.themes.materials", { count: formatNumber(props.theme.count, props.locale) });
</script>

<template>
  <RouterLink :to="{ name: 'timeline', params: { locale }, query: { theme: theme.id } }" class="group block">
    <div
      class="aspect-[3/2] bg-neutral-soft transition-colors group-hover:bg-sand"
      aria-hidden="true"
    ></div>
    <h3
      class="mt-3.5 leading-snug font-bold text-ink text-balance font-display text-[22px]"
    >
      {{ text(theme.label) }}
    </h3>
    <p class="mt-1.5 text-[11.5px] leading-relaxed text-ink-faint font-latin">
      {{ theme.label.en }}
    </p>
    <p
      class="mt-2.5 border-t border-line pt-2 text-[11.5px] text-ink tabular-nums font-latin"
    >
      {{ countLabel() }}
    </p>
  </RouterLink>
</template>
