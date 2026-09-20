<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { createArtwork, updateArtworkImage, uploadArtworkImage } from "@/api/artworkCuration";
import ErrorState from "@/components/common/ErrorState.vue";
import ArtworkFormSections from "@/components/curation/ArtworkFormSections.vue";
import ArtworkImagesPanel from "@/components/curation/ArtworkImagesPanel.vue";
import { useArtworkForm } from "@/composables/useArtworkForm";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type { ArtworkImage, ImageRights } from "@/types/artworkCuration";

const router = useRouter();
const { t } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("artworks.manage"));

const { form, artist, holder, payload } = useArtworkForm();

interface Pending { id: number; file: File; url: string; rights: ImageRights; isFinal: boolean }
const pending = ref<Pending[]>([]);
let nextId = 1;

const images = computed<ArtworkImage[]>(() =>
  pending.value.map((p) => ({
    id: p.id, url: p.url, filename: p.file.name, width_px: null, height_px: null, size_bytes: p.file.size,
    rights_status: p.rights, is_final: p.isFinal,
  })),
);

function addImage(file: File, rights: ImageRights): void {
  pending.value.push({ id: nextId++, file, url: URL.createObjectURL(file), rights, isFinal: pending.value.length === 0 });
}
function makeFinal(id: number): void {
  pending.value.forEach((p) => (p.isFinal = p.id === id));
}
function setRights(id: number, rights: ImageRights): void {
  const p = pending.value.find((x) => x.id === id);
  if (p) p.rights = rights;
}
function removeImage(id: number): void {
  const p = pending.value.find((x) => x.id === id);
  if (p) URL.revokeObjectURL(p.url);
  pending.value = pending.value.filter((x) => x.id !== id);
  if (pending.value.length > 0 && !pending.value.some((x) => x.isFinal)) pending.value[0].isFinal = true;
}
onBeforeUnmount(() => pending.value.forEach((p) => URL.revokeObjectURL(p.url)));

const filled = (v: string): boolean => v.trim() !== "";

/** Live checklist: what the record would satisfy if created now. The artist's letter can't be known before save. */
const checklist = computed(() => [
  { key: "code", met: filled(form.code) },
  { key: "titles", met: filled(form.title.ar) && filled(form.title.en) },
  { key: "artist", met: artist.value !== null },
  { key: "year", met: filled(String(form.year)) },
  { key: "dimensions", met: filled(String(form.height)) || filled(String(form.width)) },
  { key: "medium", met: filled(form.medium.ar) || filled(form.medium.en) },
  { key: "condition_report", met: form.conditionStatus === "available" },
  { key: "hr_image", met: pending.value.some((p) => p.isFinal) },
  { key: "holder", met: holder.value !== null },
  { key: "authorization_letter", met: false },
]);
const metCount = computed(() => checklist.value.filter((i) => i.met).length);
const gaps = computed(() => [
  ...(holder.value ? [] : ["holder"]),
  ...(filled(String(form.height)) || filled(String(form.width)) ? [] : ["dimensions"]),
  ...(filled(form.medium.ar) || filled(form.medium.en) ? [] : ["medium"]),
]);

const valid = computed(() => form.isUntitled || filled(form.title.ar) || filled(form.title.en));
const submitting = ref(false);
const error = ref<string | null>(null);
const createdId = ref<number | null>(null);

async function submit(): Promise<void> {
  submitting.value = true;
  error.value = null;
  let newId: number;
  try {
    newId = (await createArtwork(payload("create"))).data.id;
    createdId.value = newId;
  } catch (err) {
    error.value = err instanceof Error ? err.message : t("errors.generic");
    submitting.value = false;
    return;
  }

  // The artwork exists now, so image failures must not block navigation.
  let failed = false;
  for (const p of pending.value) {
    try {
      const list = (await uploadArtworkImage(newId, p.file, p.rights)).data;
      if (p.isFinal) await updateArtworkImage(newId, Math.max(...list.map((i) => i.id)), { is_final: true });
    } catch {
      failed = true;
    }
  }
  submitting.value = false;
  if (!failed) await router.push(localePath("admin.artworks.show", { id: newId }));
}
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <form v-else @submit.prevent="submit">
      <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
        <RouterLink :to="localePath('admin.artworks')" class="hover:text-ink">{{ t("curation.artworkDetail.back") }}</RouterLink>
      </nav>

      <div class="mt-3 flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-sm bg-warn-soft px-1.5 py-0.5 text-xs font-medium text-warn">{{ t("curation.artworkRegistry.statuses.draft") }}</span>
          </div>
          <h1 class="mt-2 text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("curation.artworkCreate.title") }}</h1>
          <p class="mt-1 text-sm text-ink-muted">{{ t("curation.artworkCreate.subtitle") }}</p>
        </div>
        <div class="flex items-center gap-2">
          <RouterLink :to="localePath('admin.artworks')" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft">{{ t("curation.merge.cancel") }}</RouterLink>
          <button type="submit" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted" :disabled="!valid || submitting || createdId !== null" data-testid="create-submit">
            {{ submitting ? t("curation.artworkCreate.creating") : t("curation.artworkCreate.submit") }}
          </button>
        </div>
      </div>
      <p v-if="!valid" class="mt-2 text-sm text-ink-muted">{{ t("curation.artworkCreate.titleRequired") }}</p>
      <p v-if="error" class="mt-2 text-sm text-danger" role="alert">{{ error }}</p>
      <p v-if="createdId && !submitting" class="mt-2 text-sm text-warn" data-testid="images-failed">
        {{ t("curation.artworkCreate.imagesFailed") }}
        <RouterLink :to="localePath('admin.artworks.show', { id: createdId })" class="font-medium underline">{{ t("curation.artworkCreate.openArtwork") }}</RouterLink>
      </p>

      <div class="mt-8 grid gap-10 lg:grid-cols-[20rem_1fr]">
        <aside class="space-y-6">
          <ArtworkImagesPanel :images="images" @upload="addImage" @final="makeFinal" @rights="setRights" @remove="removeImage" />

          <section class="rounded-lg border border-danger bg-danger-soft p-4">
            <h2 class="text-base font-semibold text-danger">{{ t("curation.artworkDetail.checklist") }}</h2>
            <ul class="mt-3 space-y-2" data-testid="checklist">
              <li v-for="item in checklist" :key="item.key" class="flex items-center gap-2 text-sm" :class="item.met ? 'text-ink-muted' : 'text-ink'">
                <input type="checkbox" class="size-4" :checked="item.met" disabled :aria-label="t(`curation.artworkDetail.checklistItem.${item.key}`)" />
                <span>{{ t(`curation.artworkDetail.checklistItem.${item.key}`) }}</span>
              </li>
            </ul>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-neutral-soft">
              <div class="h-full rounded-full bg-danger" :style="{ width: `${(metCount / checklist.length) * 100}%` }" />
            </div>
            <p class="mt-1 text-xs tabular-nums text-ink-muted">{{ t("curation.artworkDetail.metCount", { met: metCount, total: checklist.length }) }}</p>
          </section>
        </aside>

        <ArtworkFormSections v-model:form="form" v-model:artist="artist" v-model:holder="holder" :gaps="gaps" />
      </div>
    </form>
  </section>
</template>
