<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { isLocale, type AppLocale } from "@/i18n";
import { greetingPeriod } from "@/utils/editorDashboard";

defineProps<{
  name: string;
}>();

const { t, locale } = useI18n();

const period = greetingPeriod();

const today = computed(() => {
  const appLocale: AppLocale = isLocale(locale.value) ? locale.value : "ar";
  return new Intl.DateTimeFormat(appLocale, {
    dateStyle: "full",
    numberingSystem: "latn",
  }).format(new Date());
});
</script>

<template>
  <div class="border-b-2 border-ink pb-6">
    <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">
      {{ t(`editorDashboard.greeting.${period}`, { name }) }}
    </h1>
    <p class="mt-1 text-sm text-ink-muted">{{ t("editorDashboard.subtitle") }}</p>
    <p class="mt-1 text-xs text-ink-faint">{{ today }}</p>
  </div>
</template>
