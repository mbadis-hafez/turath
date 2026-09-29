<script setup lang="ts">
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";

import type { ExtractedField } from "@/types/ocr";

const props = defineProps<{
  fields: ExtractedField[];
  canReview: boolean;
}>();
const emit = defineEmits<{
  accept: [fieldId: number];
  reject: [fieldId: number];
  edit: [fieldId: number, value: string];
  "accept-high-confidence": [];
}>();
const { t } = useI18n();

const HIGH_CONFIDENCE = 85;
const highConfidenceCount = computed(() => props.fields.filter((f) => (f.status === "pending" || f.status === "edited") && f.confidence >= HIGH_CONFIDENCE).length);

const editingId = ref<number | null>(null);
const draftValue = ref("");
function startEdit(field: ExtractedField): void {
  editingId.value = field.id;
  draftValue.value = field.extracted_value ?? "";
}
function saveEdit(fieldId: number): void {
  emit("edit", fieldId, draftValue.value);
  editingId.value = null;
}

const CONFIDENCE_COLOR = (c: number) => (c >= HIGH_CONFIDENCE ? "bg-success" : c >= 50 ? "bg-warn" : "bg-danger");

// Reuses the existing field-name labels from the edit form's i18n keys rather than
// duplicating a parallel set — this list mirrors ExtractedFieldPayloadMapper on the backend.
const FIELD_LABEL_KEYS: Record<string, string> = {
  title_ar: "archive.edit.titleAr", title_en: "archive.edit.titleEn",
  description_ar: "archive.edit.descriptionAr", description_en: "archive.edit.descriptionEn",
  place_ar: "archive.edit.place", place_en: "archive.edit.place",
  rights_holder_ar: "archive.edit.rightsHolder", rights_holder_en: "archive.edit.rightsHolder",
  source_name: "archive.edit.sourceName", verification_reference: "archive.edit.verification",
  date_display: "archive.edit.date",
};
const fieldLabel = (key: string): string => (FIELD_LABEL_KEYS[key] ? t(FIELD_LABEL_KEYS[key]) : key);
const STATUS_LABEL: Record<ExtractedField["status"], string> = {
  pending: "archive.ocr.fieldStatus.pending",
  edited: "archive.ocr.fieldStatus.edited",
  accepted: "archive.ocr.fieldStatus.accepted",
  rejected: "archive.ocr.fieldStatus.rejected",
};
</script>

<template>
  <section data-testid="ocr-fields-table">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b-2 border-ink pb-2">
      <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t("archive.ocr.fieldsTitle") }}</h2>
      <button
        v-if="canReview && highConfidenceCount > 0"
        type="button"
        class="rounded-md border border-ink px-3 py-1.5 text-xs font-medium text-ink hover:bg-neutral-soft"
        data-testid="accept-high-confidence"
        @click="emit('accept-high-confidence')"
      >
        {{ t("archive.ocr.acceptHighConfidence", { count: highConfidenceCount }) }}
      </button>
    </div>

    <div class="mt-3 space-y-2">
      <div v-for="f in fields" :key="f.id" class="flex flex-wrap items-center gap-3 border-b border-line py-2.5 text-sm" data-testid="ocr-field-row">
        <span class="w-40 shrink-0 text-ink-muted">{{ fieldLabel(f.field_key) }}</span>

        <div v-if="editingId === f.id" class="flex flex-1 items-center gap-2">
          <input v-model="draftValue" type="text" class="flex-1 rounded-md border border-ink px-2 py-1 text-sm" data-testid="edit-input" />
          <button type="button" class="rounded-md bg-ink px-2 py-1 text-xs text-paper" data-testid="save-edit" @click="saveEdit(f.id)">{{ t("archive.ocr.save") }}</button>
          <button type="button" class="text-xs text-ink-muted hover:text-ink" @click="editingId = null">{{ t("archive.ocr.cancel") }}</button>
        </div>
        <span v-else class="flex-1 font-medium text-ink" data-testid="field-value">{{ f.extracted_value ?? "—" }}</span>

        <span class="flex w-24 shrink-0 items-center gap-2">
          <span class="h-1 flex-1 overflow-hidden rounded-full bg-neutral-soft"><span class="block h-full rounded-full" :class="CONFIDENCE_COLOR(f.confidence)" :style="{ width: `${f.confidence}%` }" /></span>
          <span class="font-mono text-xs tabular-nums">{{ f.confidence }}%</span>
        </span>

        <span class="w-24 shrink-0 text-xs text-ink-muted" data-testid="field-status">{{ t(STATUS_LABEL[f.status]) }}</span>

        <span v-if="canReview && editingId !== f.id && (f.status === 'pending' || f.status === 'edited')" class="flex shrink-0 gap-1.5">
          <button type="button" class="rounded-sm border border-ink px-2 py-1 text-xs" data-testid="accept-field" @click="emit('accept', f.id)">{{ t("archive.ocr.accept") }}</button>
          <button type="button" class="rounded-sm border border-line px-2 py-1 text-xs hover:border-ink" data-testid="edit-field" @click="startEdit(f)">{{ t("archive.ocr.edit") }}</button>
          <button type="button" class="rounded-sm border border-line px-2 py-1 text-xs text-danger hover:border-danger" data-testid="reject-field" @click="emit('reject', f.id)">{{ t("archive.ocr.reject") }}</button>
        </span>
      </div>
    </div>
  </section>
</template>
