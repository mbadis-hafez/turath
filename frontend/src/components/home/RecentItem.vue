<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { AppLocale } from "@/i18n";
import type { HomeArchiveItem } from "@/types/home";

const props = defineProps<{ item: HomeArchiveItem; locale: AppLocale }>();

const { t } = useI18n();

const text = (value: { ar: string | null; en: string | null }) =>
  props.locale === "ar" ? (value.ar ?? value.en ?? "") : (value.en ?? value.ar ?? "");

const kindLabel = computed(() =>
  t(`archive.type.${props.item.item_type}`),
);

const date = computed(() => props.item.content?.display ?? "—");
const title = computed(() => text(props.item.title) || t("archive.restricted"));
const credit = computed(() => props.item.creator_name ?? (props.item.restricted ? t("archive.restrictedHelp") : ""));
</script>

<template>
  <RouterLink :to="{ name: 'archive.records', params: { locale } }" class="flex gap-4 border-b border-line py-4.5">
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
        <span class="text-ink-faint tabular-nums">{{ date }}</span>
      </div>
      <h3
        class="mt-2 leading-[1.55] font-bold text-ink text-pretty font-display text-[19px]"
      >
        {{ title }}
      </h3>
      <p class="mt-1.5 text-[13.5px] leading-[1.7] text-ink-muted">
        {{ credit }}
      </p>
    </div>
  </RouterLink>
</template>
