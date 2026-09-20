<script setup lang="ts">
import { useI18n } from "vue-i18n";

import { searchArtistOptions, searchHolderOptions } from "@/components/curation/ArtworkPickers";
import EntityPicker, { type PickerOption } from "@/components/curation/EntityPicker.vue";
import type { ArtworkFormState } from "@/composables/useArtworkForm";
import {
  ARTWORK_CATEGORIES as CATEGORIES, CONDITION_STATES as CONDITION, MATERIAL_CLASSIFICATIONS as MATERIAL, IMAGE_QUALITIES as QUALITY, SIGNED_STATES as SIGNED,
} from "@/composables/useArtworkForm";

const props = defineProps<{
  gaps: string[];
  /** Detail page only: shows the final HR image field. */
  finalImage?: { present: boolean; label: string | null };
}>();

const form = defineModel<ArtworkFormState>("form", { required: true });
const artist = defineModel<PickerOption | null>("artist", { required: true });
const holder = defineModel<PickerOption | null>("holder", { required: true });
const { t } = useI18n();

const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
const gapInput = (key: string) => (props.gaps.includes(key) ? "!border-danger !bg-danger-soft" : "");
</script>

<template>
  <div class="space-y-10">
    <section>
      <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.artworkDetail.identification") }}</h2>
      <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.code") }}<input v-model="form.code" type="text" dir="ltr" maxlength="40" :class="input" data-testid="code-input" /></label>
        <div class="text-xs text-ink-muted">{{ t("curation.artworkDetail.artist") }}<EntityPicker v-model="artist" :search="searchArtistOptions" :placeholder="t('curation.artworkDetail.searchArtist')" /></div>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.titleAr") }}<input v-model="form.title.ar" type="text" dir="rtl" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.titleEn") }}<input v-model="form.title.en" type="text" dir="ltr" :class="input" /></label>
        <label class="flex items-center gap-2 text-sm text-ink sm:col-span-2"><input v-model="form.isUntitled" type="checkbox" class="size-4" data-testid="untitled-input" />{{ t("curation.artworkRegistry.flags.untitled") }}</label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.category") }}
          <select v-model="form.category" :class="input"><option v-for="c in CATEGORIES" :key="c" :value="c">{{ t(`curation.artworkDetail.categories.${c}`) }}</option></select>
        </label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.year") }}<input v-model="form.year" type="number" min="1000" max="2100" inputmode="numeric" :class="[input, gapInput('year')]" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.mediumAr") }}<input v-model="form.medium.ar" type="text" dir="rtl" :class="[input, gapInput('medium')]" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.mediumEn") }}<input v-model="form.medium.en" type="text" dir="ltr" :class="[input, gapInput('medium')]" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.materialClassification") }}
        <select v-model="form.materialClassification" :class="input" data-testid="material-classification"><option v-for="m in MATERIAL" :key="m" :value="m">{{ t(`curation.artworkDetail.materialClassifications.${m}`) }}</option></select>
      </label>
      <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.signed") }}
          <select v-model="form.signed" :class="input"><option v-for="s in SIGNED" :key="s" :value="s">{{ t(`curation.artworkDetail.signedStates.${s}`) }}</option></select>
        </label>
        <label class="text-xs text-ink-muted sm:col-span-2">{{ t("curation.artworkDetail.notesAr") }}<textarea v-model="form.notes.ar" rows="3" dir="rtl" :class="input" /></label>
        <label class="text-xs text-ink-muted sm:col-span-2">{{ t("curation.artworkDetail.notesEn") }}<textarea v-model="form.notes.en" rows="3" dir="ltr" :class="input" /></label>
      </div>
    </section>

    <section>
      <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.artworkDetail.specifications") }}</h2>
      <div class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.height") }}<input v-model="form.height" type="number" step="0.01" min="0" dir="ltr" :class="[input, gapInput('dimensions')]" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.width") }}<input v-model="form.width" type="number" step="0.01" min="0" dir="ltr" :class="[input, gapInput('dimensions')]" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.depth") }}<input v-model="form.depth" type="number" step="0.01" min="0" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.frameHeight") }}<input v-model="form.frameHeight" type="number" step="0.01" min="0" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.frameWidth") }}<input v-model="form.frameWidth" type="number" step="0.01" min="0" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.frameDepth") }}<input v-model="form.frameDepth" type="number" step="0.01" min="0" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.weight") }}<input v-model="form.weight" type="number" step="0.01" min="0" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.editionNumber") }}<input v-model="form.editionNumber" type="text" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.editionSize") }}<input v-model="form.editionSize" type="number" min="0" dir="ltr" :class="input" /></label>
        <div class="text-xs text-ink-muted">{{ t("curation.artworkDetail.holder") }}<EntityPicker v-model="holder" :search="searchHolderOptions" :placeholder="t('curation.artworkDetail.searchHolder')" /></div>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.holderInventory") }}<input v-model="form.holderInventory" type="text" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.inventoryByOwner") }}<input v-model="form.inventoryByOwner" type="text" dir="ltr" :class="input" /></label>
      </div>
    </section>

    <section>
      <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.artworkDetail.conditionImages") }}</h2>
      <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.conditionStatus") }}
          <select v-model="form.conditionStatus" :class="input"><option value="">—</option><option v-for="c in CONDITION" :key="c" :value="c">{{ t(`curation.artworkDetail.conditionStates.${c}`) }}</option></select>
        </label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.conditionLink") }}<input v-model="form.conditionLink" type="url" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted sm:col-span-2">{{ t("curation.artworkDetail.riskNote") }}<textarea v-model="form.riskNote" rows="2" :class="input" data-testid="risk-note" /><span class="mt-1 block text-ink-muted">{{ t("curation.artworkDetail.riskNoteHelp") }}</span></label>
      <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.imageQuality") }}
          <select v-model="form.imageQuality" :class="input"><option value="">—</option><option v-for="q in QUALITY" :key="q" :value="q">{{ t(`curation.artworkDetail.qualities.${q}`) }}</option></select>
        </label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.editingStatus") }}<input v-model="form.editingStatus" type="text" dir="ltr" maxlength="20" :class="input" /></label>
        <div v-if="finalImage" class="text-xs text-ink-muted">{{ t("curation.artworkDetail.finalImage") }}<p class="mt-1 rounded-md border px-3 py-2 text-sm text-ink" :class="finalImage.present ? 'border-line bg-neutral-soft' : 'border-danger bg-danger-soft'">{{ finalImage.present ? finalImage.label : t("curation.artworkDetail.noFinalImage") }}</p></div>
      </div>
    </section>
  </div>
</template>
