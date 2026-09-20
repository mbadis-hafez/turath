<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { createArtwork } from "@/api/artworkCuration";
import ErrorState from "@/components/common/ErrorState.vue";
import EntityPicker, { type PickerOption } from "@/components/curation/EntityPicker.vue";
import { searchArtistOptions, searchHolderOptions } from "@/components/curation/ArtworkPickers";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";

const router = useRouter();
const { t } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("artworks.manage"));

const CATEGORIES = ["painting", "drawing", "printmaking", "sculpture", "mixed_media", "paper_work", "photography", "installation", "other"];
const CERTAINTY = ["confirmed", "attributed", "disputed", "unattributed"];

const form = reactive({
  legacy_ref: "", is_untitled: false, title_ar: "", title_en: "", category: "painting", certainty: "confirmed",
  year: "", medium_ar: "", medium_en: "",
});
const artist = ref<PickerOption | null>(null);
const holder = ref<PickerOption | null>(null);
const submitting = ref(false);
const error = ref<string | null>(null);

const blank = (v: string): string | null => (v.trim() !== "" ? v.trim() : null);
const valid = computed(() => form.is_untitled || form.title_ar.trim() !== "" || form.title_en.trim() !== "");

async function submit(): Promise<void> {
  submitting.value = true;
  error.value = null;
  const year = Number.parseInt(String(form.year), 10);
  try {
    const response = await createArtwork({
      legacy_ref: blank(form.legacy_ref),
      is_untitled: form.is_untitled,
      title: { ar: blank(form.title_ar), en: blank(form.title_en) },
      category: form.category,
      attribution_certainty: artist.value ? form.certainty : "unattributed",
      artist_id: artist.value?.id ?? null,
      holder_id: holder.value?.id ?? null,
      medium: { ar: blank(form.medium_ar), en: blank(form.medium_en) },
      creation: Number.isFinite(year) ? { display: String(year), year_from: year, year_to: year, calendar: "gregorian", certainty: "exact" } : null,
      publication_status: "draft",
    });
    await router.push(localePath("admin.artworks.show", { id: response.data.id }));
  } catch (err) {
    error.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    submitting.value = false;
  }
}

const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <form v-else class="max-w-3xl" @submit.prevent="submit">
      <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
        <RouterLink :to="localePath('admin.artworks')" class="hover:text-ink">{{ t("curation.artworkDetail.back") }}</RouterLink>
      </nav>
      <h1 class="mt-3 border-b-2 border-ink pb-4 text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("curation.artworkCreate.title") }}</h1>

      <div class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.code") }}<input v-model="form.legacy_ref" type="text" dir="ltr" maxlength="40" :class="input" /></label>
        <label class="flex items-end gap-2 pb-2 text-sm text-ink"><input v-model="form.is_untitled" type="checkbox" class="size-4" />{{ t("curation.artworkRegistry.flags.untitled") }}</label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.titleAr") }}<input v-model="form.title_ar" type="text" dir="rtl" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.titleEn") }}<input v-model="form.title_en" type="text" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.category") }}
          <select v-model="form.category" :class="input"><option v-for="c in CATEGORIES" :key="c" :value="c">{{ t(`curation.artworkDetail.categories.${c}`) }}</option></select>
        </label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.year") }}<input v-model="form.year" type="number" min="1000" max="2100" inputmode="numeric" :class="input" /></label>
        <div class="text-xs text-ink-muted">{{ t("curation.artworkDetail.artist") }}<EntityPicker v-model="artist" :search="searchArtistOptions" :placeholder="t('curation.artworkDetail.searchArtist')" /></div>
        <label v-if="artist" class="text-xs text-ink-muted">{{ t("curation.artworkCreate.attribution") }}
          <select v-model="form.certainty" :class="input"><option v-for="c in CERTAINTY" :key="c" :value="c">{{ t(`curation.artworkCreate.certainty.${c}`) }}</option></select>
        </label>
        <div class="text-xs text-ink-muted">{{ t("curation.artworkDetail.holder") }}<EntityPicker v-model="holder" :search="searchHolderOptions" :placeholder="t('curation.artworkDetail.searchHolder')" /></div>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.mediumAr") }}<input v-model="form.medium_ar" type="text" dir="rtl" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.artworkDetail.mediumEn") }}<input v-model="form.medium_en" type="text" dir="ltr" :class="input" /></label>
      </div>

      <p v-if="!valid" class="mt-4 text-sm text-ink-muted">{{ t("curation.artworkCreate.titleRequired") }}</p>
      <p v-if="error" class="mt-4 text-sm text-danger" role="alert">{{ error }}</p>
      <div class="mt-6 flex gap-2">
        <button type="submit" class="rounded-md bg-ink px-5 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:opacity-50" :disabled="!valid || submitting" data-testid="create-submit">
          {{ submitting ? t("curation.artworkCreate.creating") : t("curation.artworkCreate.submit") }}
        </button>
        <RouterLink :to="localePath('admin.artworks')" class="rounded-md border border-ink px-5 py-2 text-sm font-medium text-ink hover:bg-neutral-soft">{{ t("curation.merge.cancel") }}</RouterLink>
      </div>
    </form>
  </section>
</template>
