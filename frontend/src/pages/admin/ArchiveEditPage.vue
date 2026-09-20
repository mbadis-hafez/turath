<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import {
  addArchiveLink, createArchiveItem, deleteArchiveFile, getAdminArchiveItem, removeArchiveLink,
  submitArchiveReview, updateArchiveItem, uploadArchiveFile,
} from "@/api/archive";
import { listAdminArtworks } from "@/api/artworkCuration";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Spinner from "@/components/common/Spinner.vue";
import ArchiveTypeIcon from "@/components/curation/ArchiveTypeIcon.vue";
import { labelOf, searchArtistOptions } from "@/components/curation/ArtworkPickers";
import EntityPicker, { type PickerOption } from "@/components/curation/EntityPicker.vue";
import TagsInput from "@/components/curation/TagsInput.vue";
import { useArchiveForm } from "@/composables/useArchiveForm";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import { ARCHIVE_ITEM_TYPES, LINK_ROLES, type ArchiveEdit, type ArchiveEditLink, type RightsStatus } from "@/types/archive";
import { formatRelativeTime } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("archive.manage"));
const id = computed(() => (route.params.id ? Number(route.params.id) : null));
const isNew = computed(() => id.value === null);

const { form, load: loadForm, payload, checklist: liveChecklist } = useArchiveForm();

const item = ref<ArchiveEdit | null>(null);
const loading = ref(false);
const loadError = ref<unknown>(null);
let controller: AbortController | null = null;

async function loadItem(): Promise<void> {
  if (id.value === null) return;
  controller?.abort();
  const self = new AbortController();
  controller = self;
  loading.value = item.value === null;
  loadError.value = null;
  try {
    const response = await getAdminArchiveItem(id.value, self.signal);
    if (controller !== self) return;
    item.value = response.data;
    loadForm(response.data);
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    loadError.value = err;
  } finally {
    if (controller === self) loading.value = false;
  }
}
watch(id, () => void loadItem(), { immediate: true });
onBeforeUnmount(() => controller?.abort());

// ---- add mode: file and links are queued until the item exists
const pendingFile = ref<File | null>(null);
const pendingPreview = ref<string | null>(null);
const pendingLinks = ref<ArchiveEditLink[]>([]);
onBeforeUnmount(() => pendingPreview.value && URL.revokeObjectURL(pendingPreview.value));

const links = computed<ArchiveEditLink[]>(() => (isNew.value ? pendingLinks.value : item.value?.links ?? []));
const checklist = computed(() => (isNew.value ? liveChecklist(pendingFile.value !== null) : item.value?.checklist ?? []));
const pct = computed(() => Math.round((checklist.value.filter((c) => c.met).length / checklist.value.length) * 100));
const complete = computed(() => pct.value === 100);

const filePreview = computed(() => {
  if (isNew.value) return pendingFile.value ? { name: pendingFile.value.name, size: pendingFile.value.size, url: pendingPreview.value, isImage: pendingFile.value.type.startsWith("image/"), dims: null } : null;
  const f = item.value?.file;
  return f ? { name: f.name ?? "", size: f.size_bytes, url: f.url, isImage: f.is_image, dims: f.width_px ? `${f.width_px} × ${f.height_px}` : null } : null;
});
const formatSize = (b: number) => (b >= 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

const busy = ref(false);
const saved = ref(false);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});

function fail(err: unknown): void {
  error.value = err instanceof Error ? err.message : t("errors.generic");
  fieldErrors.value = err instanceof ApiError ? err.fieldErrors : {};
}

async function onFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = "";
  if (!file) return;
  error.value = null;
  if (isNew.value) {
    if (pendingPreview.value) URL.revokeObjectURL(pendingPreview.value);
    pendingFile.value = file;
    pendingPreview.value = URL.createObjectURL(file);
    return;
  }
  busy.value = true;
  try {
    await uploadArchiveFile(id.value!, file);
    await loadItem();
  } catch (err) {
    fail(err);
  } finally {
    busy.value = false;
  }
}

async function removeFile(): Promise<void> {
  if (isNew.value) {
    if (pendingPreview.value) URL.revokeObjectURL(pendingPreview.value);
    pendingFile.value = null;
    pendingPreview.value = null;
    return;
  }
  busy.value = true;
  try {
    await deleteArchiveFile(id.value!);
    await loadItem();
  } catch (err) {
    fail(err);
  } finally {
    busy.value = false;
  }
}

async function saveDraft(): Promise<void> {
  busy.value = true;
  saved.value = false;
  error.value = null;
  fieldErrors.value = {};
  if (isNew.value) {
    let newId: number;
    try {
      newId = (await createArchiveItem(payload("create"))).data.id;
    } catch (err) {
      fail(err);
      busy.value = false;
      return;
    }
    let failed = false;
    try {
      if (pendingFile.value) await uploadArchiveFile(newId, pendingFile.value);
      for (const l of pendingLinks.value) await addArchiveLink(newId, { linkable_type: l.kind, linkable_id: l.entity_id, role: l.role });
    } catch {
      failed = true;
    }
    busy.value = false;
    createdId.value = failed ? newId : null;
    if (!failed) await router.push(localePath("admin.archive.edit", { id: newId }));
    return;
  }
  try {
    await updateArchiveItem(id.value!, payload("update"));
    await loadItem();
    saved.value = true;
  } catch (err) {
    fail(err);
  } finally {
    busy.value = false;
  }
}
const createdId = ref<number | null>(null);

async function sendForReview(): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    await updateArchiveItem(id.value!, payload("update"));
    item.value = (await submitArchiveReview(id.value!)).data;
    loadForm(item.value);
  } catch (err) {
    fail(err);
  } finally {
    busy.value = false;
  }
}

// ---- links
const linkOpen = ref(false);
const linkKind = ref<"artist" | "artwork">("artist");
const linkRole = ref<string>("about");
const linkEntity = ref<PickerOption | null>(null);

const searchArtworks = async (q: string): Promise<PickerOption[]> =>
  (await listAdminArtworks({ q })).data.map((a) => ({ id: a.id, label: labelOf(a.title) }));

async function addLink(): Promise<void> {
  if (!linkEntity.value) return;
  const link: ArchiveEditLink = { role: linkRole.value, kind: linkKind.value, entity_id: linkEntity.value.id, label: { ar: linkEntity.value.label, en: linkEntity.value.label } };
  if (isNew.value) {
    pendingLinks.value = [...pendingLinks.value, link];
  } else {
    try {
      await addArchiveLink(id.value!, { linkable_type: link.kind, linkable_id: link.entity_id, role: link.role });
      await loadItem();
    } catch (err) {
      fail(err);
      return;
    }
  }
  linkEntity.value = null;
  linkOpen.value = false;
}

async function removeLink(link: ArchiveEditLink): Promise<void> {
  if (isNew.value) {
    pendingLinks.value = pendingLinks.value.filter((l) => l !== link);
    return;
  }
  try {
    await removeArchiveLink(id.value!, link.id!);
    await loadItem();
  } catch (err) {
    fail(err);
  }
}

const ACCESS = ["public", "registered", "institution_only"] as const;
const accessOptions = computed(() => (ACCESS as readonly string[]).includes(form.access) ? [...ACCESS] : [...ACCESS, form.access]);
const RIGHTS: RightsStatus[] = ["public_domain", "licensed", "all_rights_reserved", "unknown"];
const LICENSES = ["CC BY", "CC BY-NC", "CC BY-SA", "CC0", "In copyright — permission granted"];

const missingKeys = computed(() => new Set(checklist.value.filter((c) => !c.met).map((c) => c.key)));
const statusKey = computed(() => (item.value?.under_review ? "under_review" : item.value?.publication_status ?? "draft"));
const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
const missing = (key: string) => (missingKeys.value.has(key) ? "!border-danger" : "");
const err = (key: string) => fieldErrors.value[key]?.[0];
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <ErrorState v-else-if="loadError" :error="loadError" @retry="loadItem" />
    <Spinner v-else-if="loading" class="mx-auto my-12 block" />

    <template v-else>
      <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
        <RouterLink :to="localePath('dashboard')" class="hover:text-ink">{{ t("nav.dashboard") }}</RouterLink>
        <span aria-hidden="true"> &rsaquo; </span>
        <RouterLink :to="localePath('admin.archive')" class="hover:text-ink">{{ t("archive.admin.title") }}</RouterLink>
        <span aria-hidden="true"> &rsaquo; </span>
        <span class="text-ink">{{ isNew ? t("archive.edit.addCrumb") : t("archive.edit.editCrumb") }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ isNew ? t("archive.edit.addTitle") : t("archive.edit.editTitle") }}</h1>
          <p v-if="item" class="mt-1 text-sm text-ink-muted">
            <bdi dir="ltr">{{ item.legacy_ref ?? `#${item.id}` }}</bdi> · {{ t(`archive.admin.rowStatuses.${statusKey}`) }}
            <template v-if="item.updated_at"> · {{ t("archive.edit.lastSaved", { when: formatRelativeTime(item.updated_at, locale as AppLocale) }) }}</template>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft disabled:opacity-50" :disabled="busy || createdId !== null" data-testid="save-draft" @click="saveDraft">
            {{ busy ? t("curation.detail.saving") : t("archive.edit.saveDraft") }}
          </button>
          <button v-if="!isNew" type="button" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted" :disabled="!complete || busy || item?.under_review" :title="complete ? undefined : t('archive.edit.reviewBlocked')" data-testid="send-review" @click="sendForReview">
            {{ item?.under_review ? t("archive.edit.inReview") : t("archive.edit.sendReview") }}
          </button>
        </div>
      </div>
      <p v-if="saved" class="mt-2 text-sm text-success">{{ t("curation.detail.saved") }}</p>
      <p v-if="error" class="mt-2 text-sm text-danger" role="alert">{{ error }}</p>
      <p v-if="createdId" class="mt-2 text-sm text-warn" data-testid="partial-failure">
        {{ t("archive.edit.partialFailure") }}
        <RouterLink :to="localePath('admin.archive.edit', { id: createdId })" class="font-medium underline">{{ t("archive.edit.openItem") }}</RouterLink>
      </p>

      <div class="mt-8 grid gap-10 lg:grid-cols-[22rem_1fr]">
        <aside class="space-y-6">
          <section class="rounded-lg border border-ink p-4">
            <h2 class="text-xs font-semibold text-ink-muted">{{ t("archive.edit.uploadedFile") }}</h2>
            <div class="mt-3 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-md bg-neutral-soft text-ink-muted">
              <img v-if="filePreview?.isImage && filePreview.url" :src="filePreview.url" :alt="filePreview.name" class="size-full object-contain" data-testid="file-preview" />
              <ArchiveTypeIcon v-else :type="form.type" />
            </div>
            <p v-if="filePreview" class="mt-3 text-xs text-ink-muted" dir="ltr" data-testid="file-meta">{{ filePreview.name }} · {{ formatSize(filePreview.size) }}<template v-if="filePreview.dims"> · {{ filePreview.dims }}</template></p>
            <p v-else class="mt-3 text-xs text-ink-muted">{{ t("archive.edit.noFile") }}</p>
            <div class="mt-3 flex gap-2">
              <label class="flex-1 cursor-pointer rounded-md border border-ink px-3 py-2 text-center text-sm font-medium text-ink hover:bg-neutral-soft" :class="busy ? 'opacity-50' : ''">
                {{ filePreview ? t("archive.edit.replace") : t("archive.edit.upload") }}
                <input type="file" class="sr-only" accept=".jpg,.jpeg,.png,.webp,.tif,.tiff,.pdf,.doc,.docx,.mp3,.wav,.mp4,.mov" :disabled="busy" data-testid="file-input" @change="onFile" />
              </label>
              <button v-if="filePreview" type="button" class="flex-1 rounded-md border border-danger px-3 py-2 text-sm font-medium text-danger hover:bg-danger-soft disabled:opacity-50" :disabled="busy" @click="removeFile">{{ t("archive.edit.remove") }}</button>
            </div>
          </section>

          <section class="rounded-lg border p-4" :class="complete ? 'border-line bg-surface' : 'border-danger bg-danger-soft'">
            <h2 class="text-base font-semibold" :class="complete ? 'text-ink' : 'text-danger'">{{ t("archive.edit.checklistTitle") }}</h2>
            <ul class="mt-3 space-y-2" data-testid="checklist">
              <li v-for="c in checklist" :key="c.key" class="flex items-center gap-2 text-sm" :class="c.met ? 'text-ink-muted' : 'text-ink'">
                <input type="checkbox" class="size-4" :checked="c.met" disabled :aria-label="t(`archive.edit.checklist.${c.key}`)" />
                <span>{{ t(`archive.edit.checklist.${c.key}`) }}</span>
              </li>
            </ul>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-neutral-soft"><div class="h-full rounded-full" :class="complete ? 'bg-success' : 'bg-danger'" :style="{ width: `${pct}%` }" /></div>
            <p class="mt-1 text-xs tabular-nums" :class="complete ? 'text-ink-muted' : 'text-danger'" data-testid="pct">{{ t("archive.edit.completion", { pct }) }}</p>
          </section>

          <section class="rounded-lg border border-line bg-surface p-4">
            <h2 class="text-xs font-semibold text-ink-muted">{{ t("archive.edit.access") }}</h2>
            <div class="mt-3 space-y-3">
              <label v-for="a in accessOptions" :key="a" class="flex cursor-pointer items-start gap-3">
                <input v-model="form.access" type="radio" name="access" :value="a" class="mt-1 size-4 accent-ink" />
                <span><span class="block text-sm font-semibold text-ink">{{ t(`archive.edit.accessOptions.${a}.label`) }}</span><span class="block text-xs text-ink-muted">{{ t(`archive.edit.accessOptions.${a}.help`) }}</span></span>
              </label>
            </div>
          </section>
        </aside>

        <div class="space-y-10">
          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("archive.edit.identification") }}</h2>
            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
              <label class="text-xs text-ink-muted">{{ t("archive.edit.titleAr") }} <span class="text-danger">{{ t("archive.edit.required") }}</span><input v-model="form.titleAr" type="text" dir="rtl" :class="[input, missing('title_ar')]" data-testid="title-ar" /><span v-if="err('title')" class="text-danger">{{ err("title") }}</span></label>
              <label class="text-xs text-ink-muted">{{ t("archive.edit.titleEn") }}<input v-model="form.titleEn" type="text" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("archive.edit.itemType") }}<select v-model="form.type" :class="input"><option v-for="ty in ARCHIVE_ITEM_TYPES" :key="ty" :value="ty">{{ t(`archive.types.${ty}`) }}</option></select></label>
              <label class="text-xs text-ink-muted">{{ t("archive.edit.number") }}<input v-model="form.code" type="text" dir="ltr" maxlength="80" :class="input" /><span v-if="err('legacy_ref')" class="text-danger">{{ err("legacy_ref") }}</span></label>
              <fieldset class="text-xs text-ink-muted sm:col-span-2" data-testid="date-group">
                <legend>{{ t("archive.edit.date") }} <span class="text-danger">{{ t("archive.edit.required") }}</span></legend>
                <div class="mt-1 grid gap-3 sm:grid-cols-2">
                  <select v-model="form.dateMode" :class="input" :aria-label="t('archive.edit.dateMode')" data-testid="date-mode">
                    <option v-for="m in ['exact', 'year', 'approx']" :key="m" :value="m">{{ t(`archive.edit.dateModes.${m}`) }}</option>
                  </select>
                  <input v-if="form.dateMode === 'exact'" v-model="form.date" type="date" dir="ltr" :class="[input, missing('date')]" data-testid="date-input" />
                  <input v-else-if="form.dateMode === 'year'" v-model="form.year" type="number" min="1000" max="2100" inputmode="numeric" dir="ltr" :class="[input, missing('date')]" data-testid="year-input" />
                  <template v-else>
                    <input v-model="form.approxText" type="text" :placeholder="t('archive.edit.approxText')" :class="input" data-testid="approx-text" />
                    <div class="flex items-center gap-2 sm:col-span-2">
                      <input v-model="form.approxFrom" type="number" min="1000" max="2100" inputmode="numeric" dir="ltr" :placeholder="t('archive.admin.yearFrom')" :class="[input, missing('date'), 'mt-0']" data-testid="approx-from" />
                      <span aria-hidden="true">–</span>
                      <input v-model="form.approxTo" type="number" min="1000" max="2100" inputmode="numeric" dir="ltr" :placeholder="t('archive.admin.yearTo')" :class="[input, 'mt-0']" data-testid="approx-to" />
                      <select v-model="form.certainty" :class="[input, 'mt-0']" :aria-label="t('archive.edit.certainty')"><option value="circa">{{ t("archive.edit.certainties.circa") }}</option><option value="range">{{ t("archive.edit.certainties.range") }}</option></select>
                    </div>
                  </template>
                </div>
                <label v-if="form.dateMode === 'approx'" class="mt-3 block">{{ t("archive.edit.dateNote") }} <span class="text-danger">{{ t("archive.edit.required") }}</span>
                  <textarea v-model="form.dateNote" rows="2" :class="[input, missing('date')]" data-testid="date-note" />
                </label>
                <p v-if="missingKeys.has('date')" class="mt-1 text-danger">{{ form.dateMode === "approx" ? t("archive.edit.dateNeedsReason") : t("archive.edit.dateHelp") }}</p>
              </fieldset>
              <label class="text-xs text-ink-muted sm:col-span-2">{{ t("archive.edit.place") }}<input v-model="form.placeAr" type="text" dir="rtl" :class="input" /></label>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("archive.edit.content") }}</h2>
            <div class="mt-4 space-y-4 text-sm">
              <label class="block text-xs text-ink-muted">{{ t("archive.edit.descriptionAr") }}<textarea v-model="form.descriptionAr" rows="4" dir="rtl" :class="input" /></label>
              <label class="block text-xs text-ink-muted">{{ t("archive.edit.descriptionEn") }}<textarea v-model="form.descriptionEn" rows="3" dir="ltr" :class="input" /></label>
              <div class="text-xs text-ink-muted">{{ t("archive.edit.people") }} <span v-if="form.type === 'image'" class="text-danger">{{ t("archive.edit.required") }}</span>
                <TagsInput v-model="form.people" :placeholder="t('archive.edit.peoplePlaceholder')" :invalid="missingKeys.has('people_names')" />
                <p v-if="missingKeys.has('people_names')" class="mt-1 text-danger">{{ t("archive.edit.peopleHelp") }}</p>
              </div>
              <div class="text-xs text-ink-muted">{{ t("archive.edit.keywords") }}<TagsInput v-model="form.keywords" :placeholder="t('archive.edit.keywordsPlaceholder')" /></div>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("archive.edit.sourceRights") }}</h2>
            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
              <label class="text-xs text-ink-muted">{{ t("archive.edit.sourceName") }}<input v-model="form.sourceName" type="text" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("archive.edit.rightsHolder") }} <span class="text-danger">{{ t("archive.edit.required") }}</span><input v-model="form.holderAr" type="text" dir="rtl" :class="[input, missing('rights_holder_license')]" data-testid="holder-ar" /></label>
              <label class="text-xs text-ink-muted">{{ t("archive.edit.license") }} <span class="text-danger">{{ t("archive.edit.required") }}</span>
                <select v-model="form.license" :class="[input, missing('rights_holder_license')]" data-testid="license"><option value="">{{ t("archive.edit.licensePlaceholder") }}</option><option v-for="l in LICENSES" :key="l" :value="l">{{ l }}</option><option v-if="form.license && !LICENSES.includes(form.license)" :value="form.license">{{ form.license }}</option></select>
              </label>
              <label class="text-xs text-ink-muted">{{ t("archive.edit.rightsStatus") }}<select v-model="form.rightsStatus" :class="input"><option v-for="r in RIGHTS" :key="r" :value="r">{{ t(`archive.admin.rightsOptions.${r}`) }}</option></select></label>
              <label class="text-xs text-ink-muted sm:col-span-2">{{ t("archive.edit.verification") }}<input v-model="form.verification" type="text" :class="input" /></label>
              <p v-if="missingKeys.has('rights_holder_license')" class="text-xs text-danger sm:col-span-2">{{ t("archive.edit.rightsHelp") }}</p>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("archive.edit.links") }}</h2>
            <ul class="mt-4 flex flex-wrap gap-2" data-testid="links">
              <li v-for="(l, n) in links" :key="l.id ?? `p${n}`" class="flex items-center gap-2 rounded-sm border border-line bg-surface px-3 py-2 text-sm text-ink">
                <span class="text-xs text-ink-muted">{{ t(`archive.edit.linkRoles.${l.role}`) }} · {{ t(`archive.edit.linkKinds.${l.kind}`) }}</span>
                <LocalizedText :text="l.label" />
                <button type="button" class="text-ink-muted hover:text-danger" :aria-label="t('curation.profileForm.remove')" @click="removeLink(l)">×</button>
              </li>
              <li>
                <button type="button" class="rounded-sm border border-dashed border-ink px-3 py-2 text-sm text-ink hover:bg-neutral-soft" data-testid="link-open" @click="linkOpen = !linkOpen">{{ t("archive.edit.addLink") }}</button>
              </li>
            </ul>
            <div v-if="linkOpen" class="mt-3 grid gap-3 rounded-md border border-line p-3 text-sm sm:grid-cols-3" data-testid="link-form">
              <label class="text-xs text-ink-muted">{{ t("archive.edit.linkKind") }}<select v-model="linkKind" :class="input" @change="linkEntity = null"><option value="artist">{{ t("archive.edit.linkKinds.artist") }}</option><option value="artwork">{{ t("archive.edit.linkKinds.artwork") }}</option></select></label>
              <label class="text-xs text-ink-muted">{{ t("archive.edit.linkRole") }}<select v-model="linkRole" :class="input"><option v-for="r in LINK_ROLES" :key="r" :value="r">{{ t(`archive.edit.linkRoles.${r}`) }}</option></select></label>
              <div class="text-xs text-ink-muted">{{ t("archive.edit.linkTarget") }}<EntityPicker :key="linkKind" v-model="linkEntity" :search="linkKind === 'artist' ? searchArtistOptions : searchArtworks" :placeholder="t('archive.edit.linkSearch')" /></div>
              <button type="button" class="rounded-md bg-ink px-3 py-2 text-sm text-paper disabled:opacity-50 sm:col-span-3 sm:w-fit" :disabled="!linkEntity" data-testid="link-apply" @click="addLink">{{ t("archive.admin.apply") }}</button>
            </div>
          </section>
        </div>
      </div>
    </template>
  </section>
</template>
