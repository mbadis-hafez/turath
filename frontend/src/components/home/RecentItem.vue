<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { AppLocale } from "@/i18n";
import type { RecentItem } from "@/data/homeDemo";

const props = defineProps<{ item: RecentItem; locale: AppLocale }>();

const { t } = useI18n();

const text = (value: { ar: string; en: string }) =>
  props.locale === "ar" ? value.ar : value.en;

const kindLabel = computed(() =>
  props.item.kind === "article"
    ? t("search.materialTypes.articles")
    : t("search.materialTypes.photos"),
);
</script>

<template>
  <a href="#" class="flex gap-4 border-b border-line py-4.5" @click.prevent>
    <div
      class="size-[78px] shrink-0 bg-neutral-soft"
      aria-hidden="true"
    ></div>
    <div class="min-w-0">
      <div
        class="flex flex-wrap items-center gap-2 text-[10.5px] font-latin"
      >
        <span
          class="bg-sand px-2 py-0.75 font-bold text-sand-ink uppercase"
          >{{ kindLabel }}</span
        >
        <span class="text-ink-faint tabular-nums">{{ text(item.meta) }}</span>
      </div>
      <h3
        class="mt-2 leading-[1.55] font-bold text-ink text-pretty font-display text-[19px]"
      >
        {{ text(item.title) }}
      </h3>
      <p class="mt-1.5 text-[13.5px] leading-[1.7] text-ink-muted">
        {{ text(item.credit) }}
      </p>
    </div>
  </a>
</template>
