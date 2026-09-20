<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { createArtist, updateArtistCuration } from "@/api/artistCuration";
import ErrorState from "@/components/common/ErrorState.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type { AuthLetterStatus, OwnerType, PreAgreementStatus } from "@/types/artistCuration";

const router = useRouter();
const { t } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("artists.manage"));

const OWNER_TYPES: OwnerType[] = ["artist", "heir_or_estate", "gallery", "institution", "other"];
const LETTER: AuthLetterStatus[] = ["not_started", "pending", "signed", "not_applicable"];
const AGREEMENT: PreAgreementStatus[] = ["not_started", "pending", "yes", "no", "not_applicable"];

const form = reactive({
  legacy_code: "", identified_through_note: "", name_ar: "", name_en: "", living_status: "unknown" as "unknown" | "living" | "deceased",
  city_ar: "", city_en: "", bio_ar: "", bio_en: "",
  key_contact_name: "", owner_type: "" as OwnerType | "", contact_email: "", contact_phone: "", ref_supervisor_note: "",
  authorization_letter_status: "not_started" as AuthLetterStatus, owner_pre_agreement_status: "not_started" as PreAgreementStatus,
});

const submitting = ref(false);
const error = ref<unknown>(null);
const notice = ref<string | null>(null);

const filled = (v: string): boolean => v.trim() !== "";
const blank = (v: string): string | null => (filled(v) ? v.trim() : null);

/** Live checklist: what the record would satisfy if saved now (mirrors the curation page). */
const checklist = computed(() => [
  { key: "name", met: filled(form.name_ar) && filled(form.name_en) },
  { key: "artist_code", met: filled(form.legacy_code) },
  { key: "city", met: filled(form.city_ar) || filled(form.city_en) },
  { key: "contact", met: filled(form.key_contact_name) && (filled(form.contact_email) || filled(form.contact_phone)) },
  { key: "authorization_letter", met: ["signed", "not_applicable"].includes(form.authorization_letter_status) },
  { key: "name_verified", met: false },
  { key: "life_dates", met: form.living_status === "living" },
  { key: "portrait", met: false },
]);
const metCount = computed(() => checklist.value.filter((c) => c.met).length);

const fieldErrors = computed<Record<string, string[]>>(() => (error.value instanceof ApiError ? error.value.fieldErrors : {}));
const firstError = (...keys: string[]): string | null => {
  for (const key of keys) if (fieldErrors.value[key]?.[0]) return fieldErrors.value[key]![0]!;
  return null;
};
const generalError = computed(() => (error.value instanceof Error && Object.keys(fieldErrors.value).length === 0 ? error.value.message : null));
const canSubmit = computed(() => (filled(form.name_ar) || filled(form.name_en)) && !submitting.value);

async function submit(): Promise<void> {
  submitting.value = true;
  error.value = null;
  notice.value = null;
  let id: number;
  try {
    id = (
      await createArtist({
        name: { ar: blank(form.name_ar), en: blank(form.name_en) },
        bio: { ar: blank(form.bio_ar), en: blank(form.bio_en) },
        birth: { place: { ar: blank(form.city_ar), en: blank(form.city_en) } },
        living_status: form.living_status,
        legacy_code: blank(form.legacy_code) ?? undefined,
      })
    ).data.id;
  } catch (err) {
    error.value = err;
    submitting.value = false;
    return;
  }

  try {
    await updateArtistCuration(id, {
      identified_through_note: blank(form.identified_through_note),
      key_contact_name: blank(form.key_contact_name),
      owner_type: form.owner_type || null,
      contact_email: blank(form.contact_email),
      contact_phone: blank(form.contact_phone),
      ref_supervisor_note: blank(form.ref_supervisor_note),
      authorization_letter_status: form.authorization_letter_status,
      owner_pre_agreement_status: form.owner_pre_agreement_status,
    });
  } catch {
    notice.value = t("curation.create.followUp");
  }

  submitting.value = false;
  await router.push(localePath("admin.artists.show", { id }));
}

const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <template v-else>
      <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
        <RouterLink :to="localePath('admin.artists')" class="hover:text-ink">{{ t("curation.detail.back") }}</RouterLink>
        <span aria-hidden="true"> &rsaquo; </span>{{ t("curation.create.title") }}
      </nav>

      <form @submit.prevent="submit">
        <div class="mt-3 flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
          <div>
            <span class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink-muted">{{ t("curation.detail.type") }}</span>
            <h1 class="mt-2 text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("curation.create.title") }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ t("curation.create.help") }}</p>
          </div>
          <div class="flex items-center gap-2">
            <RouterLink :to="localePath('admin.artists')" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft">{{ t("curation.create.cancel") }}</RouterLink>
            <button type="submit" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted" data-testid="create-submit" :disabled="!canSubmit">
              {{ submitting ? t("curation.create.saving") : t("curation.create.save") }}
            </button>
          </div>
        </div>
        <p v-if="generalError" class="mt-2 text-sm text-danger">{{ generalError }}</p>
        <p v-if="notice" class="mt-2 text-sm text-warn">{{ notice }}</p>

        <div class="mt-8 grid gap-10 lg:grid-cols-[20rem_1fr]">
          <aside class="space-y-6">
            <section class="rounded-lg border border-line bg-surface p-4">
              <div class="aspect-[4/3] rounded-md bg-neutral-soft" aria-hidden="true" />
              <p class="mt-3 text-pretty text-xs text-ink-muted">{{ t("curation.detail.portraitCaption") }}</p>
            </section>
            <section class="rounded-lg border border-danger bg-danger-soft p-4">
              <h2 class="text-base font-semibold text-danger">{{ t("curation.detail.checklist") }}</h2>
              <ul class="mt-3 space-y-2" data-testid="checklist">
                <li v-for="item in checklist" :key="item.key" class="flex items-center gap-2 text-sm" :class="item.met ? 'text-ink-muted' : 'text-ink'">
                  <input type="checkbox" class="size-4" :checked="item.met" disabled :aria-label="t(`curation.checklistItem.${item.key}`)" />
                  <span>{{ t(`curation.checklistItem.${item.key}`) }}</span>
                </li>
              </ul>
              <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-neutral-soft"><div class="h-full rounded-full bg-danger" :style="{ width: `${(metCount / checklist.length) * 100}%` }" /></div>
              <p class="mt-1 text-xs tabular-nums text-ink-muted">{{ t("curation.detail.requiredMet", { met: metCount, total: checklist.length }) }}</p>
            </section>
          </aside>

          <div class="space-y-10">
            <section>
              <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.detail.identity") }}</h2>
              <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <label class="text-xs text-ink-muted">{{ t("curation.detail.artistCode") }}
                  <input v-model="form.legacy_code" type="text" dir="ltr" :class="input" placeholder="AR150" />
                  <span v-if="firstError('legacy_code')" class="text-danger">{{ firstError("legacy_code") }}</span>
                </label>
                <label class="text-xs text-ink-muted">{{ t("curation.detail.identifiedThrough") }}<input v-model="form.identified_through_note" type="text" :class="input" /></label>
                <label class="text-xs text-ink-muted">{{ t("curation.detail.nameAr") }}
                  <input v-model="form.name_ar" type="text" dir="rtl" lang="ar" :class="input" />
                  <span v-if="firstError('name.ar', 'name')" class="text-danger">{{ firstError("name.ar", "name") }}</span>
                </label>
                <label class="text-xs text-ink-muted">{{ t("curation.detail.nameEn") }}
                  <input v-model="form.name_en" type="text" dir="ltr" lang="en" :class="input" />
                  <span v-if="firstError('name.en')" class="text-danger">{{ firstError("name.en") }}</span>
                </label>
                <label class="text-xs text-ink-muted">{{ t("curation.create.cityAr") }}<input v-model="form.city_ar" type="text" dir="rtl" :class="input" /></label>
                <label class="text-xs text-ink-muted">{{ t("curation.create.cityEn") }}<input v-model="form.city_en" type="text" dir="ltr" :class="input" /></label>
                <label class="text-xs text-ink-muted">{{ t("curation.create.living") }}
                  <select v-model="form.living_status" :class="input">
                    <option value="unknown">{{ t("curation.create.living_unknown") }}</option>
                    <option value="living">{{ t("curation.create.living_living") }}</option>
                    <option value="deceased">{{ t("curation.create.living_deceased") }}</option>
                  </select>
                </label>
              </div>
            </section>

            <section>
              <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.detail.bio") }}</h2>
              <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="text-xs text-ink-muted">{{ t("curation.create.bioAr") }}<textarea v-model="form.bio_ar" rows="4" dir="rtl" :class="input" /></label>
                <label class="text-xs text-ink-muted">{{ t("curation.create.bioEn") }}<textarea v-model="form.bio_en" rows="4" dir="ltr" :class="input" /></label>
              </div>
            </section>

            <section data-testid="contact-section">
              <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.detail.contact") }}</h2>
              <p class="mt-2 text-xs text-ink-muted">{{ t("curation.detail.contactHelp") }}</p>
              <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <label class="text-xs text-ink-muted">{{ t("curation.detail.keyContact") }}<input v-model="form.key_contact_name" type="text" :class="input" /></label>
                <label class="text-xs text-ink-muted">{{ t("curation.detail.ownerType") }}
                  <select v-model="form.owner_type" :class="input"><option value="">—</option><option v-for="o in OWNER_TYPES" :key="o" :value="o">{{ t(`curation.ownerTypes.${o}`) }}</option></select>
                </label>
                <label class="text-xs text-ink-muted">{{ t("curation.detail.email") }}<input v-model="form.contact_email" type="email" dir="ltr" :class="input" /></label>
                <label class="text-xs text-ink-muted">{{ t("curation.detail.phone") }}<input v-model="form.contact_phone" type="tel" dir="ltr" :class="input" /></label>
                <label class="text-xs text-ink-muted">{{ t("curation.detail.authorizationLetter") }}
                  <select v-model="form.authorization_letter_status" :class="input"><option v-for="s in LETTER" :key="s" :value="s">{{ t(`curation.docStatus.${s}`) }}</option></select>
                </label>
                <label class="text-xs text-ink-muted">{{ t("curation.detail.ownerPreAgreement") }}
                  <select v-model="form.owner_pre_agreement_status" :class="input"><option v-for="s in AGREEMENT" :key="s" :value="s">{{ t(`curation.docStatus.${s}`) }}</option></select>
                </label>
                <label class="text-xs text-ink-muted sm:col-span-2">{{ t("curation.detail.supervisor") }}<input v-model="form.ref_supervisor_note" type="text" :class="input" /></label>
              </div>
            </section>
          </div>
        </div>
      </form>
    </template>
  </section>
</template>
