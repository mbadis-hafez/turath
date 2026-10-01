<script setup lang="ts">
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";

import OcrEntityMatch from "@/components/curation/OcrEntityMatch.vue";
import OcrFieldEvidence from "@/components/curation/OcrFieldEvidence.vue";
import type { OcrSourceFocus } from "@/components/curation/OcrSourceViewer.vue";
import { RECORD_FIELD_LABEL_KEYS } from "@/components/curation/ocrRecordFields";
import type { EntityMatchCandidate, ExtractedField } from "@/types/ocr";

const props = defineProps<{
  fields: ExtractedField[];
  canReview: boolean;
  archiveItemId: number;
}>();
const emit = defineEmits<{
  accept: [fieldId: number];
  reject: [fieldId: number];
  edit: [fieldId: number, value: string];
  uncertain: [fieldId: number, note: string | null];
  "transcribe-form-field": [formFieldId: number, value: string];
  "accept-high-confidence": [];
  "confirm-match": [matchId: number, candidate: EntityMatchCandidate];
  "link-match": [matchId: number, entityId: number | string];
  "decide-match": [matchId: number, decision: "no-match" | "reset"];
  "show-source": [focus: OcrSourceFocus];
}>();
const { t, locale } = useI18n();

const HIGH_CONFIDENCE = 85;
/** Only the archive item's own fields are ever bulk-accepted; everything else is verified one by one. */
const isRecordField = (f: ExtractedField): boolean => (f.route ?? "record") === "record";
const currentValue = (f: ExtractedField): string | null => f.verified_value ?? f.extracted_value;
/** Still awaiting a decision — an uncertain value too, though bulk accept never takes it. */
const isOpen = (f: ExtractedField): boolean => f.status === "pending" || f.status === "edited" || f.status === "uncertain";
const highConfidenceCount = computed(() => props.fields.filter((f) => (f.status === "pending" || f.status === "edited") && isRecordField(f) && currentValue(f) !== null && f.confidence >= HIGH_CONFIDENCE).length);

const editingId = ref<number | null>(null);
const draftValue = ref("");
function startEdit(field: ExtractedField, value?: string): void {
  editingId.value = field.id;
  draftValue.value = value ?? currentValue(field) ?? "";
}
function saveEdit(fieldId: number): void {
  emit("edit", fieldId, draftValue.value);
  editingId.value = null;
}

/** Marking uncertain asks for an optional note first. */
const uncertainId = ref<number | null>(null);
const uncertainNote = ref("");
function startUncertain(field: ExtractedField): void {
  uncertainId.value = field.id;
  uncertainNote.value = field.review_note ?? "";
}
function saveUncertain(fieldId: number): void {
  emit("uncertain", fieldId, uncertainNote.value.trim() === "" ? null : uncertainNote.value.trim());
  uncertainId.value = null;
}

/** A value still to be read off a form field's crop is typed here, into that form field. */
const transcription = ref<Record<number, string>>({});
function submitTranscription(field: ExtractedField): void {
  const value = (transcription.value[field.id] ?? "").trim();
  if (value === "" || !field.form_field_id) return;
  emit("transcribe-form-field", field.form_field_id, value);
}

const CONFIDENCE_COLOR = (c: number) => (c >= HIGH_CONFIDENCE ? "bg-success" : c >= 50 ? "bg-warn" : "bg-danger");

// A document type's fields carry their own labels from its schema; the archive item's own reuse the edit form's.
function fieldLabel(field: ExtractedField): string {
  if (field.label) return locale.value === "ar" ? field.label.ar : field.label.en;
  return RECORD_FIELD_LABEL_KEYS[field.field_key] ? t(RECORD_FIELD_LABEL_KEYS[field.field_key]) : field.field_key;
}
/** "Exhibitions 2" for the second item of a list field. */
const itemNumber = (field: ExtractedField): string => {
  const siblings = props.fields.filter((f) => f.field_key === field.field_key && f.document_type === field.document_type);
  return siblings.length > 1 ? ` ${siblings.indexOf(field) + 1}` : "";
};

/** Where a verified value goes, in words. */
function routeNote(field: ExtractedField): string {
  const route = field.route ?? "record";
  if (route === "entity") return t("archive.ocr.route.entity", { record: t(`archive.ocr.targetRecord.${(field.target ?? "").split(".")[0] || "other"}`) });
  return t(`archive.ocr.route.${route}`);
}
function provenance(field: ExtractedField): string {
  const parts: string[] = [];
  if (field.source_page !== null) parts.push(t("archive.ocr.provenance.page", { page: field.source_page }));
  parts.push(t(`archive.ocr.provenance.method.${field.extraction_method}`));
  if (field.form_field_id) parts.push(t("archive.ocr.provenance.formField"));
  return parts.join(" · ");
}
const STATUS_LABEL: Record<ExtractedField["status"], string> = {
  pending: "archive.ocr.fieldStatus.pending",
  edited: "archive.ocr.fieldStatus.edited",
  accepted: "archive.ocr.fieldStatus.accepted",
  rejected: "archive.ocr.fieldStatus.rejected",
  uncertain: "archive.ocr.fieldStatus.uncertain",
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
      <div v-for="f in fields" :key="f.id" class="border-b border-line py-2.5 text-sm" data-testid="ocr-field-row">
        <div class="flex flex-wrap items-center gap-3">
          <span class="w-40 shrink-0 text-ink-muted" data-testid="field-label">{{ fieldLabel(f) }}{{ itemNumber(f) }}</span>

          <div v-if="editingId === f.id" class="flex flex-1 items-center gap-2">
            <input v-model="draftValue" type="text" dir="auto" class="flex-1 rounded-md border border-ink px-2 py-1 text-sm" data-testid="edit-input" />
            <button type="button" class="rounded-md bg-ink px-2 py-1 text-xs text-paper" data-testid="save-edit" @click="saveEdit(f.id)">{{ t("archive.ocr.save") }}</button>
            <button type="button" class="text-xs text-ink-muted hover:text-ink" @click="editingId = null">{{ t("archive.ocr.cancel") }}</button>
          </div>
          <span v-else-if="currentValue(f) === null" class="flex-1 text-warn" data-testid="field-needs-transcription">{{ t("archive.ocr.needsTranscription") }}</span>
          <span v-else class="flex-1 whitespace-pre-line font-medium text-ink" dir="auto" data-testid="field-value">{{ currentValue(f) }}</span>

          <!-- A person's transcription has no machine confidence to show. -->
          <span v-if="f.extraction_method === 'manually_transcribed'" class="w-24 shrink-0 text-xs text-ink-muted" data-testid="field-confidence">{{ t("archive.ocr.typedByReviewer") }}</span>
          <span v-else class="flex w-24 shrink-0 items-center gap-2" data-testid="field-confidence">
            <span class="h-1 flex-1 overflow-hidden rounded-full bg-neutral-soft"><span class="block h-full rounded-full" :class="CONFIDENCE_COLOR(f.confidence)" :style="{ width: `${f.confidence}%` }" /></span>
            <span class="font-mono text-xs tabular-nums">{{ f.confidence }}%</span>
          </span>

          <span class="w-24 shrink-0 text-xs text-ink-muted" data-testid="field-status">{{ t(STATUS_LABEL[f.status]) }}</span>

          <span v-if="canReview && editingId !== f.id && uncertainId !== f.id && isOpen(f) && f.route !== 'artist_contact'" class="flex shrink-0 gap-1.5">
            <button
              v-if="currentValue(f) !== null"
              type="button"
              class="rounded-sm border border-ink px-2 py-1 text-xs"
              data-testid="accept-field"
              @click="emit('accept', f.id)"
            >
              {{ isRecordField(f) ? t("archive.ocr.accept") : t("archive.ocr.verify") }}
            </button>
            <button type="button" class="rounded-sm border border-line px-2 py-1 text-xs hover:border-ink" data-testid="edit-field" @click="startEdit(f)">{{ t("archive.ocr.edit") }}</button>
            <button type="button" class="rounded-sm border border-line px-2 py-1 text-xs text-danger hover:border-danger" data-testid="reject-field" @click="emit('reject', f.id)">{{ t("archive.ocr.reject") }}</button>
            <button
              v-if="f.status !== 'uncertain'"
              type="button"
              class="rounded-sm border border-line px-2 py-1 text-xs text-warn hover:border-warn"
              data-testid="uncertain-field"
              @click="startUncertain(f)"
            >
              {{ t("archive.ocr.markUncertain") }}
            </button>
          </span>
        </div>

        <div v-if="uncertainId === f.id" class="mt-2 flex flex-wrap items-center gap-2" data-testid="uncertain-form">
          <input
            v-model="uncertainNote"
            type="text"
            class="min-w-0 flex-1 rounded-md border border-line px-2 py-1 text-xs"
            :placeholder="t('archive.ocr.uncertainPlaceholder')"
            data-testid="uncertain-note"
          />
          <button type="button" class="rounded-md bg-warn px-2 py-1 text-xs text-surface" data-testid="save-uncertain" @click="saveUncertain(f.id)">{{ t("archive.ocr.markUncertain") }}</button>
          <button type="button" class="text-xs text-ink-muted hover:text-ink" @click="uncertainId = null">{{ t("archive.ocr.cancel") }}</button>
        </div>

        <!-- Still to be read off the document: the crop, and a box to type what it says. -->
        <div v-if="canReview && currentValue(f) === null && f.form_field_id && f.route !== 'artist_contact'" class="mt-2 flex flex-wrap items-center gap-2" data-testid="field-transcribe">
          <input
            v-model="transcription[f.id]"
            type="text"
            dir="auto"
            class="min-w-0 flex-1 rounded-md border border-ink px-2 py-1 text-sm"
            :placeholder="t('archive.ocr.formFields.transcribePlaceholder')"
            data-testid="field-transcribe-input"
            @keyup.enter="submitTranscription(f)"
          />
          <button type="button" class="rounded-md bg-ink px-2 py-1 text-xs text-paper" data-testid="field-transcribe-submit" @click="submitTranscription(f)">{{ t("archive.ocr.formFields.transcribeSubmit") }}</button>
        </div>

        <p v-if="f.verified_value && f.extracted_value !== f.verified_value" class="mt-1 text-xs text-ink-muted" dir="auto" data-testid="field-machine-reading">
          {{ t("archive.ocr.machineReading", { value: f.extracted_value ?? "—" }) }}
        </p>
        <p class="mt-1 text-xs text-ink-muted" data-testid="field-provenance">
          <span data-testid="field-route">{{ routeNote(f) }}</span> · {{ provenance(f) }}
        </p>
        <OcrFieldEvidence
          :field="f"
          :label="fieldLabel(f) + itemNumber(f)"
          :archive-item-id="archiveItemId"
          :can-review="canReview"
          @show-source="(focus) => emit('show-source', focus)"
          @use-suggestion="(value) => startEdit(f, value)"
        />
        <OcrEntityMatch
          v-if="f.match"
          :match="f.match"
          :can-review="canReview"
          :archive-item-id="archiveItemId"
          @confirm="(matchId, candidate) => emit('confirm-match', matchId, candidate)"
          @link="(matchId, entityId) => emit('link-match', matchId, entityId)"
          @decide="(matchId, decision) => emit('decide-match', matchId, decision)"
        />
      </div>
    </div>
  </section>
</template>
