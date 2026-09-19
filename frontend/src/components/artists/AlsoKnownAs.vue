<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { NameVariant } from "@/types/artist";

const props = defineProps<{
  variants: NameVariant[];
}>();

const { t } = useI18n();

function langOf(variant: NameVariant): string | undefined {
  return variant.language === "und" ? undefined : variant.language;
}

function dirOf(variant: NameVariant): "rtl" | "ltr" | "auto" {
  if (variant.language === "ar") return "rtl";
  if (variant.language === "en") return "ltr";
  return "auto";
}

const sorted = computed(() =>
  [...props.variants].sort((a, b) => a.name.localeCompare(b.name)),
);
</script>

<template>
  <section>
    <h2 class="text-lg font-semibold text-ink">{{ t("artists.alsoKnownAs") }}</h2>
    <ul class="mt-2 flex flex-wrap gap-2">
      <li
        v-for="variant in sorted"
        :key="variant.id"
        class="rounded-full border border-line bg-surface px-3 py-1 text-sm text-ink-muted"
        :lang="langOf(variant)"
        :dir="dirOf(variant)"
      >
        {{ variant.name }}
      </li>
    </ul>
  </section>
</template>
