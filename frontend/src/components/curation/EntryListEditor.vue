<script setup lang="ts">
import { useI18n } from "vue-i18n";

import ListEditor from "@/components/curation/ListEditor.vue";
import { newActivity, newEntry } from "@/composables/useArtistProfileForm";
import type { ProfileEntry } from "@/types/artistCuration";

defineProps<{
  addLabel: string;
  /** Education spans years; activities have a single year. */
  range?: boolean;
  /** Show a per-entry type selector (award, exhibition, talk, symposium). */
  typed?: boolean;
}>();

const entries = defineModel<ProfileEntry[]>({ required: true });
const { t } = useI18n();

const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <ListEditor v-model="entries" :create="typed ? newActivity : newEntry" :add-label="addLabel">
    <template #default="{ item }">
      <div class="grid gap-3 sm:grid-cols-2">
        <label v-if="typed" class="text-xs text-ink-muted sm:col-span-2">{{ t("curation.profileForm.activityType") }}
          <select v-model="item.type" data-testid="activity-type" :class="input">
            <option v-for="k in ['award', 'exhibition', 'talk', 'symposium']" :key="k" :value="k">{{ t(`curation.profileForm.activityTypes.${k}`) }}</option>
          </select>
        </label>
        <label class="text-xs text-ink-muted">{{ t("curation.profileForm.entryTitleAr") }}<input v-model="item.title.ar" type="text" dir="rtl" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.profileForm.entryTitleEn") }}<input v-model="item.title.en" type="text" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.profileForm.placeAr") }}<input v-model="item.place.ar" type="text" dir="rtl" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.profileForm.placeEn") }}<input v-model="item.place.en" type="text" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ range ? t("curation.profileForm.yearFrom") : t("curation.profileForm.year") }}
          <input v-model.number="item.year_from" type="number" min="1000" max="2100" inputmode="numeric" :class="input" />
        </label>
        <label v-if="range" class="text-xs text-ink-muted">{{ t("curation.profileForm.yearTo") }}
          <input v-model.number="item.year_to" type="number" min="1000" max="2100" inputmode="numeric" :class="input" />
        </label>
      </div>
    </template>
  </ListEditor>
</template>
