<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { formatLifeDates } from "@/utils/lifeDates";
import type { PartialDate } from "@/types/artist";

const props = defineProps<{
  date: PartialDate;
  label?: string;
}>();

const { t, locale } = useI18n();

const formatted = computed(() =>
  formatLifeDates(props.date, locale.value as "ar" | "en"),
);

const tooltip = computed(() =>
  formatted.value?.asRecorded
    ? `${t("dates.asRecorded")}: ${formatted.value.asRecorded}`
    : undefined,
);
</script>

<template>
  <span v-if="formatted">
    <span v-if="label" class="font-medium text-ink">{{ label }}&#32;</span>
    <span :title="tooltip" :lang="locale === 'ar' ? 'ar' : 'en'">{{
      formatted.text
    }}</span>
  </span>
</template>
