<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import type { OcrPageText } from "@/types/ocr";

const props = defineProps<{
  texts: { ar: OcrPageText[]; en: OcrPageText[] };
}>();
const { t } = useI18n();

const LOW_CONFIDENCE = 70;

const availableLangs = computed(() => (["ar", "en"] as const).filter((l) => props.texts[l].length > 0));
const activeLang = ref<"ar" | "en">(availableLangs.value[0] ?? "ar");
watch(availableLangs, (langs) => {
  if (!langs.includes(activeLang.value)) activeLang.value = langs[0] ?? "ar";
});

const langConfidence = (lang: "ar" | "en"): number | null => {
  const pages = props.texts[lang];
  if (pages.length === 0) return null;
  return Math.round(pages.reduce((sum, p) => sum + p.confidence, 0) / pages.length);
};

const pages = computed(() => props.texts[activeLang.value]);
const activePage = ref(1);
watch(pages, (p) => {
  if (!p.some((page) => page.page === activePage.value)) activePage.value = p[0]?.page ?? 1;
});
const currentPage = computed(() => pages.value.find((p) => p.page === activePage.value) ?? null);

const query = ref("");
const normalizedQuery = computed(() => query.value.trim().toLowerCase());
const matchCount = computed(() => {
  if (normalizedQuery.value === "" || !currentPage.value) return 0;
  return currentPage.value.segments.filter((s) => s.text.toLowerCase().includes(normalizedQuery.value)).length;
});

function segmentClass(segmentText: string, confidence: number): string {
  const classes: string[] = [];
  if (normalizedQuery.value !== "" && segmentText.toLowerCase().includes(normalizedQuery.value)) classes.push("bg-warn-soft");
  if (confidence < LOW_CONFIDENCE) classes.push("underline decoration-dotted decoration-danger underline-offset-4");
  return classes.join(" ");
}

async function copyText(): Promise<void> {
  if (!currentPage.value) return;
  try {
    await navigator.clipboard.writeText(currentPage.value.text);
  } catch {
    // Clipboard access can be denied by the browser; there's nothing useful to recover into here.
  }
}
</script>

<template>
  <div v-if="availableLangs.length > 0" class="rounded-lg border border-line" data-testid="ocr-text-panel">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line p-3">
      <div class="flex gap-0 border-b border-transparent">
        <button
          v-for="lang in availableLangs"
          :key="lang"
          type="button"
          class="-mb-px border-b-2 px-3 py-1.5 text-sm font-semibold"
          :class="lang === activeLang ? 'border-accent text-ink' : 'border-transparent text-ink-muted hover:text-ink'"
          :aria-pressed="lang === activeLang"
          data-testid="ocr-lang-tab"
          @click="activeLang = lang"
        >
          {{ t(`archive.ocr.language.${lang}`) }}
          <span class="ms-1 font-mono text-xs font-normal text-ink-muted">{{ langConfidence(lang) }}%</span>
        </button>
      </div>
      <div v-if="pages.length > 1" class="flex items-center gap-2 text-xs">
        <button type="button" class="rounded border border-line px-2 py-1 disabled:opacity-40" :disabled="activePage <= pages[0]!.page" data-testid="ocr-text-prev" @click="activePage = pages[pages.findIndex((p) => p.page === activePage) - 1]!.page">‹</button>
        <span class="tabular-nums">{{ t("archive.viewer.pageOf", { current: activePage, total: pages[pages.length - 1]!.page }) }}</span>
        <button type="button" class="rounded border border-line px-2 py-1 disabled:opacity-40" :disabled="activePage >= pages[pages.length - 1]!.page" data-testid="ocr-text-next" @click="activePage = pages[pages.findIndex((p) => p.page === activePage) + 1]!.page">›</button>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 border-b border-line p-3">
      <input v-model="query" type="search" class="min-w-0 flex-1 rounded-md border border-line px-2 py-1.5 text-sm" :placeholder="t('archive.ocr.searchPlaceholder')" data-testid="ocr-text-search" />
      <span v-if="normalizedQuery" class="text-xs text-ink-muted">{{ t("archive.ocr.matchCount", { count: matchCount }) }}</span>
      <button type="button" class="rounded-md border border-line px-2.5 py-1.5 text-xs font-medium hover:border-ink" @click="copyText">{{ t("archive.ocr.copy") }}</button>
    </div>

    <p v-if="currentPage" :dir="activeLang === 'ar' ? 'rtl' : 'ltr'" class="p-4 text-sm leading-loose" data-testid="ocr-text-body">
      <span v-for="(seg, i) in currentPage.segments" :key="i" :class="segmentClass(seg.text, seg.confidence)">{{ `${seg.text} ` }}</span>
    </p>

    <div class="flex flex-wrap gap-4 border-t border-line p-3 text-xs text-ink-muted">
      <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 bg-warn-soft" /> {{ t("archive.ocr.legendMatch") }}</span>
      <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 border-b-2 border-dotted border-danger" /> {{ t("archive.ocr.legendLowConfidence") }}</span>
    </div>
  </div>
</template>
