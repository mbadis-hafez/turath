<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { fetchSubjectActivity } from "@/api/activity";
import { getAdminArchiveItem } from "@/api/archive";
import ActivityTimeline from "@/components/activity/ActivityTimeline.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Spinner from "@/components/common/Spinner.vue";
import Tabs, { type TabItem } from "@/components/common/Tabs.vue";
import ArchiveDeleteDialog from "@/components/curation/ArchiveDeleteDialog.vue";
import ArchiveTypeIcon from "@/components/curation/ArchiveTypeIcon.vue";
import OcrFieldsReviewTable from "@/components/curation/OcrFieldsReviewTable.vue";
import OcrStatusCard from "@/components/curation/OcrStatusCard.vue";
import OcrTextPanel from "@/components/curation/OcrTextPanel.vue";
// pdf.js is a heavy dependency (~300KB) that most archive items never need — load it
// only once a file actually turns out to be a PDF.
const PdfViewer = defineAsyncComponent(() => import("@/components/curation/PdfViewer.vue"));
import { useFileOcr } from "@/composables/useFileOcr";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type { ActivityEntry } from "@/types/activity";
import type { ArchiveEdit } from "@/types/archive";
import { formatRelativeTime } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("archive.manage"));
const id = computed(() => Number(route.params.id));

const { bundle: ocrBundle, actionError: ocrActionError, pendingCount: ocrPendingCount, averageConfidence: ocrAverageConfidence, accept: acceptField, reject: rejectField, edit: editField, acceptHighConfidence, runOcr } = useFileOcr(id);

const item = ref<ArchiveEdit | null>(null);
const loading = ref(false);
const loadError = ref<unknown>(null);
let controller: AbortController | null = null;

async function loadItem(): Promise<void> {
  controller?.abort();
  const self = new AbortController();
  controller = self;
  loading.value = item.value === null;
  loadError.value = null;
  try {
    const response = await getAdminArchiveItem(id.value, self.signal);
    if (controller !== self) return;
    item.value = response.data;
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    loadError.value = err;
  } finally {
    if (controller === self) loading.value = false;
  }
}
watch(id, () => void loadItem(), { immediate: true });
onBeforeUnmount(() => controller?.abort());

const filePreview = computed(() => {
  const f = item.value?.file;
  if (!f) return null;
  return {
    name: f.name ?? "",
    size: f.size_bytes,
    url: f.url,
    isImage: f.is_image,
    isPdf: f.mime_type === "application/pdf",
    isVideo: f.mime_type.startsWith("video/"),
    isAudio: f.mime_type.startsWith("audio/"),
    dims: f.width_px ? `${f.width_px} × ${f.height_px}` : null,
  };
});
const formatSize = (b: number) => (b >= 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

const statusKey = computed(() => (item.value?.under_review ? "under_review" : item.value?.publication_status ?? "draft"));
const unpublished = computed(() => item.value !== null && (item.value.publication_status !== "published" || item.value.under_review));
const missingChecklist = computed(() => item.value?.checklist.filter((c) => !c.met) ?? []);

const metaRows = computed(() => {
  const i = item.value;
  if (!i) return [];
  return [
    { key: "date", label: t("archive.edit.date"), value: i.content?.display ?? "—" },
    { key: "place", label: t("archive.edit.place"), value: pick(i.place)?.text || "—" },
    { key: "sourceName", label: t("archive.edit.sourceName"), value: i.source_name ?? "—" },
    { key: "rightsHolder", label: t("archive.edit.rightsHolder"), value: pick(i.rights_holder)?.text || "—" },
    { key: "rightsStatus", label: t("archive.edit.rightsStatus"), value: t(`archive.admin.rightsOptions.${i.rights_status}`) },
    { key: "license", label: t("archive.edit.license"), value: i.license ?? "—" },
    { key: "verification", label: t("archive.edit.verification"), value: i.verification_reference ?? "—" },
    { key: "digitizedAt", label: t("archive.edit.digitizedAt"), value: i.digitized_at ?? "—" },
    { key: "access", label: t("archive.edit.access"), value: t(`archive.edit.accessOptions.${i.access_level}.label`) },
  ];
});

const ROUTE_FOR_KIND: Record<string, string> = {
  artist: "admin.artists.show",
  artwork: "admin.artworks.show",
  event: "admin.events.edit",
};
function linkTarget(kind: string, entityId: number) {
  const name = ROUTE_FOR_KIND[kind];
  return name ? localePath(name, { id: entityId }) : null;
}

const showDeleteDialog = ref(false);
async function onDeleted(): Promise<void> {
  showDeleteDialog.value = false;
  await router.push(localePath("admin.archive"));
}

const linkCopied = ref(false);
async function copyLink(): Promise<void> {
  try {
    await navigator.clipboard.writeText(window.location.href);
    linkCopied.value = true;
    setTimeout(() => (linkCopied.value = false), 2000);
  } catch {
    // Clipboard access can be denied by the browser; there's nothing useful to recover into here.
  }
}

const hasOcr = computed(() => ocrBundle.value !== null && ocrBundle.value.status !== null);
const hasOcrText = computed(() => ocrBundle.value !== null && (ocrBundle.value.texts.ar.length > 0 || ocrBundle.value.texts.en.length > 0));
const canRunOcr = computed(() => ocrBundle.value !== null && ocrBundle.value.status === null && (filePreview.value?.isPdf || filePreview.value?.isImage) === true);
const ocrPageCount = computed(() => {
  const b = ocrBundle.value;
  if (!b) return 0;
  return Math.max(b.texts.ar.length, b.texts.en.length);
});

const sideBySide = ref(true);
const viewerTab = ref<"document" | "text">("document");

const activeTab = ref("metadata");
const tabsSection = ref<HTMLElement | null>(null);
function viewExtractedFields(): void {
  activeTab.value = "aiExtraction";
  tabsSection.value?.scrollIntoView?.({ behavior: "smooth", block: "start" });
}
const tabs = computed<TabItem[]>(() => {
  const list: TabItem[] = [
    { key: "metadata", label: t("archive.tabs.metadata") },
    { key: "relations", label: t("archive.tabs.relations") },
  ];
  if (hasOcr.value) list.push({ key: "aiExtraction", label: t("archive.tabs.aiExtraction") });
  list.push({ key: "activity", label: t("archive.tabs.activity") });
  return list;
});

const activity = ref<ActivityEntry[]>([]);
const activityLoading = ref(false);
const activityLoaded = ref(false);
const activityError = ref<string | null>(null);
async function loadActivity(): Promise<void> {
  if (activityLoaded.value || item.value === null) return;
  activityLoading.value = true;
  activityError.value = null;
  try {
    const response = await fetchSubjectActivity("archive-items", item.value.id);
    activity.value = response.data;
    activityLoaded.value = true;
  } catch (err) {
    activityError.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    activityLoading.value = false;
  }
}
watch(activeTab, (tab) => {
  if (tab === "activity") void loadActivity();
});
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <ErrorState v-else-if="loadError" :error="loadError" @retry="loadItem" />
    <Spinner v-else-if="loading" class="mx-auto my-12 block" />

    <template v-else-if="item">
      <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
        <RouterLink :to="localePath('dashboard')" class="hover:text-ink">{{ t("nav.dashboard") }}</RouterLink>
        <span aria-hidden="true"> &rsaquo; </span>
        <RouterLink :to="localePath('admin.archive')" class="hover:text-ink">{{ t("archive.admin.title") }}</RouterLink>
        <span aria-hidden="true"> &rsaquo; </span>
        <span class="text-ink"><LocalizedText :text="item.title" /></span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <div class="flex flex-wrap items-center gap-2 text-xs" data-testid="badge-row">
            <span class="rounded-sm bg-neutral-soft px-1.5 py-0.5 font-medium text-ink-muted">{{ t(`archive.types.${item.item_type}`) }}</span>
            <span class="rounded-sm bg-neutral-soft px-1.5 py-0.5 font-medium text-ink-muted">{{ t(`archive.edit.accessOptions.${item.access_level}.label`) }}</span>
            <span v-if="hasOcr" class="rounded-sm bg-neutral-soft px-1.5 py-0.5 font-medium text-ink-muted" data-testid="ocr-page-badge">{{ t("archive.ocr.pagesBadge", { count: ocrPageCount }) }}</span>
            <bdi dir="ltr" class="text-ink-muted">{{ item.legacy_ref ?? `#${item.id}` }}</bdi>
          </div>
          <h1 class="mt-2 text-balance text-3xl font-semibold tracking-tight text-ink"><LocalizedText :text="item.title" /></h1>
          <p class="mt-1 text-sm text-ink-muted">
            {{ t(`archive.admin.rowStatuses.${statusKey}`) }}
            <template v-if="item.updated_at"> · {{ t("archive.edit.lastSaved", { when: formatRelativeTime(item.updated_at, locale as AppLocale) }) }}</template>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <RouterLink :to="localePath('admin.archive.edit', { id: item.id })" class="rounded-md bg-ink px-4 py-2 text-sm font-medium text-paper hover:opacity-90" data-testid="edit-link">
            {{ t("archive.admin.actions.edit") }}
          </RouterLink>
          <button type="button" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft" data-testid="share-button" @click="copyLink">
            {{ linkCopied ? t("archive.detail.linkCopied") : t("archive.detail.share") }}
          </button>
          <button type="button" class="rounded-md border border-danger px-4 py-2 text-sm font-medium text-danger hover:bg-danger-soft" data-testid="delete-button" @click="showDeleteDialog = true">
            {{ t("archive.admin.actions.delete") }}
          </button>
        </div>
      </div>

      <div class="mt-8 grid gap-10 lg:grid-cols-[1fr_18rem]">
        <aside class="space-y-6 lg:order-2">
          <div v-if="unpublished" class="rounded-md border border-warn bg-warn-soft p-4 text-sm" data-testid="unpublished-banner">
            <p class="font-semibold text-ink">{{ t("archive.detail.unpublishedTitle") }}</p>
            <p class="mt-1 text-ink-muted">{{ t("archive.detail.unpublishedHelp") }}</p>
            <p v-if="item.under_review" class="mt-1 text-ink-muted">{{ t("archive.detail.underReviewNote") }}</p>
            <p v-if="missingChecklist.length" class="mt-2 text-ink-muted">
              {{ t("archive.detail.missingFields") }}
              <span data-testid="missing-fields">{{ missingChecklist.map((c) => t(`archive.edit.checklist.${c.key}`)).join("، ") }}</span>
            </p>
          </div>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("archive.detail.basicInfoTitle") }}</h2>
            <dl class="mt-3 space-y-2 text-sm" data-testid="basic-info">
              <div v-for="row in metaRows.slice(0, 5)" :key="row.key" class="flex flex-col gap-0.5">
                <dt class="text-xs text-ink-muted">{{ row.label }}</dt>
                <dd class="text-ink">{{ row.value }}</dd>
              </div>
              <div v-if="filePreview" class="flex flex-col gap-0.5">
                <dt class="text-xs text-ink-muted">{{ t("archive.detail.size") }}</dt>
                <dd class="text-ink" dir="ltr">{{ formatSize(filePreview.size) }}</dd>
              </div>
            </dl>
          </section>

          <section v-if="item.links.length > 0">
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("archive.detail.relatedToTitle") }}</h2>
            <ul class="mt-3 space-y-2" data-testid="related-preview">
              <li v-for="l in item.links" :key="l.id ?? `${l.kind}-${l.entity_id}`" class="text-sm">
                <p class="text-xs text-ink-muted">{{ t(`archive.edit.linkRoles.${l.role}`) }}</p>
                <RouterLink v-if="linkTarget(l.kind, l.entity_id)" :to="linkTarget(l.kind, l.entity_id)!" class="font-medium text-ink hover:underline" data-testid="related-preview-link">
                  <LocalizedText :text="l.label" />
                </RouterLink>
                <LocalizedText v-else :text="l.label" class="font-medium text-ink" />
              </li>
            </ul>
          </section>

          <OcrStatusCard v-if="hasOcr && ocrBundle" :bundle="ocrBundle" :pending-count="ocrPendingCount" :average-confidence="ocrAverageConfidence" :can-rerun="canManage" @view-fields="viewExtractedFields" @rerun="runOcr()" />
          <section v-else-if="canRunOcr" class="rounded-lg border border-line p-4" data-testid="ocr-run-card">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t("archive.ocr.title") }}</h2>
            <p class="mt-2 text-sm text-ink-muted">{{ t("archive.ocr.notProcessed") }}</p>
            <p v-if="ocrActionError" class="mt-2 text-sm text-danger" role="alert">{{ ocrActionError }}</p>
            <button v-if="canManage" type="button" class="mt-3 w-full rounded-md border border-ink px-3 py-1.5 text-xs font-medium text-ink hover:bg-neutral-soft" data-testid="run-ocr-button" @click="runOcr()">
              {{ t("archive.ocr.runOcr") }}
            </button>
          </section>
        </aside>

        <div class="space-y-3 lg:order-1">
          <div v-if="hasOcrText" class="flex flex-wrap items-center justify-between gap-2 rounded-t-md border border-b-0 border-line bg-neutral-soft px-3 py-2 text-xs">
            <div class="flex gap-1" role="tablist">
              <button
                type="button"
                class="rounded-md px-2.5 py-1 font-medium"
                :class="sideBySide || viewerTab === 'document' ? 'bg-ink text-paper' : 'text-ink-muted hover:text-ink'"
                data-testid="viewer-tab-document"
                @click="viewerTab = 'document'"
              >
                {{ t("archive.detail.documentTab") }}
              </button>
              <button
                type="button"
                class="rounded-md px-2.5 py-1 font-medium"
                :class="sideBySide || viewerTab === 'text' ? 'bg-ink text-paper' : 'text-ink-muted hover:text-ink'"
                data-testid="viewer-tab-text"
                @click="viewerTab = 'text'"
              >
                {{ t("archive.detail.extractedTextTab") }}
              </button>
            </div>
            <button
              type="button"
              class="rounded-md border px-2.5 py-1 font-medium"
              :class="sideBySide ? 'border-ink bg-ink text-paper' : 'border-line text-ink-muted hover:border-ink hover:text-ink'"
              :aria-pressed="sideBySide"
              data-testid="side-by-side-toggle"
              @click="sideBySide = !sideBySide"
            >
              {{ t("archive.detail.sideBySide") }}
            </button>
          </div>

          <div class="grid gap-3" :class="hasOcrText && sideBySide ? 'lg:grid-cols-2' : 'grid-cols-1'">
            <div v-if="!hasOcrText || sideBySide || viewerTab === 'document'">
              <div v-if="filePreview?.isPdf && filePreview.url" class="h-[640px] overflow-hidden rounded-md" :class="{ 'rounded-t-none': hasOcrText }" data-testid="file-preview-pdf">
                <PdfViewer :url="filePreview.url" :name="filePreview.name" />
              </div>
              <div v-else class="flex aspect-[16/9] items-center justify-center overflow-hidden rounded-md bg-neutral-soft text-ink-muted" :class="{ 'rounded-t-none': hasOcrText }">
                <img v-if="filePreview?.isImage && filePreview.url" :src="filePreview.url" :alt="filePreview.name" class="size-full object-contain" data-testid="file-preview" />
                <video v-else-if="filePreview?.isVideo && filePreview.url" controls class="size-full" :src="filePreview.url" data-testid="file-preview-video" />
                <audio v-else-if="filePreview?.isAudio && filePreview.url" controls class="w-full px-6" :src="filePreview.url" data-testid="file-preview-audio" />
                <div v-else-if="filePreview" class="flex flex-col items-center gap-2 p-6 text-center" data-testid="file-preview-unsupported">
                  <ArchiveTypeIcon :type="item.item_type" />
                  <p class="text-sm font-medium text-ink">{{ t("archive.viewer.noPreviewTitle") }}</p>
                  <p class="max-w-xs text-xs text-ink-muted">{{ t("archive.viewer.noPreviewHelp") }}</p>
                  <a :href="filePreview.url" class="mt-1 rounded-md border border-ink px-3 py-1.5 text-xs font-medium text-ink hover:bg-neutral-soft" download>{{ t("archive.viewer.download") }}</a>
                </div>
                <ArchiveTypeIcon v-else :type="item.item_type" />
              </div>
              <p v-if="filePreview" class="mt-2 text-xs text-ink-muted" dir="ltr" data-testid="file-meta">{{ filePreview.name }} · {{ formatSize(filePreview.size) }}<template v-if="filePreview.dims"> · {{ filePreview.dims }}</template></p>
              <p v-else class="mt-2 text-xs text-ink-muted">{{ t("archive.edit.noFile") }}</p>
            </div>

            <div v-if="hasOcrText && (sideBySide || viewerTab === 'text') && ocrBundle">
              <OcrTextPanel :texts="ocrBundle.texts" />
            </div>
          </div>
        </div>
      </div>

      <div ref="tabsSection" class="mt-10">
        <Tabs v-model:active-key="activeTab" :tabs="tabs">
          <template #metadata>
            <div class="space-y-10">
              <section>
                <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("archive.detail.descriptionTitle") }}</h2>
                <p class="mt-3 whitespace-pre-line text-sm text-ink" data-testid="description">
                  <LocalizedText v-if="pick(item.description)?.text" :text="item.description" />
                  <template v-else>{{ t("archive.detail.noDescription") }}</template>
                </p>
              </section>

              <section>
                <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("archive.detail.metadataTitle") }}</h2>
                <dl class="mt-3 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2" data-testid="meta-table">
                  <div v-for="row in metaRows" :key="row.key" class="flex justify-between gap-3 border-b border-line pb-2 sm:flex-col sm:justify-start sm:gap-1" data-testid="meta-row">
                    <dt class="text-xs text-ink-muted">{{ row.label }}</dt>
                    <dd class="text-ink">{{ row.value }}</dd>
                  </div>
                </dl>
              </section>
            </div>
          </template>

          <template #relations>
            <p v-if="item.links.length === 0" class="text-sm text-ink-muted">{{ t("archive.detail.relatedEmpty") }}</p>
            <ul v-else class="max-w-md space-y-3" data-testid="related-list">
              <li v-for="l in item.links" :key="l.id ?? `${l.kind}-${l.entity_id}`" class="text-sm">
                <p class="text-xs text-ink-muted">{{ t(`archive.edit.linkRoles.${l.role}`) }} · {{ t(`archive.edit.linkKinds.${l.kind}`) }}</p>
                <RouterLink v-if="linkTarget(l.kind, l.entity_id)" :to="linkTarget(l.kind, l.entity_id)!" class="font-medium text-ink hover:underline" data-testid="related-link">
                  <LocalizedText :text="l.label" />
                </RouterLink>
                <LocalizedText v-else :text="l.label" class="font-medium text-ink" />
              </li>
            </ul>
          </template>

          <template v-if="hasOcr && ocrBundle" #aiExtraction>
            <div class="space-y-6">
              <p class="text-sm text-ink-muted" data-testid="ocr-summary">{{ t("archive.ocr.extractedFrom", { fields: ocrBundle.fields.length, pages: ocrPageCount }) }}</p>
              <div v-if="ocrBundle.fields.length > 0">
                <p v-if="ocrActionError" class="mb-2 text-sm text-danger" role="alert">{{ ocrActionError }}</p>
                <OcrFieldsReviewTable
                  :fields="ocrBundle.fields"
                  :can-review="canManage"
                  @accept="acceptField"
                  @reject="rejectField"
                  @edit="editField"
                  @accept-high-confidence="acceptHighConfidence()"
                />
              </div>
            </div>
          </template>

          <template #activity>
            <p v-if="activityError" class="text-sm text-danger" role="alert">{{ activityError }}</p>
            <ActivityTimeline v-else :entries="activity" :loading="activityLoading" />
          </template>
        </Tabs>
      </div>

      <ArchiveDeleteDialog
        v-if="showDeleteDialog"
        :open="true"
        :item="item"
        @deleted="onDeleted"
        @cancel="showDeleteDialog = false"
        @hide-draft="showDeleteDialog = false"
      />
    </template>
  </section>
</template>
