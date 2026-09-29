<script setup lang="ts">
import { ref } from "vue";
import { useI18n } from "vue-i18n";

import { ocrRegionCropUrl } from "@/api/archive";
import type { OcrFormField } from "@/types/ocr";

const props = defineProps<{
  archiveItemId: number;
  formFields: OcrFormField[];
  canReview: boolean;
}>();
const emit = defineEmits<{
  transcribe: [formFieldId: number, value: string];
}>();
const { t } = useI18n();

const draftValue = ref<Record<number, string>>({});
const brokenCrop = ref<Record<number, boolean>>({});

function submit(field: OcrFormField): void {
  const value = (draftValue.value[field.id] ?? "").trim();
  if (value === "") return;
  emit("transcribe", field.id, value);
}

function cropUrl(field: OcrFormField): string | null {
  if (field.value_region_id === null || brokenCrop.value[field.id]) return null;
  return ocrRegionCropUrl(props.archiveItemId, field.value_region_id);
}
</script>

<template>
  <section v-if="formFields.length > 0" data-testid="ocr-form-fields-table">
    <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t("archive.ocr.formFields.title") }}</h2>
    <p class="mt-1 text-xs text-ink-muted">{{ t("archive.ocr.formFields.explainer") }}</p>

    <div class="mt-3 space-y-3">
      <div v-for="f in formFields" :key="f.id" class="rounded-md border border-line p-3" data-testid="ocr-form-field-row">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="text-sm font-medium text-ink">{{ f.field_label }}</span>
          <span v-if="f.manual_value !== null" class="rounded-sm bg-success-soft px-1.5 py-0.5 text-xs font-medium text-success" data-testid="form-field-transcribed-badge">
            {{ t("archive.ocr.formFields.transcribed") }}
          </span>
          <span v-else-if="!f.requires_manual_transcription && f.machine_value !== null" class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink-muted">
            {{ t("archive.ocr.formFields.printedValue") }}
          </span>
          <span v-else class="rounded-sm bg-warn-soft px-1.5 py-0.5 text-xs font-medium text-warn" data-testid="form-field-needs-transcription-badge">
            {{ t("archive.ocr.formFields.needsTranscription") }}
          </span>
        </div>

        <!-- Resolved: a reviewer already transcribed this value. -->
        <p v-if="f.manual_value !== null" class="mt-2 text-sm text-ink" data-testid="form-field-manual-value">{{ f.manual_value }}</p>

        <!-- Printed form value the OCR engine could legitimately read — no transcription needed. -->
        <p v-else-if="!f.requires_manual_transcription && f.machine_value !== null" class="mt-2 text-sm text-ink" data-testid="form-field-machine-value">{{ f.machine_value }}</p>

        <!-- Needs manual transcription: handwriting, or no machine extraction at all. -->
        <div v-else class="mt-2">
          <img
            v-if="cropUrl(f)"
            :src="cropUrl(f)!"
            :alt="f.field_label"
            class="mb-2 max-h-24 rounded-sm border border-line bg-neutral-soft"
            data-testid="form-field-crop"
            @error="brokenCrop[f.id] = true"
          />
          <p v-else class="mb-2 text-xs text-ink-muted" data-testid="form-field-no-source">{{ t("archive.ocr.formFields.noSourceRegion") }}</p>

          <div v-if="canReview" class="flex items-center gap-2">
            <input
              v-model="draftValue[f.id]"
              type="text"
              class="flex-1 rounded-md border border-ink px-2 py-1 text-sm"
              :placeholder="t('archive.ocr.formFields.transcribePlaceholder')"
              data-testid="form-field-transcribe-input"
              @keyup.enter="submit(f)"
            />
            <button type="button" class="rounded-md bg-ink px-3 py-1 text-xs font-medium text-paper" data-testid="form-field-transcribe-submit" @click="submit(f)">
              {{ t("archive.ocr.formFields.transcribeSubmit") }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
