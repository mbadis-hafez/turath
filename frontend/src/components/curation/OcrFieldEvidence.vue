<script setup lang="ts">
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";

import { ocrRegionCropUrl } from "@/api/archive";
import type { OcrSourceFocus } from "@/components/curation/OcrSourceViewer.vue";
import type { ExtractedField } from "@/types/ocr";

/**
 * One value's evidence, layer by layer, as the reviewer checks it: where on
 * the page it is, what OCR read, what the AI correction would change (a
 * suggestion — using it only fills the edit box), what the record holds now,
 * and anything that makes the reading doubtful. Nothing here decides.
 */
const props = defineProps<{
  field: ExtractedField;
  label: string;
  archiveItemId: number;
  canReview: boolean;
}>();
const emit = defineEmits<{
  "show-source": [focus: OcrSourceFocus];
  "use-suggestion": [value: string];
}>();
const { t } = useI18n();

const LOW_CONFIDENCE = 50;
const brokenCrop = ref(false);

const region = computed(() => props.field.source_region ?? null);
const page = computed(() => region.value?.page_number ?? props.field.source_page);
const ocrReading = computed(() => props.field.original_ocr_text ?? region.value?.ocr_text ?? null);
const correction = computed(() => props.field.ai_correction ?? null);
const isOpen = computed(() => ["pending", "edited", "uncertain"].includes(props.field.status));
const currentValue = computed(() => props.field.verified_value ?? props.field.extracted_value);
const recordValue = computed(() => props.field.current_record_value ?? null);
const replacesRecord = computed(() => recordValue.value !== null && recordValue.value !== currentValue.value);
const isHandTyped = computed(() => props.field.extraction_method === "manually_transcribed");

/** Everything that makes this reading doubtful, so it isn't trusted by default. */
const doubts = computed(() => {
  const out: string[] = [];
  const f = props.field;
  if (!isHandTyped.value && f.extracted_value !== null && f.confidence < LOW_CONFIDENCE) out.push(t("archive.ocr.evidence.doubts.lowConfidence", { value: f.confidence }));
  if (region.value?.has_correction_mark) out.push(t("archive.ocr.evidence.doubts.correctionMark"));
  if (correction.value?.needs_review) out.push(t("archive.ocr.evidence.doubts.aiFlagged"));
  if (f.extraction_method === "ai_inferred") out.push(t("archive.ocr.evidence.doubts.aiInferred"));
  if (region.value === null && !f.form_field_id && f.extracted_value !== null) out.push(t("archive.ocr.evidence.doubts.wholePage"));
  return out;
});

function showSource(): void {
  if (page.value === null) return;
  emit("show-source", { page: page.value, bbox: region.value?.bbox ?? null, label: props.label });
}
</script>

<template>
  <div class="mt-2 space-y-1.5 rounded-md border border-line bg-surface p-2 text-xs" data-testid="field-evidence">
    <!-- Where on the document -->
    <div v-if="page !== null || region" class="flex flex-wrap items-start gap-3">
      <img
        v-if="region?.has_crop && !brokenCrop"
        :src="ocrRegionCropUrl(archiveItemId, region.id)"
        :alt="label"
        class="max-h-12 rounded-sm border border-line bg-neutral-soft"
        data-testid="field-evidence-crop"
        @error="brokenCrop = true"
      />
      <button v-if="page !== null" type="button" class="text-accent underline hover:text-accent-strong" data-testid="field-evidence-show-source" @click="showSource">
        {{ region ? t("archive.ocr.evidence.showRegion", { page }) : t("archive.ocr.evidence.showPage", { page }) }}
      </button>
    </div>

    <!-- What OCR read -->
    <p v-if="ocrReading" class="text-ink-muted" data-testid="field-evidence-ocr">
      {{ t("archive.ocr.evidence.ocrRead") }} <span class="text-ink" dir="auto">{{ ocrReading }}</span>
    </p>
    <p v-else-if="isHandTyped" class="text-ink-muted" data-testid="field-evidence-ocr">{{ t("archive.ocr.evidence.noOcr") }}</p>

    <!-- What the AI correction would change -->
    <div v-if="correction" class="text-ink-muted" data-testid="field-evidence-ai">
      <template v-if="correction.suggested_value">
        <p>
          {{ t("archive.ocr.evidence.aiSuggests") }}
          <span class="font-medium text-ink" dir="auto" data-testid="field-evidence-ai-suggestion">{{ correction.suggested_value }}</span>
          <button
            v-if="canReview && isOpen && field.route !== 'artist_contact'"
            type="button"
            class="ms-2 rounded-sm border border-ink px-1.5 py-0.5 text-ink"
            data-testid="field-evidence-use-suggestion"
            @click="emit('use-suggestion', correction.suggested_value)"
          >
            {{ t("archive.ocr.evidence.useSuggestion") }}
          </button>
        </p>
        <ul class="mt-0.5 ps-3">
          <li v-for="(c, i) in correction.changes" :key="i" data-testid="field-evidence-ai-change">
            <span dir="auto">{{ c.original }}</span> → <span dir="auto">{{ c.corrected }}</span>
            <template v-if="c.type"> ({{ t(`archive.ocr.evidence.changeTypes.${c.type}`, c.type) }})</template>
          </li>
        </ul>
      </template>
      <p v-else-if="correction.status === 'rejected'">{{ t("archive.ocr.evidence.aiUnusable") }}</p>
      <p v-else>{{ t("archive.ocr.evidence.aiNoChange") }}</p>
      <p v-for="(n, i) in correction.name_candidates" :key="`n${i}`" data-testid="field-evidence-ai-name">
        {{ t("archive.ocr.evidence.aiNameReading", { ocr: n.ocr_text, candidate: n.candidate }) }}
      </p>
      <p class="text-ink-faint" data-testid="field-evidence-ai-model">
        {{ [correction.provider, correction.model, correction.model_version, t("archive.ocr.evidence.promptVersion", { version: correction.prompt_version })].filter(Boolean).join(" · ") }}
      </p>
    </div>

    <!-- What the record holds now -->
    <p v-if="replacesRecord" class="text-warn" data-testid="field-evidence-record">
      {{ t("archive.ocr.evidence.recordNow", { value: recordValue }) }}
    </p>
    <p v-else-if="recordValue !== null" class="text-ink-muted" data-testid="field-evidence-record">{{ t("archive.ocr.evidence.recordSame") }}</p>

    <!-- Doubts -->
    <ul v-if="doubts.length > 0" class="space-y-0.5 text-warn" data-testid="field-evidence-doubts">
      <li v-for="d in doubts" :key="d">{{ d }}</li>
    </ul>
    <p v-if="field.status === 'uncertain'" class="text-warn" data-testid="field-evidence-uncertain">
      {{ field.review_note ? t("archive.ocr.evidence.uncertainNote", { note: field.review_note }) : t("archive.ocr.evidence.uncertain") }}
    </p>
  </div>
</template>
