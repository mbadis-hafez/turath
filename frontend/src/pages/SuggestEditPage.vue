<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import { getArtist } from "@/api/artists";
import { getArtwork } from "@/api/artworks";
import { submitProposal } from "@/api/proposals";
import ErrorState from "@/components/common/ErrorState.vue";
import Spinner from "@/components/common/Spinner.vue";
import DiffValue from "@/components/proposals/DiffValue.vue";
import { SUGGEST_FIELDS, flattenArtist, flattenArtwork, supportsSuggestions } from "@/components/proposals/suggestFields";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";

const route = useRoute();
const { t } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canSuggest = computed(() => auth.can("proposals.submit"));

const type = computed(() => String(route.params.type ?? ""));
const key = computed(() => String(route.params.id ?? ""));
const fields = computed(() => (supportsSuggestions(type.value) ? SUGGEST_FIELDS[type.value] : []));

const recordId = ref<number | null>(null);
const recordLabel = ref<string>("");
const current = ref<Record<string, unknown>>({});
const draft = reactive<Record<string, string>>({});
const loading = ref(false);
const loadError = ref<unknown>(null);

const asText = (v: unknown): string => (v === null || v === undefined ? "" : String(v));

async function load(): Promise<void> {
  if (!canSuggest.value || !supportsSuggestions(type.value)) return;
  loading.value = true;
  loadError.value = null;
  try {
    if (type.value === "artists") {
      const artist = (await getArtist(key.value)).data;
      recordId.value = artist.id;
      recordLabel.value = artist.name.ar ?? artist.name.en ?? "";
      current.value = flattenArtist(artist);
    } else {
      const artwork = (await getArtwork(Number(key.value))).data;
      recordId.value = artwork.id;
      recordLabel.value = artwork.title.ar ?? artwork.title.en ?? "";
      current.value = flattenArtwork(artwork);
    }
    for (const f of fields.value) draft[f.key] = asText(current.value[f.key]);
  } catch (err) {
    loadError.value = err;
  } finally {
    loading.value = false;
  }
}
watch([type, key], () => void load(), { immediate: true });

/** Only fields the contributor actually changed are proposed. */
const changed = computed(() =>
  fields.value
    .filter((f) => draft[f.key] !== asText(current.value[f.key]))
    .map((f) => ({ field: f.key, before: current.value[f.key], after: draft[f.key] === "" ? null : draft[f.key] })),
);

const rationale = ref("");
const submitting = ref(false);
const error = ref<string | null>(null);
const submittedId = ref<string | null>(null);
const ready = computed(() => changed.value.length > 0 && rationale.value.trim().length >= 3);

async function submit(): Promise<void> {
  if (!ready.value || recordId.value === null || !supportsSuggestions(type.value)) return;
  submitting.value = true;
  error.value = null;
  try {
    const changes: Record<string, unknown> = {};
    for (const row of changed.value) changes[row.field] = row.after;
    const response = await submitProposal(type.value, recordId.value, { changes, rationale: rationale.value });
    submittedId.value = response.data.id;
  } catch (err) {
    error.value = err instanceof ApiError && Object.keys(err.fieldErrors).length > 0
      ? Object.values(err.fieldErrors)[0][0]
      : err instanceof Error ? err.message : t("errors.generic");
  } finally {
    submitting.value = false;
  }
}

const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
const label = (field: string) => t(`proposals.fields.${type.value}.${field}`);
</script>

<template>
  <section>
    <ErrorState v-if="!canSuggest" :error="forbidden" />
    <ErrorState v-else-if="loadError" :error="loadError" @retry="load" />
    <Spinner v-else-if="loading" class="mx-auto my-12 block" />

    <div v-else-if="submittedId" class="mx-auto max-w-xl py-16 text-center" data-testid="submitted">
      <h1 class="text-balance text-2xl font-semibold text-ink">{{ t("proposals.submitted") }}</h1>
      <RouterLink :to="localePath('proposals')" class="mt-4 inline-block rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper">{{ t("proposals.viewMine") }}</RouterLink>
    </div>

    <form v-else class="max-w-3xl" @submit.prevent="submit">
      <h1 class="border-b-2 border-ink pb-4 text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("proposals.suggestEdit") }}</h1>
      <p class="mt-2 text-sm text-ink-muted">{{ recordLabel }}</p>

      <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <label v-for="f in fields" :key="f.key" class="text-xs text-ink-muted" :class="f.input === 'textarea' ? 'sm:col-span-2' : ''">
          {{ label(f.key) }}
          <textarea v-if="f.input === 'textarea'" v-model="draft[f.key]" rows="4" :dir="f.dir" :class="input" :data-testid="`field-${f.key}`" />
          <select v-else-if="f.input === 'select'" v-model="draft[f.key]" :class="input" :data-testid="`field-${f.key}`">
            <option v-for="o in f.options" :key="o" :value="o">{{ t(`curation.create.living_${o}`) }}</option>
          </select>
          <input v-else v-model="draft[f.key]" :type="f.input === 'number' ? 'number' : 'text'" :dir="f.dir" :class="input" :data-testid="`field-${f.key}`" />
        </label>
      </div>

      <section class="mt-8" data-testid="diff-preview">
        <h2 class="text-sm font-semibold text-ink">{{ t("proposals.preview") }}</h2>
        <p v-if="changed.length === 0" class="mt-2 text-sm text-ink-muted">{{ t("proposals.noChanges") }}</p>
        <ul v-else class="mt-2 divide-y divide-line border-y border-line">
          <li v-for="row in changed" :key="row.field" class="py-2 text-sm" data-testid="preview-row">
            <p class="text-xs font-medium text-ink-muted">{{ label(row.field) }}</p>
            <div class="mt-1 grid gap-2 sm:grid-cols-2">
              <p><DiffValue :value="row.before" tone="old" /></p>
              <p class="border-s-2 border-success ps-3"><DiffValue :value="row.after" /></p>
            </div>
          </li>
        </ul>
      </section>

      <label class="mt-6 block text-xs text-ink-muted">{{ t("proposals.rationale") }}
        <textarea v-model="rationale" rows="3" :class="input" data-testid="rationale-input" />
        <span class="mt-1 block text-pretty">{{ t("proposals.rationaleHelp") }}</span>
      </label>

      <p v-if="error" class="mt-4 text-sm text-danger" role="alert">{{ error }}</p>
      <div class="mt-6 flex gap-2">
        <button type="submit" class="rounded-md bg-ink px-5 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted" :disabled="!ready || submitting" data-testid="submit-for-review">
          {{ submitting ? t("curation.detail.saving") : t("proposals.submitForReview") }}
        </button>
      </div>
    </form>
  </section>
</template>
