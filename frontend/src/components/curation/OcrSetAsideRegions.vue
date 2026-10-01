<script setup lang="ts">
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";

import { ocrRegionCropUrl } from "@/api/archive";
import type { OcrSourceFocus } from "@/components/curation/OcrSourceViewer.vue";
import { RECORD_FIELD_LABEL_KEYS } from "@/components/curation/ocrRecordFields";
import type { DocumentSchemaField, ExtractedField, OcrSetAsideRegion } from "@/types/ocr";

/**
 * Lines the pipeline set aside for a person that nothing else shows: a
 * possible strikethrough on printed text, handwriting with no label beside
 * it. Nothing is chosen for the reviewer — they read the crop, pick the field
 * the line fills and type what it says (or say there's nothing to take).
 */
const props = defineProps<{
  archiveItemId: number;
  regions: OcrSetAsideRegion[];
  schema: DocumentSchemaField[];
  fields: ExtractedField[];
  canReview: boolean;
}>();
const emit = defineEmits<{
  transcribe: [regionId: number, fieldKey: string, value: string];
  dismiss: [regionId: number, dismissed: boolean];
  "show-source": [focus: OcrSourceFocus];
}>();
const { t, locale } = useI18n();

/** Fields a line can fill: the document's own (contacts go through their panel, dates through theirs) and the item's. */
const fieldOptions = computed(() => {
  const pick = (label: { ar: string; en: string }) => (locale.value === "ar" ? label.ar : label.en);
  const schemaFields = props.schema
    .filter((f) => f.route !== "artist_contact" && f.kind !== "date")
    .map((f) => ({ key: f.key, label: pick(f.label) }));
  // Place and rights holder share one label for both languages; say which.
  const entries = Object.entries(RECORD_FIELD_LABEL_KEYS);
  const shared = (labelKey: string) => entries.filter(([, k]) => k === labelKey).length > 1;
  const recordFields = entries.map(([key, labelKey]) => {
    const lang = !shared(labelKey) ? "" : key.endsWith("_ar") ? ` (${t("archive.ocr.language.ar")})` : ` (${t("archive.ocr.language.en")})`;
    return { key, label: t(labelKey) + lang };
  });
  return [...schemaFields, ...recordFields];
});

const chosenField = ref<Record<number, string>>({});
const typed = ref<Record<number, string>>({});
const brokenCrop = ref<Record<number, boolean>>({});

function submit(region: OcrSetAsideRegion): void {
  const key = chosenField.value[region.id] ?? "";
  const value = (typed.value[region.id] ?? "").trim();
  if (key === "" || value === "") return;
  emit("transcribe", region.id, key, value);
  typed.value[region.id] = "";
}

function typedFrom(region: OcrSetAsideRegion): ExtractedField[] {
  return props.fields.filter((f) => region.transcribed_field_ids.includes(f.id));
}
const optionLabel = (key: string): string => fieldOptions.value.find((o) => o.key === key)?.label ?? key;

const open = computed(() => props.regions.filter((r) => !r.dismissed));
const dismissed = computed(() => props.regions.filter((r) => r.dismissed));
</script>

<template>
  <section v-if="regions.length > 0" data-testid="ocr-set-aside">
    <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t("archive.ocr.setAside.title") }}</h2>
    <p class="mt-1 text-xs text-ink-muted">{{ t("archive.ocr.setAside.explainer") }}</p>

    <div class="mt-3 space-y-3">
      <div v-for="r in open" :key="r.id" class="rounded-md border border-line p-3 text-sm" data-testid="ocr-set-aside-region">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="font-medium text-ink">
            {{ t(`archive.ocr.source.regionTypes.${r.region_type}`) }} · {{ t("archive.ocr.provenance.page", { page: r.page_number }) }}
          </span>
          <button type="button" class="text-xs text-accent underline" data-testid="ocr-set-aside-show" @click="emit('show-source', { page: r.page_number, bbox: r.bbox, label: t(`archive.ocr.source.regionTypes.${r.region_type}`) })">
            {{ t("archive.ocr.evidence.showRegion", { page: r.page_number }) }}
          </button>
        </div>

        <!-- Nothing is chosen between the crossed-out value and its replacement: that's the reviewer's to read. -->
        <p v-if="r.has_correction_mark" class="mt-2 rounded-md bg-danger-soft p-2 text-xs text-ink" data-testid="ocr-set-aside-correction">{{ t("archive.ocr.setAside.correctionMark") }}</p>
        <img
          v-if="r.has_crop && !brokenCrop[r.id]"
          :src="ocrRegionCropUrl(archiveItemId, r.id)"
          :alt="t(`archive.ocr.source.regionTypes.${r.region_type}`)"
          class="mt-2 max-h-24 rounded-sm border border-line bg-neutral-soft"
          data-testid="ocr-set-aside-crop"
          @error="brokenCrop[r.id] = true"
        />
        <p v-if="r.ocr_text" class="mt-2 text-xs text-ink-muted" data-testid="ocr-set-aside-reading">
          {{ t("archive.ocr.setAside.untrustedReading") }} <span dir="auto" class="font-mono text-ink">{{ r.ocr_text }}</span>
        </p>

        <ul v-if="typedFrom(r).length > 0" class="mt-2 space-y-0.5 text-xs text-ink" data-testid="ocr-set-aside-typed">
          <li v-for="f in typedFrom(r)" :key="f.id">{{ t("archive.ocr.setAside.typedInto", { field: optionLabel(f.field_key), value: f.verified_value ?? "" }) }}</li>
        </ul>

        <div v-if="canReview" class="mt-2 flex flex-wrap items-center gap-2">
          <select v-model="chosenField[r.id]" class="rounded-md border border-line bg-surface px-2 py-1 text-xs" data-testid="ocr-set-aside-field">
            <option value="" disabled>{{ t("archive.ocr.setAside.chooseField") }}</option>
            <option v-for="o in fieldOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
          </select>
          <input
            v-model="typed[r.id]"
            type="text"
            dir="auto"
            class="min-w-0 flex-1 rounded-md border border-ink px-2 py-1 text-sm"
            :placeholder="t('archive.ocr.setAside.typePlaceholder')"
            data-testid="ocr-set-aside-value"
            @keyup.enter="submit(r)"
          />
          <button
            type="button"
            class="rounded-md bg-ink px-2 py-1 text-xs text-paper disabled:opacity-50"
            :disabled="!chosenField[r.id] || !(typed[r.id] ?? '').trim()"
            data-testid="ocr-set-aside-submit"
            @click="submit(r)"
          >
            {{ t("archive.ocr.setAside.add") }}
          </button>
          <button type="button" class="text-xs text-ink-muted underline hover:text-ink" data-testid="ocr-set-aside-dismiss" @click="emit('dismiss', r.id, true)">
            {{ t("archive.ocr.setAside.dismiss") }}
          </button>
        </div>
      </div>

      <p v-if="dismissed.length > 0" class="text-xs text-ink-muted" data-testid="ocr-set-aside-dismissed">
        {{ t("archive.ocr.setAside.dismissedCount", { count: dismissed.length }) }}
        <template v-if="canReview">
          <button v-for="r in dismissed" :key="r.id" type="button" class="ms-2 underline hover:text-ink" data-testid="ocr-set-aside-restore" @click="emit('dismiss', r.id, false)">
            {{ t("archive.ocr.setAside.restore", { page: r.page_number }) }}
          </button>
        </template>
      </p>
    </div>
  </section>
</template>
