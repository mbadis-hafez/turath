<script setup lang="ts">
import { useI18n } from "vue-i18n";

import type { FuzzyDate } from "@/composables/useFuzzyDate";

defineProps<{ legend: string; missing?: boolean }>();
const state = defineModel<FuzzyDate>({ required: true });
const { t } = useI18n();

const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <fieldset class="text-xs text-ink-muted" data-testid="date-group">
    <legend>{{ legend }} <span class="text-danger">{{ t("common.fuzzyDate.required") }}</span></legend>
    <div class="mt-1 grid gap-3 sm:grid-cols-2">
      <select v-model="state.mode" :class="input" :aria-label="t('common.fuzzyDate.mode')" data-testid="date-mode">
        <option v-for="m in ['exact', 'year', 'approx']" :key="m" :value="m">{{ t(`common.fuzzyDate.modes.${m}`) }}</option>
      </select>
      <input v-if="state.mode === 'exact'" v-model="state.date" type="date" dir="ltr" :class="[input, missing ? '!border-danger' : '']" data-testid="date-input" />
      <input v-else-if="state.mode === 'year'" v-model="state.year" type="number" min="1000" max="2100" inputmode="numeric" dir="ltr" :class="[input, missing ? '!border-danger' : '']" data-testid="year-input" />
      <template v-else>
        <input v-model="state.text" type="text" :placeholder="t('common.fuzzyDate.text')" :class="input" data-testid="approx-text" />
        <div class="flex items-center gap-2 sm:col-span-2">
          <input v-model="state.from" type="number" min="1000" max="2100" inputmode="numeric" dir="ltr" :placeholder="t('common.fuzzyDate.from')" :class="[input, missing ? '!border-danger' : '', 'mt-0']" data-testid="approx-from" />
          <span aria-hidden="true">–</span>
          <input v-model="state.to" type="number" min="1000" max="2100" inputmode="numeric" dir="ltr" :placeholder="t('common.fuzzyDate.to')" :class="[input, 'mt-0']" data-testid="approx-to" />
          <select v-model="state.certainty" :class="[input, 'mt-0']" :aria-label="t('common.fuzzyDate.certainty')">
            <option value="circa">{{ t("common.fuzzyDate.certainties.circa") }}</option>
            <option value="range">{{ t("common.fuzzyDate.certainties.range") }}</option>
          </select>
        </div>
      </template>
    </div>
    <label v-if="state.mode === 'approx'" class="mt-3 block">{{ t("common.fuzzyDate.note") }} <span class="text-danger">{{ t("common.fuzzyDate.required") }}</span>
      <textarea v-model="state.note" rows="2" :class="[input, missing ? '!border-danger' : '']" data-testid="date-note" />
    </label>
    <p v-if="missing" class="mt-1 text-danger">{{ state.mode === "approx" ? t("common.fuzzyDate.needsReason") : t("common.fuzzyDate.help") }}</p>
  </fieldset>
</template>
