<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch, type ComponentPublicInstance } from "vue";
import { useI18n } from "vue-i18n";
import * as pdfjsLib from "pdfjs-dist";
import type { PDFDocumentProxy, RenderTask } from "pdfjs-dist";
import pdfjsWorkerUrl from "pdfjs-dist/build/pdf.worker.min.mjs?url";

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorkerUrl;

const props = defineProps<{ url: string; name: string }>();
const { t } = useI18n();

const canvasEl = ref<HTMLCanvasElement | null>(null);
const thumbEls = new Map<number, HTMLCanvasElement>();
const pageCount = ref(0);
const currentPage = ref(1);
const scale = ref(1);
const loading = ref(true);
const loadError = ref<string | null>(null);

let pdfDoc: PDFDocumentProxy | null = null;
let renderTask: RenderTask | null = null;

function setThumbEl(el: Element | ComponentPublicInstance | null, page: number): void {

  if (el instanceof HTMLCanvasElement) thumbEls.set(page, el);
}

async function renderPage(num: number): Promise<void> {
  if (!pdfDoc || !canvasEl.value) return;
  renderTask?.cancel();
  const page = await pdfDoc.getPage(num);
  const viewport = page.getViewport({ scale: scale.value });
  const canvas = canvasEl.value;
  canvas.width = viewport.width;
  canvas.height = viewport.height;
  const ctx = canvas.getContext("2d");
  if (!ctx) return;
  renderTask = page.render({ canvasContext: ctx, viewport });
  try {
    await renderTask.promise;
  } catch (err) {
    if (!(err instanceof Error && err.name === "RenderingCancelledException")) throw err;
  }
}

async function renderThumbnails(doc: PDFDocumentProxy): Promise<void> {
  for (let n = 1; n <= doc.numPages; n++) {
    if (doc !== pdfDoc) return;
    const canvas = thumbEls.get(n);
    if (!canvas) continue;
    const page = await doc.getPage(n);
    const viewport = page.getViewport({ scale: 0.2 });
    canvas.width = viewport.width;
    canvas.height = viewport.height;
    const ctx = canvas.getContext("2d");
    if (!ctx) continue;
    await page.render({ canvasContext: ctx, viewport }).promise;
  }
}

async function load(): Promise<void> {
  loading.value = true;
  loadError.value = null;
  pageCount.value = 0;
  currentPage.value = 1;
  try {
    const doc = await pdfjsLib.getDocument(props.url).promise;
    pdfDoc = doc;
    pageCount.value = doc.numPages;
    loading.value = false;
    await renderPage(1);
    void renderThumbnails(doc);
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : t("archive.viewer.pdfLoadError");
    loading.value = false;
  }
}

function goTo(num: number): void {
  const next = Math.min(Math.max(1, num), pageCount.value);
  if (next === currentPage.value) return;
  currentPage.value = next;
  void renderPage(next);
}
function zoomIn(): void {
  scale.value = Math.min(3, Math.round((scale.value + 0.25) * 100) / 100);
  void renderPage(currentPage.value);
}
function zoomOut(): void {
  scale.value = Math.max(0.5, Math.round((scale.value - 0.25) * 100) / 100);
  void renderPage(currentPage.value);
}
function resetZoom(): void {
  scale.value = 1;
  void renderPage(currentPage.value);
}

watch(() => props.url, load);
onMounted(load);
onBeforeUnmount(() => {
  renderTask?.cancel();
  void pdfDoc?.destroy();
});
</script>

<template>
  <div class="flex h-full min-h-0 flex-col bg-ink text-paper">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-2.5 text-sm">
      <div class="flex items-center gap-2">
        <button type="button" class="rounded border border-white/20 px-2.5 py-1.5 text-xs font-medium hover:bg-white/10 disabled:opacity-40" :disabled="currentPage <= 1" :aria-label="t('archive.viewer.prevPage')" @click="goTo(currentPage - 1)">‹</button>
        <span class="tabular-nums" data-testid="pdf-page-indicator">{{ t("archive.viewer.pageOf", { current: currentPage, total: pageCount }) }}</span>
        <button type="button" class="rounded border border-white/20 px-2.5 py-1.5 text-xs font-medium hover:bg-white/10 disabled:opacity-40" :disabled="currentPage >= pageCount" :aria-label="t('archive.viewer.nextPage')" @click="goTo(currentPage + 1)">›</button>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" class="rounded border border-white/20 px-2.5 py-1.5 text-xs font-medium hover:bg-white/10" :aria-label="t('archive.viewer.zoomOut')" @click="zoomOut">−</button>
        <button type="button" class="rounded border border-white/20 px-2 py-1.5 text-xs font-medium tabular-nums hover:bg-white/10" @click="resetZoom">{{ Math.round(scale * 100) }}%</button>
        <button type="button" class="rounded border border-white/20 px-2.5 py-1.5 text-xs font-medium hover:bg-white/10" :aria-label="t('archive.viewer.zoomIn')" @click="zoomIn">+</button>
      </div>
    </div>

    <div class="flex min-h-0 flex-1">
      <div class="flex flex-1 items-start justify-center overflow-auto p-6">
        <p v-if="loadError" class="text-sm text-danger-soft" role="alert">{{ loadError }}</p>
        <p v-else-if="loading" class="text-sm text-white/70">{{ t("archive.viewer.loadingPdf") }}</p>
        <canvas v-show="!loading && !loadError" ref="canvasEl" data-testid="pdf-canvas" class="max-w-full shadow-xl" />
      </div>
      <div v-if="pageCount > 1" class="flex w-24 shrink-0 flex-col gap-2 overflow-y-auto border-s border-white/10 p-2" data-testid="pdf-thumbnails">
        <button
          v-for="n in pageCount"
          :key="n"
          type="button"
          class="rounded border-2 p-1 hover:border-white/40"
          :class="n === currentPage ? 'border-accent' : 'border-transparent'"
          :aria-current="n === currentPage"
          @click="goTo(n)"
        >
          <canvas :ref="(el) => setThumbEl(el, n)" class="w-full bg-white/5" />
          <span class="mt-1 block text-center text-[10px] text-white/60">{{ n }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
