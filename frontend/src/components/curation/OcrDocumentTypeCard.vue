<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { DocumentSchemaField, DocumentType } from "@/types/ocr";

const props = defineProps<{
  documentType: string | null;
  source: "reviewer" | "classifier" | null;
  schema: DocumentSchemaField[];
  canReview: boolean;
}>();
const emit = defineEmits<{ "set-type": [documentType: DocumentType | null] }>();
const { t, locale } = useI18n();

const TYPES: DocumentType[] = ["artist_authorization", "artwork_condition_report", "artist_biography", "exhibition_document", "unknown"];

// A linked record (the exhibition an invitation is about) is chosen by matching, not read off the page.
const expected = computed(() => props.schema.filter((f) => f.kind !== "link"));
const missing = computed(() => expected.value.filter((f) => !f.found).map((f) => (locale.value === "ar" ? f.label.ar : f.label.en)));

function onSelect(event: Event): void {
  emit("set-type", (event.target as HTMLSelectElement).value as DocumentType);
}
</script>

<template>
  <section class="rounded-lg border border-line p-4" data-testid="ocr-document-type">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t("archive.ocr.documentType.title") }}</h2>
        <p class="mt-1 text-sm font-medium text-ink" data-testid="ocr-document-type-label">{{ t(`archive.ocr.documentType.types.${documentType ?? "unknown"}`) }}</p>
        <p v-if="source" class="text-xs text-ink-muted" data-testid="ocr-document-type-source">{{ t(`archive.ocr.documentType.source.${source}`) }}</p>
      </div>
      <div v-if="canReview" class="flex items-center gap-2">
        <label class="sr-only" for="ocr-document-type-select">{{ t("archive.ocr.documentType.change") }}</label>
        <select
          id="ocr-document-type-select"
          class="rounded-md border border-line px-2 py-1 text-sm"
          :value="documentType ?? 'unknown'"
          data-testid="ocr-document-type-select"
          @change="onSelect"
        >
          <option v-for="type in TYPES" :key="type" :value="type">{{ t(`archive.ocr.documentType.types.${type}`) }}</option>
        </select>
        <button
          v-if="source === 'reviewer'"
          type="button"
          class="text-xs font-medium text-ink-muted underline hover:text-ink"
          data-testid="ocr-document-type-reset"
          @click="emit('set-type', null)"
        >
          {{ t("archive.ocr.documentType.reset") }}
        </button>
      </div>
    </div>

    <div v-if="expected.length > 0" class="mt-3 border-t border-line pt-3 text-xs text-ink-muted" data-testid="ocr-document-schema">
      <p>{{ t("archive.ocr.documentType.found", { found: expected.length - missing.length, total: expected.length }) }}</p>
      <p v-if="missing.length > 0" class="mt-1" data-testid="ocr-document-schema-missing">{{ t("archive.ocr.documentType.notFound", { fields: missing.join("، ") }) }}</p>
    </div>
  </section>
</template>
