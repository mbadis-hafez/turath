<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useI18n } from "vue-i18n";

import { submitMaterial } from "@/api/submissions";
import { formatBytes, problemWith, totalBytes } from "@/components/submissions/fileRules";
import { useLocalePath } from "@/composables/useLocalePath";
import { ApiError } from "@/types/api";
import { MAX_TOTAL_BYTES, SUBMITTER_ROLES } from "@/types/submission";

const { t } = useI18n();
const { localePath } = useLocalePath();

const form = reactive({ name: "", contact: "", role: "", city: "", description: "", attested: false });
const files = ref<File[]>([]);
const fileProblems = ref<string[]>([]);
const dragging = ref(false);
const submitting = ref(false);
const bannerError = ref<string | null>(null);
const fieldErrors = ref<Record<string, string>>({});
const reference = ref<number | null>(null);
const picker = ref<HTMLInputElement | null>(null);

const STEPS = ["review", "catalog", "authorization", "publish"] as const;

const ready = computed(() =>
  form.name.trim() !== "" && form.contact.trim() !== "" && form.role !== "" && form.description.trim() !== "" && form.attested,
);

function addFiles(list: FileList | File[]): void {
  fileProblems.value = [];
  for (const file of Array.from(list)) {
    const problem = problemWith(files.value, file);
    if (problem === "type") fileProblems.value.push(t("submission.errors.type", { name: file.name }));
    else if (problem === "size") fileProblems.value.push(t("submission.errors.size", { name: file.name }));
    else files.value = [...files.value, file];
  }
}
const onPick = (e: Event) => {
  const input = e.target as HTMLInputElement;
  if (input.files) addFiles(input.files);
  input.value = "";
};
const onDrop = (e: DragEvent) => {
  dragging.value = false;
  if (e.dataTransfer?.files) addFiles(e.dataTransfer.files);
};
const removeFile = (index: number) => (files.value = files.value.filter((_, i) => i !== index));

async function submit(): Promise<void> {
  if (!ready.value || submitting.value) return;
  submitting.value = true;
  bannerError.value = null;
  fieldErrors.value = {};

  const body = new FormData();
  body.append("submitter_name", form.name.trim());
  body.append("submitter_contact", form.contact.trim());
  body.append("submitter_role", form.role);
  if (form.city.trim()) body.append("city", form.city.trim());
  body.append("description", form.description.trim());
  body.append("attestation", "1");
  for (const file of files.value) body.append("files[]", file);

  try {
    reference.value = (await submitMaterial(body)).data.id;
  } catch (err) {
    if (err instanceof ApiError && err.kind === "throttled") {
      bannerError.value = t("submission.errors.throttled");
    } else if (err instanceof ApiError && err.kind === "validation") {
      // Show the server's specific reason (bad file type, over the limit) rather than a generic failure.
      for (const [key, messages] of Object.entries(err.fieldErrors)) fieldErrors.value[key.split(".")[0]] = messages[0];
      bannerError.value = fieldErrors.value.files ?? t("submission.errors.invalid");
    } else if (err instanceof ApiError && err.kind === "network") {
      bannerError.value = t("errors.network");
    } else {
      bannerError.value = t("errors.generic");
    }
  } finally {
    submitting.value = false;
  }
}

const input = "mt-1 w-full border border-line bg-surface px-4 py-3 text-base text-ink placeholder:text-ink-faint focus:border-ink focus:outline-none";
const label = "block text-xs text-ink-muted";
</script>

<template>
  <section>
    <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
      {{ t("submission.crumbParent") }} <span aria-hidden="true">&rsaquo;</span> <span class="text-ink">{{ t("submission.crumb") }}</span>
    </nav>

    <div v-if="reference !== null" class="mx-auto mt-16 max-w-2xl" data-testid="confirmation">
      <p class="text-xs text-ink-muted">{{ t("submission.confirmationReference", { id: reference }) }}</p>
      <h1 class="mt-2 text-balance font-display text-4xl font-semibold text-ink">{{ t("submission.confirmationTitle") }}</h1>
      <p class="mt-4 text-pretty text-lg leading-relaxed text-ink-muted">{{ t("submission.confirmationBody") }}</p>
      <ol class="mt-8 border-t-2 border-ink">
        <li v-for="(step, n) in STEPS" :key="step" class="flex gap-6 border-b border-line py-4" :class="n === 0 ? 'bg-neutral-soft/60' : ''">
          <span class="w-8 shrink-0 text-xs tabular-nums text-ink-muted">{{ String(n + 1).padStart(2, "0") }}</span>
          <div>
            <p class="font-semibold text-ink">{{ t(`submission.steps.${step}.title`) }}<span v-if="n === 0" class="ms-2 text-xs font-normal text-accent-strong">{{ t("submission.next") }}</span></p>
            <p class="mt-1 text-sm text-ink-muted">{{ t(`submission.steps.${step}.body`) }}</p>
          </div>
        </li>
      </ol>
      <RouterLink :to="localePath('home')" class="mt-8 inline-block border border-ink px-6 py-3 text-sm font-semibold text-ink hover:bg-ink hover:text-paper">{{ t("submission.backHome") }}</RouterLink>
    </div>

    <div v-else class="mt-6 grid gap-12 lg:grid-cols-[minmax(0,1fr)_28rem]">
      <div>
        <p class="text-xs text-ink-muted">{{ t("submission.eyebrow") }}</p>
        <h1 class="mt-2 text-balance font-display text-4xl font-semibold leading-tight text-ink sm:text-5xl">{{ t("submission.title") }}</h1>
        <p class="mt-6 max-w-2xl text-pretty text-lg leading-relaxed text-ink-muted">{{ t("submission.intro") }}</p>

        <p class="mt-12 text-xs text-ink-muted">{{ t("submission.afterHeading") }}</p>
        <ol class="mt-2 border-t-2 border-ink" data-testid="steps">
          <li v-for="(step, n) in STEPS" :key="step" class="flex gap-6 border-b border-line py-5">
            <span class="w-8 shrink-0 text-xs tabular-nums text-ink-muted">{{ String(n + 1).padStart(2, "0") }}</span>
            <div>
              <p class="text-lg font-semibold text-ink">{{ t(`submission.steps.${step}.title`) }}</p>
              <p class="mt-1 text-pretty text-ink-muted">{{ t(`submission.steps.${step}.body`) }}</p>
            </div>
          </li>
        </ol>
      </div>

      <form class="self-start border border-ink bg-surface p-8" novalidate @submit.prevent="submit">
        <h2 class="font-display text-2xl font-semibold text-ink">{{ t("submission.formTitle") }}</h2>

        <div class="mt-6 space-y-5">
          <label :class="label">{{ t("submission.name") }} <span class="text-danger">{{ t("submission.required") }}</span>
            <input v-model="form.name" type="text" autocomplete="name" :placeholder="t('submission.namePlaceholder')" :class="input" data-testid="name" />
            <span v-if="fieldErrors.submitter_name" class="text-danger">{{ fieldErrors.submitter_name }}</span>
          </label>
          <label :class="label">{{ t("submission.contact") }} <span class="text-danger">{{ t("submission.required") }}</span>
            <input v-model="form.contact" type="text" :placeholder="t('submission.contactPlaceholder')" :class="input" data-testid="contact" />
            <span v-if="fieldErrors.submitter_contact" class="text-danger">{{ fieldErrors.submitter_contact }}</span>
          </label>
          <label :class="label">{{ t("submission.role") }} <span class="text-danger">{{ t("submission.required") }}</span>
            <select v-model="form.role" :class="input" data-testid="role">
              <option value="" disabled>{{ t("submission.rolePlaceholder") }}</option>
              <option v-for="r in SUBMITTER_ROLES" :key="r" :value="r">{{ t(`submission.roles.${r}`) }}</option>
            </select>
          </label>
          <label :class="label">{{ t("submission.city") }}
            <input v-model="form.city" type="text" :placeholder="t('submission.cityPlaceholder')" :class="input" data-testid="city" />
          </label>
          <label :class="label">{{ t("submission.description") }} <span class="text-danger">{{ t("submission.required") }}</span>
            <textarea v-model="form.description" rows="4" :placeholder="t('submission.descriptionPlaceholder')" :class="input" data-testid="description" />
            <span v-if="fieldErrors.description" class="text-danger">{{ fieldErrors.description }}</span>
          </label>

          <div>
            <p :class="label">{{ t("submission.files") }}</p>
            <div
              class="mt-1 cursor-pointer border border-dashed border-ink px-6 py-8 text-center"
              :class="dragging ? 'bg-neutral-soft' : ''"
              role="button"
              tabindex="0"
              data-testid="dropzone"
              @click="picker?.click()"
              @keydown.enter.prevent="picker?.click()"
              @dragover.prevent="dragging = true"
              @dragleave="dragging = false"
              @drop.prevent="onDrop"
            >
              <p class="font-semibold text-ink">{{ t("submission.dropTitle") }}</p>
              <p class="mt-2 text-xs text-ink-muted" dir="ltr">{{ t("submission.dropHint") }}</p>
              <input ref="picker" type="file" multiple class="sr-only" accept=".jpg,.jpeg,.tif,.tiff,.pdf,.mp3" data-testid="file-input" @change="onPick" />
            </div>
            <ul v-if="fileProblems.length" class="mt-2 space-y-1 text-sm text-danger" role="alert" data-testid="file-problems">
              <li v-for="p in fileProblems" :key="p">{{ p }}</li>
            </ul>
            <ul v-if="files.length" class="mt-3 divide-y divide-line border-y border-line text-sm" data-testid="file-list">
              <li v-for="(f, n) in files" :key="`${f.name}-${n}`" class="flex items-center justify-between gap-3 py-2">
                <span class="min-w-0 truncate text-ink" dir="ltr">{{ f.name }}</span>
                <span class="flex shrink-0 items-center gap-3 text-xs tabular-nums text-ink-muted">{{ formatBytes(f.size) }}
                  <button type="button" class="text-danger hover:underline" :aria-label="t('submission.removeFile', { name: f.name })" @click="removeFile(n)">{{ t("submission.remove") }}</button>
                </span>
              </li>
            </ul>
            <p v-if="files.length" class="mt-1 text-xs tabular-nums text-ink-muted">{{ formatBytes(totalBytes(files)) }} / {{ formatBytes(MAX_TOTAL_BYTES) }}</p>
          </div>

          <label class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed text-ink">
            <input v-model="form.attested" type="checkbox" class="mt-1 size-4 shrink-0 accent-ink" data-testid="attestation" />
            <span>{{ t("submission.attestation") }}</span>
          </label>
        </div>

        <p v-if="bannerError" class="mt-5 border-s-2 border-danger bg-danger-soft px-4 py-3 text-sm text-danger" role="alert" data-testid="banner-error">{{ bannerError }}</p>
        <button type="submit" class="mt-6 w-full bg-ink py-4 text-lg font-semibold text-paper transition-colors hover:bg-ink/85 disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted" :disabled="!ready || submitting" data-testid="submit">
          {{ submitting ? t("submission.sending") : t("submission.send") }}
        </button>
      </form>
    </div>
  </section>
</template>
