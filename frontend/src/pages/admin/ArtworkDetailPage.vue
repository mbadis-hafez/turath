<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import { updateArtworkImage, uploadArtworkImages } from "@/api/artworkCuration";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Spinner from "@/components/common/Spinner.vue";
import { STATUS_CLASS } from "@/components/curation/ArtworkGridCard.vue";
import { useArtworkCuration } from "@/composables/useArtworkCuration";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import { ARTWORK_IMAGE_VIEW_ROLES, REQUIRED_ARTWORK_IMAGE_VIEW_ROLES, type ArtworkImage, type ArtworkImageViewRole, type Dims } from "@/types/artworkCuration";

const route = useRoute();
const { t } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("artworks.manage"));
const id = computed(() => Number(route.params.id));

const { curation, loading, error, retry } = useArtworkCuration(id);

const blockerCount = computed(() => Object.keys(curation.value?.approve_blockers ?? {}).length);
const missingKeys = computed(() => new Set(curation.value?.checklist.filter((c) => !c.met).map((c) => c.key) ?? []));

function formatDims(d: Dims | null | undefined): string {
  if (!d) return "—";
  const parts = [d.height_cm, d.width_cm, d.depth_cm].filter((v) => v !== null && v !== "");
  if (parts.length > 0) return `${parts.join(" × ")} cm`;
  return d.raw ?? "—";
}
const formatSize = (b: number) => (b >= 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);
const yesNo = (v: boolean) => (v ? t("curation.artworkDetail.yes") : t("curation.artworkDetail.no"));

interface FieldRow { key: string; label: string; value: string; flag: boolean; wide?: boolean }
interface FieldGroup { key: string; label: string; fields: FieldRow[] }

const fieldGroups = computed<FieldGroup[]>(() => {
  const c = curation.value;
  if (!c) return [];
  const f = (key: string, label: string, value: string, flagKey?: string, wide = false): FieldRow => ({
    key, label, value, wide, flag: flagKey ? missingKeys.value.has(flagKey) : false,
  });
  return [
    {
      key: "identification", label: t("curation.artworkDetail.identification"),
      fields: [
        f("code", t("curation.artworkDetail.code"), c.legacy_ref ?? "—", "code"),
        f("artist", t("curation.artworkDetail.artist"), c.artist ? pick(c.artist.name)?.text ?? "—" : "—", "artist"),
        f("titleAr", t("curation.artworkDetail.titleAr"), c.title.ar || "—", c.title.ar ? undefined : "titles"),
        f("titleEn", t("curation.artworkDetail.titleEn"), c.title.en || "—", c.title.en ? undefined : "titles"),
        f("category", t("curation.artworkDetail.category"), t(`curation.artworkDetail.categories.${c.category}`)),
        f("year", t("curation.artworkDetail.year"), c.creation?.display ?? "—", "year"),
        f("signed", t("curation.artworkDetail.signed"), t(`curation.artworkDetail.signedStates.${c.signed}`)),
        f("notes", t("curation.artworkDetail.description"), pick(c.notes)?.text || "—", undefined, true),
      ],
    },
    {
      key: "specifications", label: t("curation.artworkDetail.specifications"),
      fields: [
        f("dimensions", t("curation.artworkDetail.dimensions"), formatDims(c.dimensions), "dimensions"),
        f("frameDimensions", t("curation.artworkDetail.frameDimensions"), formatDims(c.frame_dimensions)),
        f("weight", t("curation.artworkDetail.weight"), c.weight_kg?.toString() ?? "—"),
        f("medium", t("curation.artworkDetail.mediumAr"), pick(c.medium)?.text || "—", "medium"),
        f("editionNumber", t("curation.artworkDetail.editionNumber"), c.edition.number ?? "—"),
        f("editionSize", t("curation.artworkDetail.editionSize"), c.edition.size?.toString() ?? "—"),
        f("holderInventory", t("curation.artworkDetail.holderInventory"), c.holder_inventory_no ?? "—"),
        f("inventoryByOwner", t("curation.artworkDetail.inventoryByOwner"), c.inventory_by_owner ?? "—"),
      ],
    },
    {
      key: "conditionImages", label: t("curation.artworkDetail.conditionImages"),
      fields: [
        f("conditionStatus", t("curation.artworkDetail.conditionStatus"), c.condition_report_status ? t(`curation.artworkDetail.conditionStates.${c.condition_report_status}`) : "—", "condition_report"),
        f("conditionLink", t("curation.artworkDetail.conditionLink"), c.condition_report_link ?? "—", "condition_report"),
        f("imageQuality", t("curation.artworkDetail.imageQuality"), c.image_quality ? t(`curation.artworkDetail.qualities.${c.image_quality}`) : "—"),
        f("finalHrImage", t("curation.artworkDetail.finalImage"), yesNo(c.has_final_hr_image), "hr_image"),
        f("editingStatus", t("curation.artworkDetail.editingStatus"), c.editing_status ?? "—"),
        f("holder", t("curation.artworkDetail.holder"), c.holder ? pick(c.holder.name)?.text ?? "—" : t("curation.artworkDetail.notSet"), "holder"),
      ],
    },
  ];
});

// ---- image gallery
const selectedIndex = ref(0);
const zoom = ref(100);
const viewerEl = ref<HTMLElement | null>(null);

function selectImage(index: number): void {
  selectedIndex.value = index;
  zoom.value = 100;
}
const images = computed<ArtworkImage[]>(() => curation.value?.images ?? []);
const selectedImage = computed(() => images.value[Math.min(selectedIndex.value, images.value.length - 1)] ?? null);
function prevImage(): void {
  if (images.value.length === 0) return;
  selectImage((selectedIndex.value - 1 + images.value.length) % images.value.length);
}
function nextImage(): void {
  if (images.value.length === 0) return;
  selectImage((selectedIndex.value + 1) % images.value.length);
}
function zoomIn(): void { zoom.value = Math.min(200, zoom.value + 10); }
function zoomOut(): void { zoom.value = Math.max(50, zoom.value - 10); }
function fitToView(): void { zoom.value = 100; }
async function toggleFullscreen(): Promise<void> {
  if (!viewerEl.value) return;
  if (document.fullscreenElement) await document.exitFullscreen();
  else await viewerEl.value.requestFullscreen();
}

// ---- shot-role tagging, public visibility, and capturing the required shots — the
// only mutating actions on this page, since they're tied to fields (view_role,
// is_public) that only exist here; everything else stays on the edit page.
const imageActionError = ref<string | null>(null);
const capturingRole = ref<ArtworkImageViewRole | null>(null);

async function setImageRole(image: ArtworkImage, role: ArtworkImageViewRole | ""): Promise<void> {
  imageActionError.value = null;
  try {
    await updateArtworkImage(id.value, image.id, { view_role: role || null });
    await retry();
  } catch (err) {
    imageActionError.value = err instanceof Error ? err.message : t("errors.generic");
  }
}

async function toggleImagePublic(image: ArtworkImage): Promise<void> {
  imageActionError.value = null;
  try {
    await updateArtworkImage(id.value, image.id, { is_public: !image.is_public });
    await retry();
  } catch (err) {
    imageActionError.value = err instanceof Error ? err.message : t("errors.generic");
  }
}

const missingRequiredRoles = computed<ArtworkImageViewRole[]>(() => {
  const present = new Set(images.value.map((i) => i.view_role).filter((r): r is ArtworkImageViewRole => r !== null));
  return REQUIRED_ARTWORK_IMAGE_VIEW_ROLES.filter((r) => !present.has(r));
});
const requiredShotStatus = computed(() => {
  const present = new Set(images.value.map((i) => i.view_role).filter((r): r is ArtworkImageViewRole => r !== null));
  return REQUIRED_ARTWORK_IMAGE_VIEW_ROLES.map((role) => ({ role, captured: present.has(role) }));
});

const captureInput = ref<HTMLInputElement | null>(null);
function requestCapture(role: ArtworkImageViewRole): void {
  capturingRole.value = role;
  captureInput.value?.click();
}
async function onCaptureFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  const role = capturingRole.value;
  input.value = "";
  capturingRole.value = null;
  if (!file || !role) return;
  imageActionError.value = null;
  try {
    const uploaded = await uploadArtworkImages(id.value, [file], "unknown");
    const newImageId = uploaded.results.find((r) => r.status === "attached")?.image_id;
    if (newImageId) await updateArtworkImage(id.value, newImageId, { view_role: role });
    await retry();
  } catch (err) {
    imageActionError.value = err instanceof Error ? err.message : t("errors.generic");
  }
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
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <ErrorState v-else-if="error" :error="error" @retry="retry" />
    <Spinner v-else-if="loading && !curation" class="mx-auto my-12 block" />

    <template v-else-if="curation">
      <nav class="font-latin text-xs text-ink-faint" aria-label="Breadcrumb">
        <RouterLink :to="localePath('admin.artworks')" class="hover:text-ink">{{ t("curation.artworkDetail.back") }}</RouterLink>
        <span aria-hidden="true"> &rsaquo; </span>
        <bdi dir="ltr" class="text-ink">{{ curation.legacy_ref ?? curation.id }}</bdi>
      </nav>

      <div class="mt-3 flex flex-wrap items-end justify-between gap-4 border-b-2 border-ink pb-4">
        <div class="max-w-[70ch]">
          <div class="flex flex-wrap items-center gap-2 font-latin text-[10.5px]" data-testid="badge-row">
            <span class="bg-ink px-2 py-0.5 font-bold uppercase tracking-wide text-paper">{{ t("curation.artworkDetail.artworkTag") }}</span>
            <span class="px-2 py-0.5 font-bold uppercase tracking-wide" :class="STATUS_CLASS[curation.publication_status]">{{ t(`curation.artworkRegistry.statuses.${curation.publication_status}`) }}</span>
            <span v-if="blockerCount > 0" class="border border-dashed border-danger bg-danger-soft px-2 py-0.5 font-bold uppercase tracking-wide tabular-nums text-danger">{{ t("curation.artworkDetail.missingFields", { count: blockerCount }) }}</span>
          </div>
          <h1 class="mt-3 text-balance font-display text-4xl font-bold leading-snug text-ink"><LocalizedText :text="curation.title" /></h1>
          <p class="mt-1.5 font-latin text-sm text-ink-faint">
            <bdi :dir="curation.title.en ? 'ltr' : 'rtl'">{{ curation.title.en ?? curation.title.ar }}</bdi>
            <template v-if="curation.artist"> · <LocalizedText :text="curation.artist.name" /></template>
          </p>
        </div>
        <div class="flex items-center gap-3">
          <RouterLink :to="localePath('admin.artworks.show', { id: curation.id })" class="border border-ink px-5 py-2.5 text-sm font-semibold text-ink hover:bg-ink hover:text-paper" data-testid="edit-link">
            {{ t("curation.artworkDetail.edit") }}
          </RouterLink>
          <button type="button" class="border border-ink px-5 py-2.5 text-sm font-semibold text-ink hover:bg-ink hover:text-paper" data-testid="share-button" @click="copyLink">
            {{ linkCopied ? t("curation.artworkDetail.linkCopied") : t("curation.artworkDetail.share") }}
          </button>
        </div>
      </div>

      <section v-if="images.length > 0 && selectedImage" class="mt-7" data-testid="gallery">
        <div class="flex items-baseline justify-between gap-4 border-b-2 border-ink pb-2.5">
          <span class="font-latin text-[11px] uppercase tracking-widest text-ink-faint">{{ t("curation.artworkDetail.imagesTitle") }}</span>
          <span class="font-latin text-[11px] tabular-nums text-ink-muted">{{ t("curation.artworkDetail.imagesCount", { count: images.length }) }}</span>
        </div>

        <div class="flex flex-col gap-7 pt-5 lg:flex-row lg:items-stretch">
          <div class="flex min-w-0 flex-1 flex-col">
            <div ref="viewerEl" class="relative flex h-[540px] items-center justify-center overflow-hidden bg-ink">
              <img :src="selectedImage.url" alt="" class="max-h-full max-w-full object-contain transition-transform" :style="{ transform: `scale(${zoom / 100})` }" data-testid="file-preview" />
              <template v-if="images.length > 1">
                <button type="button" class="absolute top-1/2 end-4 -mt-[22px] flex size-11 items-center justify-center bg-paper/90 text-ink" :aria-label="t('curation.artworkDetail.prevImage')" data-testid="gallery-prev" @click="prevImage">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 6-6 6 6 6" /></svg>
                </button>
                <button type="button" class="absolute top-1/2 start-4 -mt-[22px] flex size-11 items-center justify-center bg-paper/90 text-ink" :aria-label="t('curation.artworkDetail.nextImage')" data-testid="gallery-next" @click="nextImage">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6" /></svg>
                </button>
              </template>
              <span class="absolute top-3.5 end-3.5 bg-paper/90 px-2.5 py-1 font-latin text-[11.5px] tabular-nums" data-testid="gallery-position">{{ selectedIndex + 1 }} / {{ images.length }}</span>
              <span v-if="selectedImage.is_final" class="absolute top-3.5 start-3.5 bg-accent px-2.5 py-1 text-xs font-semibold text-paper">{{ t("curation.artworkDetail.finalImage") }}</span>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4 bg-ink px-3.5 py-2.5 font-latin text-xs text-paper">
              <span class="flex items-center gap-3.5">
                <button type="button" class="text-base" :aria-label="t('curation.artworkDetail.zoomOut')" @click="zoomOut">−</button>
                <span class="tabular-nums">{{ zoom }}%</span>
                <button type="button" class="text-base" :aria-label="t('curation.artworkDetail.zoomIn')" @click="zoomIn">+</button>
                <span class="h-3.5 w-px bg-ink-muted" />
                <button type="button" @click="fitToView">{{ t("curation.artworkDetail.fitToView") }}</button>
                <button type="button" @click="toggleFullscreen">{{ t("curation.artworkDetail.fullscreen") }}</button>
              </span>
              <span class="flex items-center gap-3.5 text-line">
                <span v-if="selectedImage.width_px" dir="ltr">{{ selectedImage.width_px }} × {{ selectedImage.height_px }}</span>
                <a :href="selectedImage.url" download class="text-paper hover:underline">{{ t("curation.artworkDetail.downloadOriginal") }}</a>
              </span>
            </div>
            <div class="flex gap-3 overflow-x-auto pt-3.5" data-testid="thumbnails">
              <button
                v-for="(img, i) in images" :key="img.id" type="button"
                class="w-[100px] shrink-0 text-start"
                data-testid="thumbnail"
                @click="selectImage(i)"
              >
                <span class="relative flex h-20 items-center justify-center overflow-hidden" :class="i === selectedIndex ? 'border-2 border-ink' : 'border border-line'">
                  <img :src="img.url" alt="" class="size-full object-cover" />
                  <span v-if="img.is_final" class="absolute top-1 end-1 bg-accent px-1 py-0.5 text-[10px] text-paper">{{ t("curation.artworkDetail.finalImage") }}</span>
                </span>
                <span class="mt-1.5 block truncate text-[12.5px] font-semibold text-ink">{{ img.view_role ? t(`curation.artworkDetail.viewRoles.${img.view_role}`) : t("curation.artworkDetail.unassigned") }}</span>
                <span class="block truncate font-latin text-[10.5px] text-ink-faint" dir="ltr">{{ img.filename ?? `#${img.id}` }}</span>
              </button>
              <button
                v-for="role in missingRequiredRoles" :key="role" type="button"
                class="w-[100px] shrink-0 text-start"
                data-testid="missing-shot-tile"
                :disabled="!canManage"
                @click="requestCapture(role)"
              >
                <span class="flex h-20 items-center justify-center border border-dashed border-danger bg-paper px-2 text-center text-[11.5px] font-semibold text-danger">{{ t("curation.artworkDetail.requiredTile") }}</span>
                <span class="mt-1.5 block truncate text-[12.5px] font-semibold text-ink">{{ t(`curation.artworkDetail.viewRoles.${role}`) }}</span>
                <span class="block truncate font-latin text-[10.5px] text-ink-faint">{{ t("curation.artworkDetail.notUploadedYet") }}</span>
              </button>
            </div>
            <input ref="captureInput" type="file" accept="image/*" class="sr-only" data-testid="capture-input" @change="onCaptureFile" />
          </div>

          <div class="flex w-full flex-col border border-line p-5 lg:w-80 lg:flex-none">
            <span class="font-latin text-[10.5px] uppercase tracking-widest text-ink-faint">{{ t("curation.artworkDetail.selectedImage") }}</span>
            <span class="mt-2 truncate font-display text-xl font-bold text-ink" dir="ltr">{{ selectedImage.filename ?? `#${selectedImage.id}` }}</span>
            <span v-if="selectedImage.is_final" class="mt-2.5 w-fit bg-accent-soft px-2 py-0.5 font-latin text-[10.5px] font-bold text-accent-strong">{{ t("curation.artworkDetail.finalImage") }}</span>
            <label class="mt-3.5 block text-sm">
              <span class="font-latin text-[10.5px] uppercase tracking-wide text-ink-faint">{{ t("curation.artworkDetail.assignRole") }}</span>
              <select
                class="mt-1 w-full border border-line bg-surface px-2 py-1.5 text-sm text-ink disabled:opacity-60"
                data-testid="role-select"
                :disabled="!canManage"
                :value="selectedImage.view_role ?? ''"
                @change="setImageRole(selectedImage, ($event.target as HTMLSelectElement).value as ArtworkImageViewRole | '')"
              >
                <option value="">{{ t("curation.artworkDetail.unassigned") }}</option>
                <option v-for="role in ARTWORK_IMAGE_VIEW_ROLES" :key="role" :value="role">{{ t(`curation.artworkDetail.viewRoles.${role}`) }}</option>
              </select>
            </label>
            <dl class="mt-3.5" data-testid="selected-image-meta">
              <div class="flex justify-between gap-3 border-b border-line py-2 text-sm">
                <dt class="font-latin text-[10.5px] uppercase tracking-wide text-ink-faint">{{ t("curation.artworkDetail.sizeLabel") }}</dt>
                <dd class="text-end text-ink" dir="ltr">{{ formatSize(selectedImage.size_bytes) }}</dd>
              </div>
              <div class="flex justify-between gap-3 border-b border-line py-2 text-sm">
                <dt class="font-latin text-[10.5px] uppercase tracking-wide text-ink-faint">{{ t("curation.artworkDetail.imageRights") }}</dt>
                <dd class="text-end text-ink">{{ t(`curation.profileForm.rights.${selectedImage.rights_status}`) }}</dd>
              </div>
              <div class="flex items-center justify-between gap-3 py-2.5 text-sm">
                <dt class="text-ink">{{ t("curation.artworkDetail.publicToggleLabel") }}</dt>
                <button
                  type="button" role="switch" :aria-checked="selectedImage.is_public"
                  class="relative h-[18px] w-9 shrink-0"
                  :class="selectedImage.is_public ? 'bg-ink' : 'bg-line'"
                  data-testid="public-toggle"
                  :disabled="!canManage"
                  @click="toggleImagePublic(selectedImage)"
                >
                  <span class="absolute top-0.5 size-3 bg-paper transition-all" :class="selectedImage.is_public ? 'end-0.5' : 'start-0.5'" />
                </button>
              </div>
            </dl>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3.5 bg-sand px-4 py-3 text-sm text-sand-ink" data-testid="required-shots">
          <span class="font-semibold text-ink">{{ t("curation.artworkDetail.requiredShotsTitle") }}</span>
          <span v-for="shot in requiredShotStatus" :key="shot.role" class="inline-flex items-center gap-1.5 whitespace-nowrap" :class="shot.captured ? 'text-ink-muted' : 'text-danger'">
            <span class="size-2 rounded-full" :class="shot.captured ? 'bg-accent' : 'bg-danger'" />
            {{ t(`curation.artworkDetail.viewRoles.${shot.role}`) }}
          </span>
        </div>
        <p v-if="imageActionError" class="mt-2 text-sm text-danger" role="alert">{{ imageActionError }}</p>
      </section>
      <p v-else class="mt-7 text-sm text-ink-muted" data-testid="no-images">{{ t("curation.artworkDetail.noImages") }}</p>

      <div class="mt-10 flex flex-col items-start gap-10 lg:flex-row">
        <main class="flex min-w-0 flex-1 flex-col gap-9">
          <section v-for="grp in fieldGroups" :key="grp.key">
            <div class="flex items-baseline justify-between gap-4 border-b-2 border-ink pb-2.5">
              <span class="font-latin text-[11px] uppercase tracking-widest text-ink-faint">{{ grp.label }}</span>
              <span class="font-latin text-[11px]" :class="grp.fields.some((f) => f.flag) ? 'text-danger' : 'text-ink-muted'">
                {{ grp.fields.some((f) => f.flag) ? t("curation.artworkDetail.missingFields", { count: grp.fields.filter((f) => f.flag).length }) : t("curation.artworkDetail.complete") }}
              </span>
            </div>
            <div class="grid gap-4 pt-5 sm:grid-cols-2" data-testid="meta-table">
              <div v-for="field in grp.fields" :key="field.key" :class="field.wide ? 'sm:col-span-2' : ''" data-testid="meta-row">
                <div class="flex items-baseline gap-2">
                  <span class="font-latin text-[10.5px] uppercase tracking-wide text-ink-muted">{{ field.label }}</span>
                  <span v-if="field.flag" class="text-[11.5px] font-semibold text-danger" data-testid="field-flag">{{ t("curation.artworkDetail.missing") }}</span>
                </div>
                <div class="mt-1.5 border px-3.5 py-2.5 text-sm leading-relaxed" :class="field.flag ? 'border-danger bg-danger-soft text-danger' : 'border-line bg-surface text-ink'">{{ field.value }}</div>
              </div>
            </div>
          </section>

          <section>
            <div class="border-b-2 border-ink pb-2.5 font-latin text-[11px] uppercase tracking-widest text-ink-faint">{{ t("curation.artworkDetail.materials") }}</div>
            <p v-if="curation.linked_materials.length === 0" class="py-4 text-sm text-ink-muted">{{ t("curation.artworkDetail.noMaterials") }}</p>
            <ul v-else data-testid="materials-list">
              <li v-for="m in curation.linked_materials" :key="m.id" class="flex flex-wrap items-center gap-4 border-b border-line py-3.5">
                <span class="size-12 shrink-0 bg-neutral-soft" />
                <span class="min-w-[220px] flex-1">
                  <RouterLink :to="localePath('admin.archive.show', { id: m.id })" class="block text-base font-semibold text-ink hover:underline" data-testid="related-link"><LocalizedText :text="m.title" /></RouterLink>
                  <span class="mt-1 block break-all font-latin text-[10.5px] text-ink-faint" dir="ltr">{{ m.legacy_ref ?? `#${m.id}` }}</span>
                </span>
                <span class="font-latin text-xs text-ink-muted">{{ t(`archive.types.${m.item_type}`) }}</span>
                <span class="tabular-nums font-latin text-[10.5px] text-ink-muted">{{ m.completeness_pct }}%</span>
              </li>
            </ul>
          </section>
        </main>

        <aside class="flex w-full flex-col gap-6 lg:w-80 lg:flex-none">
          <section v-if="curation.checklist.some((c) => !c.met)" class="border border-danger bg-danger-soft px-5 py-4.5" data-testid="required-checklist">
            <h2 class="font-display text-lg font-bold text-danger">{{ t("curation.artworkDetail.checklist") }}</h2>
            <ul class="mt-3.5 flex flex-col gap-2.5">
              <li v-for="item in curation.checklist" :key="item.key" class="flex items-start gap-2.5 text-sm leading-relaxed" :class="item.met ? 'text-ink-faint' : 'text-ink'">
                <span class="mt-0.5 flex size-3.5 shrink-0 items-center justify-center border" :class="item.met ? 'border-ink bg-ink' : 'border-danger bg-transparent'">
                  <svg v-if="item.met" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="var(--color-paper)" stroke-width="4"><path d="m5 13 4.5 4.5L19 7" /></svg>
                </span>
                <span>{{ t(`curation.artworkDetail.checklistItem.${item.key}`) }}</span>
              </li>
            </ul>
            <div class="mt-4.5 h-1 bg-neutral-soft"><div class="h-full bg-danger" :style="{ width: `${(curation.checklist.filter((c) => c.met).length / curation.checklist.length) * 100}%` }" /></div>
            <p class="mt-2 font-latin text-[11px] text-danger" data-testid="checklist-progress">{{ t("curation.artworkDetail.metCount", { met: curation.checklist.filter((c) => c.met).length, total: curation.checklist.length }) }}</p>
          </section>
          <section v-else class="border border-line bg-surface px-5 py-4.5" data-testid="required-checklist-done">
            <p class="text-sm text-ink">{{ t("curation.artworkDetail.requiredFieldsDone") }}</p>
          </section>

          <section>
            <div class="border-b-2 border-ink pb-2.5 font-latin text-[11px] uppercase tracking-widest text-ink-faint">{{ t("curation.artworkDetail.pipeline") }}</div>
            <ul class="divide-y divide-line" data-testid="pipeline">
              <li v-for="s in curation.pipeline" :key="s.stage_key" class="flex items-center justify-between gap-3 py-2.5 text-sm">
                <span class="text-ink">{{ t(`curation.artworkDetail.stages.${s.stage_key}`) }}</span>
                <span class="border px-2 py-0.5 font-latin text-[11px]" :class="s.status === 'done' || s.status === 'not_applicable' ? 'border-accent-soft bg-accent-soft text-accent-strong' : s.status === 'not_started' ? 'border-danger bg-danger-soft text-danger' : 'border-warn-soft bg-warn-soft text-warn'">{{ t(`curation.artworkDetail.stageStatus.${s.status}`) }}</span>
              </li>
            </ul>
          </section>
        </aside>
      </div>
    </template>
  </section>
</template>
