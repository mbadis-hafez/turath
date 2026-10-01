<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { ocrPageImageUrl } from "@/api/archive";
import type { OcrRegion, OcrRegionType } from "@/types/ocr";

type Box = { x: number; y: number; width: number; height: number };
/** What the reviewer asked to see: a page, and the box on it a value came from. */
export interface OcrSourceFocus {
  page: number;
  bbox: Box | null;
  label: string | null;
}

/**
 * The page as OCR saw it, with the region a value was read from outlined —
 * so a reviewer checks every reading against the document itself. Boxes are
 * in the rendered page's pixels; they're drawn as percentages of the image's
 * natural size, so they stay in place at any display size.
 */
const props = defineProps<{
  archiveItemId: number;
  pageCount: number;
  regions: OcrRegion[];
  focus: OcrSourceFocus | null;
}>();
const { t } = useI18n();

const page = ref(props.focus?.page ?? 1);
const natural = ref<{ width: number; height: number } | null>(null);
const failed = ref(false);
const showAll = ref(false);

watch(() => props.focus, (focus) => {
  if (focus) page.value = focus.page;
});
watch(page, () => {
  natural.value = null;
  failed.value = false;
});

function onLoad(event: Event): void {
  const img = event.target as HTMLImageElement;
  natural.value = img.naturalWidth > 0 ? { width: img.naturalWidth, height: img.naturalHeight } : null;
}

const focusBox = computed(() => (props.focus && props.focus.page === page.value ? props.focus.bbox : null));
const pageRegions = computed(() => props.regions.filter((r) => r.page_number === page.value));

function boxStyle(box: Box): Record<string, string> {
  const size = natural.value;
  if (!size) return { display: "none" };
  const pct = (v: number, of: number) => `${Math.max(0, Math.min(100, (v / of) * 100))}%`;
  return { left: pct(box.x, size.width), top: pct(box.y, size.height), width: pct(box.width, size.width), height: pct(box.height, size.height) };
}

const REGION_CLASS: Partial<Record<OcrRegionType, string>> = {
  printed_text: "border-accent",
  form_label: "border-accent",
  form_value: "border-info",
  handwriting: "border-warn",
  signature: "border-danger",
  logo: "border-ink-faint",
  photograph: "border-ink-faint",
};
</script>

<template>
  <section class="rounded-lg border border-line" data-testid="ocr-source-viewer">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-3 py-2 text-xs">
      <h2 class="font-semibold uppercase tracking-wide text-ink-muted">{{ t("archive.ocr.source.title") }}</h2>
      <div class="flex items-center gap-2">
        <label class="flex items-center gap-1 text-ink-muted">
          <input v-model="showAll" type="checkbox" data-testid="ocr-source-show-all" />
          {{ t("archive.ocr.source.showAll") }}
        </label>
        <template v-if="pageCount > 1">
          <button type="button" class="rounded border border-line px-2 py-0.5 disabled:opacity-40" :disabled="page <= 1" data-testid="ocr-source-prev" @click="page--">‹</button>
          <span class="tabular-nums" data-testid="ocr-source-page-label">{{ t("archive.viewer.pageOf", { current: page, total: pageCount }) }}</span>
          <button type="button" class="rounded border border-line px-2 py-0.5 disabled:opacity-40" :disabled="page >= pageCount" data-testid="ocr-source-next" @click="page++">›</button>
        </template>
      </div>
    </div>

    <p v-if="focus?.label && focus.page === page" class="border-b border-line px-3 py-1.5 text-xs text-ink" data-testid="ocr-source-focus-label">
      {{ t("archive.ocr.source.showing", { label: focus.label }) }}
    </p>

    <p v-if="failed" class="p-4 text-sm text-ink-muted" data-testid="ocr-source-unavailable">{{ t("archive.ocr.source.unavailable") }}</p>
    <div v-else class="relative" data-testid="ocr-source-canvas">
      <img
        :key="page"
        :src="ocrPageImageUrl(archiveItemId, page)"
        :alt="t('archive.ocr.source.pageAlt', { page })"
        class="block h-auto w-full"
        data-testid="ocr-source-page"
        @load="onLoad"
        @error="failed = true"
      />
      <template v-if="showAll">
        <div
          v-for="r in pageRegions"
          :key="r.id"
          class="pointer-events-none absolute border"
          :class="REGION_CLASS[r.region_type] ?? 'border-line'"
          :style="boxStyle(r.bbox)"
          :title="t(`archive.ocr.source.regionTypes.${r.region_type}`)"
          data-testid="ocr-source-region"
        />
        <template v-for="r in pageRegions" :key="`marks-${r.id}`">
          <div
            v-for="(m, i) in r.correction_marks ?? []"
            :key="i"
            class="pointer-events-none absolute border-2 border-dashed border-danger"
            :style="boxStyle(m.bbox)"
            data-testid="ocr-source-correction-mark"
          />
        </template>
      </template>
      <div v-if="focusBox" class="pointer-events-none absolute border-2 border-accent bg-accent/15 ring-2 ring-accent/30" :style="boxStyle(focusBox)" data-testid="ocr-source-focus" />
    </div>

    <p v-if="showAll" class="flex flex-wrap gap-3 border-t border-line px-3 py-2 text-xs text-ink-muted">
      <span class="inline-flex items-center gap-1"><span class="inline-block size-3 border border-accent" />{{ t("archive.ocr.source.legend.printed") }}</span>
      <span class="inline-flex items-center gap-1"><span class="inline-block size-3 border border-warn" />{{ t("archive.ocr.source.legend.handwriting") }}</span>
      <span class="inline-flex items-center gap-1"><span class="inline-block size-3 border border-danger" />{{ t("archive.ocr.source.legend.signature") }}</span>
      <span class="inline-flex items-center gap-1"><span class="inline-block size-3 border-2 border-dashed border-danger" />{{ t("archive.ocr.source.legend.correction") }}</span>
    </p>
  </section>
</template>
