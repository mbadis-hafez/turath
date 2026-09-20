<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import { approveArtwork, updateArtwork, updateArtworkStage } from "@/api/artworkCuration";
import EntityPicker, { type PickerOption } from "@/components/curation/EntityPicker.vue";
import { labelOf, searchArtistOptions, searchHolderOptions } from "@/components/curation/ArtworkPickers";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useArtworkCuration } from "@/composables/useArtworkCuration";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import {
  PIPELINE_STATUSES, type ArtworkStatus, type ConditionStatus, type ImageQuality, type PipelineStatus, type SignedState,
} from "@/types/artworkCuration";

const route = useRoute();
const { t } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("artworks.manage"));
const id = computed(() => Number(route.params.id));
const { curation, loading, error, retry } = useArtworkCuration(id);

const CATEGORIES = ["painting", "drawing", "printmaking", "sculpture", "mixed_media", "paper_work", "photography", "installation", "other"];
const CONDITION: ConditionStatus[] = ["not_available", "pending", "available"];
const QUALITY: ImageQuality[] = ["low_resolution", "high_resolution", "archive_source"];
const SIGNED: SignedState[] = ["signed", "unsigned", "unknown"];

const blank = (v: string | null | undefined) => (v && v.trim() !== "" ? v.trim() : null);
const num = (v: string | number | null) => (v === "" || v === null ? null : Number(v));

const form = reactive({
  title: { ar: "", en: "" }, category: "painting", medium: { ar: "", en: "" }, year: "", signed: "unknown" as SignedState,
  height: "", width: "", depth: "", frameHeight: "", frameWidth: "", frameDepth: "", weight: "",
  editionNumber: "", editionSize: "", holderInventory: "", inventoryByOwner: "",
  conditionLink: "", conditionStatus: "" as ConditionStatus | "", imageQuality: "" as ImageQuality | "", editingStatus: "",
  notes: { ar: "", en: "" },
});
const artistPick = ref<PickerOption | null>(null);
const holderPick = ref<PickerOption | null>(null);
let initialYear = "";

watch(curation, (c) => {
  if (!c) return;
  form.title = { ar: c.title.ar ?? "", en: c.title.en ?? "" };
  form.category = c.category;
  artistPick.value = c.artist ? { id: c.artist.id, label: labelOf(c.artist.name) } : null;
  holderPick.value = c.holder ? { id: c.holder.id, label: labelOf(c.holder.name) } : null;
  form.medium = { ar: c.medium.ar ?? "", en: c.medium.en ?? "" };
  form.year = initialYear = c.creation?.year_from ? String(c.creation.year_from) : "";
  form.signed = c.signed;
  form.height = String(c.dimensions.height_cm ?? ""); form.width = String(c.dimensions.width_cm ?? ""); form.depth = String(c.dimensions.depth_cm ?? "");
  form.frameHeight = String(c.frame_dimensions.height_cm ?? ""); form.frameWidth = String(c.frame_dimensions.width_cm ?? ""); form.frameDepth = String(c.frame_dimensions.depth_cm ?? "");
  form.weight = String(c.weight_kg ?? "");
  form.editionNumber = c.edition.number ?? ""; form.editionSize = String(c.edition.size ?? "");
  form.holderInventory = c.holder_inventory_no ?? ""; form.inventoryByOwner = c.inventory_by_owner ?? "";
  form.conditionLink = c.condition_report_link ?? ""; form.conditionStatus = c.condition_report_status ?? "";
  form.imageQuality = c.image_quality ?? ""; form.editingStatus = c.editing_status ?? "";
  form.notes = { ar: c.notes.ar ?? "", en: c.notes.en ?? "" };
}, { immediate: true });

const saving = ref(false);
const saved = ref(false);
const approving = ref(false);
const actionError = ref<string | null>(null);

function messageOf(err: unknown): string {
  return err instanceof Error ? err.message : t("errors.generic");
}

async function save(): Promise<void> {
  saving.value = true;
  saved.value = false;
  actionError.value = null;
  const payload: Record<string, unknown> = {
    title: { ar: blank(form.title.ar), en: blank(form.title.en) },
    category: form.category,
    artist_id: artistPick.value?.id ?? null,
    holder_id: holderPick.value?.id ?? null,
    medium: { ar: blank(form.medium.ar), en: blank(form.medium.en) },
    signed: form.signed,
    dimensions: { height_cm: num(form.height), width_cm: num(form.width), depth_cm: num(form.depth) },
    frame_dimensions: { height_cm: num(form.frameHeight), width_cm: num(form.frameWidth), depth_cm: num(form.frameDepth) },
    weight_kg: num(form.weight),
    edition_number: blank(form.editionNumber),
    edition_size: num(form.editionSize),
    holder_inventory_no: blank(form.holderInventory),
    inventory_by_owner: blank(form.inventoryByOwner),
    condition_report_link: blank(form.conditionLink),
    condition_report_status: form.conditionStatus || null,
    image_quality: form.imageQuality || null,
    editing_status: blank(form.editingStatus),
    notes: { ar: blank(form.notes.ar), en: blank(form.notes.en) },
  };
  // Only sent when edited so circa/range dates are never overwritten.
  if (String(form.year) !== initialYear) {
    const y = num(form.year);
    payload.creation = y ? { display: String(y), year_from: y, year_to: y, calendar: "gregorian", certainty: "exact" } : null;
  }
  try {
    await updateArtwork(id.value, payload);
    await retry();
    saved.value = true;
  } catch (err) {
    actionError.value = messageOf(err);
  } finally {
    saving.value = false;
  }
}

async function setStage(key: string, status: PipelineStatus): Promise<void> {
  actionError.value = null;
  try {
    await updateArtworkStage(id.value, key, status);
    await retry();
  } catch (err) {
    actionError.value = messageOf(err);
  }
}

const blockerCount = computed(() => Object.keys(curation.value?.approve_blockers ?? {}).length);
const canApprove = computed(() => blockerCount.value === 0 && curation.value?.publication_status !== "published");
const metCount = computed(() => curation.value?.checklist.filter((i) => i.met).length ?? 0);

async function approve(): Promise<void> {
  approving.value = true;
  actionError.value = null;
  try {
    await approveArtwork(id.value);
    await retry();
  } catch (err) {
    actionError.value = messageOf(err);
  } finally {
    approving.value = false;
  }
}

const STATUS_CLASS: Record<ArtworkStatus, string> = {
  draft: "bg-warn-soft text-warn", published: "bg-success-soft text-success", hidden: "bg-neutral-soft text-ink-muted",
};
const stageClass = (s: PipelineStatus) =>
  s === "done" || s === "not_applicable" ? "bg-success-soft text-success" : s === "not_started" ? "bg-danger-soft text-danger" : "bg-warn-soft text-warn";
const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
const gap = (key: string) => curation.value?.completeness.blocking.includes(key) || curation.value?.completeness.minor.includes(key);
const gapInput = (key: string) => (gap(key) ? "!border-danger !bg-danger-soft" : "");
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <ErrorState v-else-if="error" :error="error" @retry="retry" />
    <Spinner v-else-if="loading && !curation" class="mx-auto my-12 block" />

    <template v-else-if="curation">
      <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
        <RouterLink :to="localePath('admin.artworks')" class="hover:text-ink">{{ t("curation.artworkDetail.back") }}</RouterLink>
        <span aria-hidden="true"> &rsaquo; </span>
        <bdi>{{ curation.legacy_ref ?? curation.id }}</bdi>
      </nav>

      <div class="mt-3 flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="STATUS_CLASS[curation.publication_status]" data-testid="status-badge">{{ t(`curation.artworkRegistry.statuses.${curation.publication_status}`) }}</span>
            <span v-if="curation.completeness.blocking.length + curation.completeness.minor.length > 0" class="rounded-sm bg-danger-soft px-1.5 py-0.5 text-xs font-medium tabular-nums text-danger">
              {{ t("curation.artworkDetail.missingFields", { count: curation.completeness.blocking.length + curation.completeness.minor.length }) }}
            </span>
          </div>
          <h1 class="mt-2 text-balance text-3xl font-semibold tracking-tight text-ink"><LocalizedText :text="curation.title" /></h1>
          <p class="mt-1 text-sm text-ink-muted">
            {{ pick({ ar: curation.title.en, en: curation.title.ar })?.text }}<template v-if="curation.artist"> · <LocalizedText :text="curation.artist.name" /></template>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft disabled:opacity-50" :disabled="saving" @click="save">
            {{ saving ? t("curation.detail.saving") : t("curation.detail.save") }}
          </button>
          <button
            type="button"
            class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted"
            data-testid="approve-button"
            :disabled="!canApprove || approving"
            :title="blockerCount > 0 ? t('curation.artworkDetail.blockers', { count: blockerCount }) : undefined"
            @click="approve"
          >
            {{ curation.publication_status === "published" ? t("curation.artworkDetail.approved") : t("curation.artworkDetail.approve") }}
          </button>
        </div>
      </div>
      <p v-if="saved" class="mt-2 text-sm text-success">{{ t("curation.detail.saved") }}</p>
      <p v-if="actionError" class="mt-2 text-sm text-danger">{{ actionError }}</p>

      <div class="mt-8 grid gap-10 lg:grid-cols-[20rem_1fr]">
        <aside class="space-y-6">
          <div class="aspect-[4/3] rounded-lg border border-line bg-neutral-soft" aria-hidden="true" />

          <section class="rounded-lg border p-4" :class="blockerCount === 0 ? 'border-line bg-surface' : 'border-danger bg-danger-soft'">
            <h2 class="text-base font-semibold" :class="blockerCount === 0 ? 'text-ink' : 'text-danger'">{{ t("curation.artworkDetail.checklist") }}</h2>
            <ul class="mt-3 space-y-2" data-testid="checklist">
              <li v-for="item in curation.checklist" :key="item.key" class="flex items-center gap-2 text-sm" :class="item.met ? 'text-ink-muted' : 'text-ink'">
                <input type="checkbox" class="size-4" :checked="item.met" disabled :aria-label="t(`curation.artworkDetail.checklistItem.${item.key}`)" />
                <span>{{ t(`curation.artworkDetail.checklistItem.${item.key}`) }}</span>
              </li>
            </ul>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-neutral-soft">
              <div class="h-full rounded-full" :class="blockerCount === 0 ? 'bg-success' : 'bg-danger'" :style="{ width: `${(metCount / curation.checklist.length) * 100}%` }" />
            </div>
            <p class="mt-1 text-xs tabular-nums text-ink-muted">{{ t("curation.artworkDetail.metCount", { met: metCount, total: curation.checklist.length }) }}</p>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("curation.artworkDetail.pipeline") }}</h2>
            <ul class="divide-y divide-line text-sm" data-testid="pipeline">
              <li v-for="s in curation.pipeline" :key="s.stage_key" class="flex items-center justify-between gap-2 py-3">
                <span class="text-ink">{{ t(`curation.artworkDetail.stages.${s.stage_key}`) }}</span>
                <select :value="s.status" class="rounded-sm border-0 px-1.5 py-0.5 text-xs font-medium" :class="stageClass(s.status)" :aria-label="t(`curation.artworkDetail.stages.${s.stage_key}`)" @change="setStage(s.stage_key, ($event.target as HTMLSelectElement).value as PipelineStatus)">
                  <option v-for="st in PIPELINE_STATUSES" :key="st" :value="st">{{ t(`curation.artworkDetail.stageStatus.${st}`) }}</option>
                </select>
              </li>
            </ul>
          </section>
        </aside>

        <div class="space-y-10">
          <section>
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.artworkDetail.identification") }}</h2>
            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
              <div class="text-xs text-ink-muted">{{ t("curation.artworkDetail.code") }}<p class="mt-1 rounded-md border border-line bg-neutral-soft px-3 py-2 text-sm text-ink" dir="ltr">{{ curation.legacy_ref ?? "—" }}</p></div>
              <div class="text-xs text-ink-muted">{{ t("curation.artworkDetail.artist") }}<EntityPicker v-model="artistPick" :search="searchArtistOptions" :placeholder="t('curation.artworkDetail.searchArtist')" /></div>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.titleAr") }}<input v-model="form.title.ar" type="text" dir="rtl" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.titleEn") }}<input v-model="form.title.en" type="text" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.category") }}
                <select v-model="form.category" :class="input"><option v-for="c in CATEGORIES" :key="c" :value="c">{{ t(`curation.artworkDetail.categories.${c}`) }}</option></select>
              </label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.year") }}<input v-model="form.year" type="number" min="1000" max="2100" inputmode="numeric" :class="[input, gapInput('year')]" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.mediumAr") }}<input v-model="form.medium.ar" type="text" dir="rtl" :class="[input, gapInput('medium')]" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.mediumEn") }}<input v-model="form.medium.en" type="text" dir="ltr" :class="[input, gapInput('medium')]" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.signed") }}
                <select v-model="form.signed" :class="input"><option v-for="s in SIGNED" :key="s" :value="s">{{ t(`curation.artworkDetail.signedStates.${s}`) }}</option></select>
              </label>
              <label class="text-xs text-ink-muted sm:col-span-2">{{ t("curation.artworkDetail.notesAr") }}<textarea v-model="form.notes.ar" rows="3" dir="rtl" :class="input" /></label>
              <label class="text-xs text-ink-muted sm:col-span-2">{{ t("curation.artworkDetail.notesEn") }}<textarea v-model="form.notes.en" rows="3" dir="ltr" :class="input" /></label>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.artworkDetail.specifications") }}</h2>
            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.height") }}<input v-model="form.height" type="number" step="0.01" min="0" dir="ltr" :class="[input, gapInput('dimensions')]" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.width") }}<input v-model="form.width" type="number" step="0.01" min="0" dir="ltr" :class="[input, gapInput('dimensions')]" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.depth") }}<input v-model="form.depth" type="number" step="0.01" min="0" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.frameHeight") }}<input v-model="form.frameHeight" type="number" step="0.01" min="0" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.frameWidth") }}<input v-model="form.frameWidth" type="number" step="0.01" min="0" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.frameDepth") }}<input v-model="form.frameDepth" type="number" step="0.01" min="0" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.weight") }}<input v-model="form.weight" type="number" step="0.01" min="0" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.editionNumber") }}<input v-model="form.editionNumber" type="text" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.editionSize") }}<input v-model="form.editionSize" type="number" min="0" dir="ltr" :class="input" /></label>
              <div class="text-xs text-ink-muted">{{ t("curation.artworkDetail.holder") }}<EntityPicker v-model="holderPick" :search="searchHolderOptions" :placeholder="t('curation.artworkDetail.searchHolder')" /></div>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.holderInventory") }}<input v-model="form.holderInventory" type="text" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.inventoryByOwner") }}<input v-model="form.inventoryByOwner" type="text" dir="ltr" :class="input" /></label>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.artworkDetail.conditionImages") }}</h2>
            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.conditionStatus") }}
                <select v-model="form.conditionStatus" :class="input"><option value="">—</option><option v-for="c in CONDITION" :key="c" :value="c">{{ t(`curation.artworkDetail.conditionStates.${c}`) }}</option></select>
              </label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.conditionLink") }}<input v-model="form.conditionLink" type="url" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.imageQuality") }}
                <select v-model="form.imageQuality" :class="input"><option value="">—</option><option v-for="q in QUALITY" :key="q" :value="q">{{ t(`curation.artworkDetail.qualities.${q}`) }}</option></select>
              </label>
              <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.editingStatus") }}<input v-model="form.editingStatus" type="text" dir="ltr" maxlength="20" :class="input" /></label>
              <div class="text-xs text-ink-muted">{{ t("curation.artworkDetail.finalImage") }}<p class="mt-1 rounded-md border px-3 py-2 text-sm text-ink" :class="curation.has_final_hr_image ? 'border-line bg-neutral-soft' : 'border-danger bg-danger-soft'">{{ curation.has_final_hr_image ? t("curation.detail.yes") : t("curation.artworkDetail.noFinalImage") }}</p></div>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("curation.artworkDetail.materials") }}</h2>
            <p v-if="curation.linked_materials.length === 0" class="py-4 text-sm text-ink-muted">{{ t("curation.artworkDetail.noMaterials") }}</p>
            <ul v-else class="divide-y divide-line" data-testid="materials">
              <li v-for="m in curation.linked_materials" :key="m.id" class="flex items-center justify-between gap-4 py-3">
                <div>
                  <p class="text-base font-semibold text-ink"><LocalizedText :text="m.title" /></p>
                  <p class="text-xs text-ink-muted" dir="ltr">{{ m.legacy_ref }} · {{ m.item_type }}</p>
                </div>
                <span class="text-xs tabular-nums text-ink-muted">{{ m.completeness_pct }}%</span>
              </li>
            </ul>
          </section>
        </div>
      </div>
    </template>
  </section>
</template>
