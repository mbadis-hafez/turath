<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useI18n } from "vue-i18n";

import { createArtist } from "@/api/artistCuration";
import { ApiError } from "@/types/api";

const emit = defineEmits<{
  close: [];
  created: [id: number];
}>();

const { t } = useI18n();

const form = reactive({ ar: "", en: "", code: "", living: "unknown" as "unknown" | "living" | "deceased" });
const submitting = ref(false);
const error = ref<unknown>(null);

const fieldErrors = computed<Record<string, string[]>>(() =>
  error.value instanceof ApiError ? error.value.fieldErrors : {},
);
const firstError = (...keys: string[]): string | null => {
  for (const key of keys) if (fieldErrors.value[key]?.[0]) return fieldErrors.value[key]![0]!;
  return null;
};
const generalError = computed(() =>
  error.value instanceof Error && Object.keys(fieldErrors.value).length === 0 ? error.value.message : null,
);
const canSubmit = computed(() => (form.ar.trim() !== "" || form.en.trim() !== "") && !submitting.value);

async function submit(): Promise<void> {
  submitting.value = true;
  error.value = null;
  try {
    const response = await createArtist({
      name: { ar: form.ar.trim() || null, en: form.en.trim() || null },
      legacy_code: form.code.trim() || undefined,
      living_status: form.living,
    });
    emit("created", response.data.id);
  } catch (err) {
    error.value = err;
  } finally {
    submitting.value = false;
  }
}

const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4" role="dialog" aria-modal="true" aria-labelledby="create-artist-title" @keydown.esc="emit('close')">
    <form class="w-full max-w-md rounded-lg border border-line bg-surface p-6" @submit.prevent="submit">
      <h2 id="create-artist-title" class="text-lg font-semibold text-ink">{{ t("curation.create.title") }}</h2>
      <p class="mt-1 text-pretty text-sm text-ink-muted">{{ t("curation.create.help") }}</p>

      <div class="mt-4 space-y-3">
        <label class="block text-xs text-ink-muted">{{ t("curation.create.nameAr") }}
          <input v-model="form.ar" type="text" dir="rtl" lang="ar" :class="input" />
          <span v-if="firstError('name.ar', 'name')" class="text-danger">{{ firstError("name.ar", "name") }}</span>
        </label>
        <label class="block text-xs text-ink-muted">{{ t("curation.create.nameEn") }}
          <input v-model="form.en" type="text" dir="ltr" lang="en" :class="input" />
          <span v-if="firstError('name.en')" class="text-danger">{{ firstError("name.en") }}</span>
        </label>
        <label class="block text-xs text-ink-muted">{{ t("curation.create.code") }}
          <input v-model="form.code" type="text" dir="ltr" :class="input" />
          <span v-if="firstError('legacy_code')" class="text-danger">{{ firstError("legacy_code") }}</span>
        </label>
        <label class="block text-xs text-ink-muted">{{ t("curation.create.living") }}
          <select v-model="form.living" :class="input">
            <option value="unknown">{{ t("curation.create.living_unknown") }}</option>
            <option value="living">{{ t("curation.create.living_living") }}</option>
            <option value="deceased">{{ t("curation.create.living_deceased") }}</option>
          </select>
        </label>
      </div>

      <p v-if="generalError" class="mt-3 text-sm text-danger">{{ generalError }}</p>

      <div class="mt-6 flex justify-end gap-2">
        <button type="button" class="rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink hover:bg-neutral-soft" @click="emit('close')">{{ t("curation.create.cancel") }}</button>
        <button type="submit" class="rounded-md bg-ink px-4 py-1.5 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:opacity-50" :disabled="!canSubmit">
          {{ submitting ? t("curation.create.creating") : t("curation.create.submit") }}
        </button>
      </div>
    </form>
  </div>
</template>
