<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import {
  approveArtwork, deleteArtworkImage, updateArtwork, updateArtworkImage, updateArtworkStage, uploadArtworkImage,
} from "@/api/artworkCuration";
import ArtworkImagesPanel from "@/components/curation/ArtworkImagesPanel.vue";
import ArtworkFormSections from "@/components/curation/ArtworkFormSections.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useArtworkForm } from "@/composables/useArtworkForm";
import { useArtworkCuration } from "@/composables/useArtworkCuration";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import {
  PIPELINE_STATUSES, type ArtworkStatus, type ImageRights, type PipelineStatus,
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

const { form, artist, holder, load: loadForm, payload } = useArtworkForm();

watch(curation, (c) => c && loadForm(c), { immediate: true });

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
  try {
    await updateArtwork(id.value, payload("update"));
    await retry();
    saved.value = true;
  } catch (err) {
    actionError.value = messageOf(err);
  } finally {
    saving.value = false;
  }
}

const imageBusy = ref(false);

async function imageAction(action: () => Promise<unknown>): Promise<void> {
  imageBusy.value = true;
  actionError.value = null;
  try {
    await action();
    await retry();
  } catch (err) {
    actionError.value = messageOf(err);
  } finally {
    imageBusy.value = false;
  }
}

const onUpload = (file: File, rights: ImageRights) => imageAction(() => uploadArtworkImage(id.value, file, rights));
const onMakeFinal = (imageId: number) => imageAction(() => updateArtworkImage(id.value, imageId, { is_final: true }));
const onRights = (imageId: number, rights: ImageRights) => imageAction(() => updateArtworkImage(id.value, imageId, { rights_status: rights }));
const onRemove = (imageId: number) => imageAction(() => deleteArtworkImage(id.value, imageId));

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
          <ArtworkImagesPanel :images="curation.images" :busy="imageBusy" @upload="onUpload" @final="onMakeFinal" @rights="onRights" @remove="onRemove" />

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
          <ArtworkFormSections
            v-model:form="form"
            v-model:artist="artist"
            v-model:holder="holder"
            :gaps="[...curation.completeness.blocking, ...curation.completeness.minor]"
            :final-image="{ present: curation.has_final_hr_image, label: curation.images.find((i) => i.is_final)?.filename ?? null }"
          />

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
