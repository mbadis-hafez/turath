<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { listAdminArtworks } from "@/api/artworkCuration";
import { catalogSubmission, getSubmission, rejectSubmission, updateSubmission } from "@/api/submissions";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Spinner from "@/components/common/Spinner.vue";
import { labelOf, searchArtistOptions } from "@/components/curation/ArtworkPickers";
import EntityPicker, { type PickerOption } from "@/components/curation/EntityPicker.vue";
import { formatBytes } from "@/components/submissions/fileRules";
import { useLocalePath } from "@/composables/useLocalePath";
import { ApiError } from "@/types/api";
import { ARCHIVE_ITEM_TYPES } from "@/types/archive";
import type { LetterStatus, SubmissionDetail, SubmissionStatus } from "@/types/submission";
import { formatDateTime } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const props = defineProps<{ id: number }>();
const emit = defineEmits<{ changed: [] }>();

const { t, locale } = useI18n();
const { localePath } = useLocalePath();

const detail = ref<SubmissionDetail | null>(null);
const loading = ref(false);
const loadError = ref<unknown>(null);
const busy = ref(false);
const error = ref<string | null>(null);
let controller: AbortController | null = null;

const artist = ref<PickerOption | null>(null);
const artwork = ref<PickerOption | null>(null);
const notes = ref("");
const letter = ref<LetterStatus>("not_started");
const rejecting = ref(false);
const rejectNote = ref("");

interface CatalogRow { title: { ar: string; en: string }; type: string; fileIds: number[]; label: string }
const singleItem = ref(false);
const rows = ref<CatalogRow[]>([]);

function seed(d: SubmissionDetail): void {
  artist.value = d.linked_artist ? { id: d.linked_artist.id, label: labelOf(d.linked_artist.name) } : null;
  artwork.value = d.linked_artwork ? { id: d.linked_artwork.id, label: labelOf(d.linked_artwork.title) } : null;
  notes.value = d.staff_notes ?? "";
  letter.value = d.authorization_letter_status;
  buildRows();
}

/** By default one item per staged file; a curator can fold them into one (D155: their call). */
function buildRows(): void {
  const files = detail.value?.files ?? [];
  const blank = { ar: "", en: "" };
  if (singleItem.value || files.length === 0) {
    rows.value = [{ title: { ...blank }, type: "image", fileIds: files.map((f) => f.id), label: t("submissions.allFiles") }];
  } else {
    rows.value = files.map((f) => ({ title: { ...blank }, type: f.mime_type.startsWith("image/") ? "image" : "document", fileIds: [f.id], label: f.name ?? `#${f.id}` }));
  }
}
watch(singleItem, buildRows);

async function load(): Promise<void> {
  controller?.abort();
  const self = new AbortController();
  controller = self;
  loading.value = detail.value === null;
  loadError.value = null;
  try {
    const response = await getSubmission(props.id, self.signal);
    if (controller !== self) return;
    detail.value = response.data;
    seed(response.data);
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    if (controller === self) loadError.value = err;
  } finally {
    if (controller === self) loading.value = false;
  }
}
watch(() => props.id, () => void load(), { immediate: true });
onBeforeUnmount(() => controller?.abort());

async function run(action: () => Promise<unknown>): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    await action();
    await load();
    emit("changed");
  } catch (err) {
    error.value = err instanceof ApiError && Object.keys(err.fieldErrors).length > 0
      ? Object.values(err.fieldErrors)[0][0]
      : err instanceof Error ? err.message : t("errors.generic");
  } finally {
    busy.value = false;
  }
}

const move = (status: SubmissionStatus) => run(() => updateSubmission(props.id, { status }));
const saveLinks = () => run(() => updateSubmission(props.id, { linked_artist_id: artist.value?.id ?? null, linked_artwork_id: artwork.value?.id ?? null }));
const saveNotes = () => run(() => updateSubmission(props.id, { staff_notes: notes.value.trim() || null }));
const saveLetter = () => run(() => updateSubmission(props.id, { authorization_letter_status: letter.value }));
const doReject = () => {
  if (rejectNote.value.trim().length < 3) {
    error.value = t("submissions.rejectNoteRequired");
    return;
  }
  return run(async () => { await rejectSubmission(props.id, rejectNote.value); rejecting.value = false; });
};
const doCatalog = () =>
  run(() => catalogSubmission(props.id, rows.value.map((r) => ({
    item_type: r.type,
    title: { ar: r.title.ar.trim() || null, en: r.title.en.trim() || null },
    file_ids: r.fileIds,
  }))));

const searchArtworks = async (q: string): Promise<PickerOption[]> =>
  (await listAdminArtworks({ q })).data.map((a) => ({ id: a.id, label: labelOf(a.title) }));

const status = computed(() => detail.value?.status);
const closed = computed(() => ["published", "rejected", "withdrawn"].includes(status.value ?? ""));
const canCatalog = computed(() => status.value === "initial_review" || status.value === "cataloging");
const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
const btn = "rounded-md border border-ink px-3 py-1.5 text-sm font-medium text-ink hover:bg-neutral-soft disabled:opacity-50";
</script>

<template>
  <div class="rounded-lg border border-line bg-surface p-5" data-testid="submission-detail">
    <ErrorState v-if="loadError" :error="loadError" @retry="load" />
    <Spinner v-else-if="loading" class="mx-auto my-6 block" />
    <template v-else-if="detail">
      <dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
        <div><dt class="text-xs text-ink-muted">{{ t("submissions.submitter") }}</dt><dd class="text-ink" data-testid="submitter-name">{{ detail.submitter_name }} · {{ t(`submission.roles.${detail.submitter_role}`) }}</dd></div>
        <div><dt class="text-xs text-ink-muted">{{ t("submission.contact") }}</dt><dd class="text-ink" dir="auto" data-testid="submitter-contact">{{ detail.submitter_contact }}</dd></div>
        <div v-if="detail.city"><dt class="text-xs text-ink-muted">{{ t("submission.city") }}</dt><dd class="text-ink">{{ detail.city }}</dd></div>
        <div><dt class="text-xs text-ink-muted">{{ t("submissions.sentAt") }}</dt><dd class="text-ink">{{ formatDateTime(detail.submitted_at, locale as AppLocale) }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-xs text-ink-muted">{{ t("submission.description") }}</dt><dd class="whitespace-pre-wrap text-ink">{{ detail.description }}</dd></div>
      </dl>

      <section class="mt-5">
        <h3 class="text-xs font-semibold text-ink-muted">{{ t("submissions.files", { count: detail.files.length }) }}</h3>
        <p v-if="detail.files.length === 0" class="mt-1 text-sm text-ink-muted">{{ t("submissions.noFiles") }}</p>
        <ul v-else class="mt-2 flex flex-wrap gap-3" data-testid="staged-files">
          <li v-for="f in detail.files" :key="f.id" class="w-40 text-xs">
            <a :href="f.url" target="_blank" rel="noopener" class="block">
              <img v-if="f.mime_type.startsWith('image/') && f.mime_type !== 'image/tiff'" :src="f.url" :alt="f.name ?? ''" class="h-28 w-full rounded-sm bg-neutral-soft object-cover" />
              <span v-else class="flex h-28 w-full items-center justify-center rounded-sm bg-neutral-soft text-ink-muted" dir="ltr">{{ f.mime_type.split("/")[1] }}</span>
            </a>
            <p class="mt-1 truncate text-ink" dir="ltr">{{ f.name }}</p>
            <p class="tabular-nums text-ink-muted">{{ formatBytes(f.size_bytes) }}</p>
          </li>
        </ul>
      </section>

      <section v-if="detail.archive_items.length" class="mt-5" data-testid="created-items">
        <h3 class="text-xs font-semibold text-ink-muted">{{ t("submissions.createdItems") }}</h3>
        <ul class="mt-1 text-sm"><li v-for="a in detail.archive_items" :key="a.id"><RouterLink :to="localePath('admin.archive.edit', { id: a.id })" class="text-accent-strong hover:underline"><LocalizedText :text="a.title" /></RouterLink></li></ul>
      </section>

      <section v-if="!closed" class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="text-xs text-ink-muted">{{ t("submissions.linkedArtist") }}<EntityPicker v-model="artist" :search="searchArtistOptions" :placeholder="t('curation.artworkDetail.searchArtist')" /></div>
        <div class="text-xs text-ink-muted">{{ t("submissions.linkedArtwork") }}<EntityPicker v-model="artwork" :search="searchArtworks" :placeholder="t('events.edit.searchRecord')" /></div>
        <p class="text-xs text-ink-muted sm:col-span-2">{{ t("submissions.linkHelp") }} <button type="button" :class="btn" :disabled="busy" data-testid="save-links" @click="saveLinks">{{ t("submissions.saveLinks") }}</button></p>
      </section>
      <p v-else-if="detail.linked_artist" class="mt-5 text-sm text-ink"><span class="text-xs text-ink-muted">{{ t("submissions.linkedArtist") }}: </span><LocalizedText :text="detail.linked_artist.name" /></p>

      <label class="mt-5 block text-xs text-ink-muted">{{ t("submissions.staffNotes") }}
        <textarea v-model="notes" rows="2" :class="input" :disabled="detail.status === 'rejected'" data-testid="staff-notes" />
      </label>
      <button v-if="!closed" type="button" :class="[btn, 'mt-2']" :disabled="busy" data-testid="save-notes" @click="saveNotes">{{ t("submissions.saveNotes") }}</button>

      <section v-if="canCatalog" class="mt-6 rounded-md border border-line p-4" data-testid="catalog-panel">
        <h3 class="text-sm font-semibold text-ink">{{ t("submissions.catalogTitle") }}</h3>
        <p class="mt-1 text-xs text-ink-muted">{{ t("submissions.catalogHelp") }}</p>
        <label v-if="detail.files.length > 1" class="mt-3 flex cursor-pointer items-center gap-2 text-sm text-ink"><input v-model="singleItem" type="checkbox" class="size-4 accent-ink" data-testid="single-item" />{{ t("submissions.singleItem") }}</label>
        <ul class="mt-3 space-y-3">
          <li v-for="(r, n) in rows" :key="n" class="grid gap-2 sm:grid-cols-3" data-testid="catalog-row">
            <p class="truncate text-xs text-ink-muted sm:col-span-3" dir="auto">{{ r.label }}</p>
            <input v-model="r.title.ar" type="text" dir="rtl" :placeholder="t('archive.edit.titleAr')" :class="input" data-testid="catalog-title-ar" />
            <input v-model="r.title.en" type="text" dir="ltr" :placeholder="t('archive.edit.titleEn')" :class="input" />
            <select v-model="r.type" :class="input"><option v-for="ty in ARCHIVE_ITEM_TYPES" :key="ty" :value="ty">{{ t(`archive.types.${ty}`) }}</option></select>
          </li>
        </ul>
        <button type="button" class="mt-3 rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:opacity-50" :disabled="busy" data-testid="catalog" @click="doCatalog">{{ t("submissions.catalog") }}</button>
        <p class="mt-2 text-xs text-ink-muted">{{ t("submissions.catalogDefaults") }}</p>
      </section>

      <section v-if="status === 'authorization_pending'" class="mt-6 rounded-md border border-line p-4" data-testid="authorization-panel">
        <label class="block text-xs text-ink-muted">{{ t("submissions.letterStatus") }}
          <select v-model="letter" :class="input" data-testid="letter-status"><option v-for="s in ['not_started', 'pending', 'signed', 'not_applicable']" :key="s" :value="s">{{ t(`submissions.letter.${s}`) }}</option></select>
        </label>
        <div class="mt-3 flex gap-2">
          <button type="button" :class="btn" :disabled="busy" data-testid="save-letter" @click="saveLetter">{{ t("submissions.saveLetter") }}</button>
          <button type="button" class="rounded-md bg-ink px-4 py-1.5 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:opacity-50" :disabled="busy || !['signed', 'not_applicable'].includes(detail.authorization_letter_status)" data-testid="mark-published" @click="move('published')">{{ t("submissions.markPublished") }}</button>
        </div>
      </section>

      <p v-if="error" class="mt-4 text-sm text-danger" role="alert" data-testid="detail-error">{{ error }}</p>

      <div v-if="!closed" class="mt-6 flex flex-wrap items-center gap-2 border-t border-line pt-4">
        <button v-if="status === 'submitted'" type="button" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:opacity-50" :disabled="busy" data-testid="start-review" @click="move('initial_review')">{{ t("submissions.startReview") }}</button>
        <button v-if="status === 'initial_review'" type="button" :class="btn" :disabled="busy" data-testid="start-cataloging" @click="move('cataloging')">{{ t("submissions.startCataloging") }}</button>
        <button type="button" :class="btn" :disabled="busy" data-testid="withdraw" @click="move('withdrawn')">{{ t("submissions.withdraw") }}</button>
        <button v-if="!rejecting" type="button" class="rounded-md border border-danger px-3 py-1.5 text-sm font-medium text-danger hover:bg-danger-soft" :disabled="busy" data-testid="reject-open" @click="rejecting = true">{{ t("submissions.reject") }}</button>
        <div v-else class="flex w-full flex-wrap items-start gap-2" data-testid="reject-panel">
          <textarea v-model="rejectNote" rows="2" :placeholder="t('submissions.rejectNote')" :class="[input, 'flex-1']" data-testid="reject-note" />
          <button type="button" class="rounded-md bg-danger px-3 py-1.5 text-sm font-semibold text-surface disabled:opacity-50" :disabled="busy" data-testid="reject-confirm" @click="doReject">{{ t("submissions.rejectConfirm") }}</button>
          <button type="button" class="text-sm text-ink-muted hover:text-ink" @click="rejecting = false">{{ t("curation.merge.cancel") }}</button>
        </div>
        <p v-if="rejecting" class="w-full text-xs text-ink-muted">{{ t("submissions.rejectWarning") }}</p>
      </div>
    </template>
  </div>
</template>
