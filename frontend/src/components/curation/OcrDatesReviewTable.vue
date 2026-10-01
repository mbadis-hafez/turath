<script setup lang="ts">
import { useI18n } from "vue-i18n";

import type { DocumentSchemaField, ExtractedDate } from "@/types/ocr";

const props = defineProps<{
  dates: ExtractedDate[];
  schema: DocumentSchemaField[];
  canReview: boolean;
}>();
const emit = defineEmits<{
  review: [dateId: number, decision: "accept" | "reject"];
  /** Show the date where it is on the page: its printed region if it has one, else the page. */
  "show-source": [regionId: number | null, page: number, label: string];
}>();
const { t, locale } = useI18n();

/** The document field a date fills ("Opening date"), when there is one; otherwise its role. */
function roleLabel(date: ExtractedDate): string {
  const field = date.field_key ? props.schema.find((f) => f.key === date.field_key) : undefined;
  if (field) return locale.value === "ar" ? field.label.ar : field.label.en;
  return t(`archive.ocr.dates.role.${date.date_type}`);
}
</script>

<template>
  <section data-testid="ocr-dates-table">
    <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t("archive.ocr.dates.title") }}</h2>
    <p class="mt-2 text-xs text-ink-muted">{{ t("archive.ocr.dates.help") }}</p>

    <ul class="mt-3 space-y-2">
      <li v-for="d in dates" :key="d.id" class="flex flex-wrap items-center gap-3 border-b border-line py-2 text-sm" data-testid="ocr-date-row">
        <span class="w-40 shrink-0 text-ink-muted" data-testid="date-role">{{ roleLabel(d) }}</span>
        <span class="flex-1">
          <span class="font-medium text-ink" dir="auto" data-testid="date-value">{{ d.value }}</span>
          <span class="ms-2 text-xs text-ink-muted" data-testid="date-calendar">{{ t(`archive.ocr.dates.calendar.${d.calendar}`) }}<template v-if="d.normalized"> · {{ d.normalized }}</template></span>
          <span class="block text-xs text-ink-muted" data-testid="date-source">
            <template v-if="d.source_page !== null">{{ t("archive.ocr.provenance.page", { page: d.source_page }) }} · </template>{{ d.region_id ? t("archive.ocr.dates.printedRegion") : t("archive.ocr.dates.pageTextOnly") }}
            <button
              v-if="d.source_page !== null"
              type="button"
              class="ms-2 text-accent underline"
              data-testid="date-show-source"
              @click="emit('show-source', d.region_id ?? null, d.source_page, roleLabel(d))"
            >
              {{ d.region_id ? t("archive.ocr.evidence.showRegion", { page: d.source_page }) : t("archive.ocr.evidence.showPage", { page: d.source_page }) }}
            </button>
          </span>
        </span>
        <span class="w-24 shrink-0 text-xs text-ink-muted" data-testid="date-status">{{ t(`archive.ocr.fieldStatus.${d.status ?? "pending"}`) }}</span>
        <span v-if="canReview && (d.status ?? 'pending') === 'pending'" class="flex shrink-0 gap-1.5">
          <button type="button" class="rounded-sm border border-ink px-2 py-1 text-xs" data-testid="accept-date" @click="emit('review', d.id, 'accept')">{{ t("archive.ocr.verify") }}</button>
          <button type="button" class="rounded-sm border border-line px-2 py-1 text-xs text-danger hover:border-danger" data-testid="reject-date" @click="emit('review', d.id, 'reject')">{{ t("archive.ocr.reject") }}</button>
        </span>
      </li>
    </ul>
  </section>
</template>
