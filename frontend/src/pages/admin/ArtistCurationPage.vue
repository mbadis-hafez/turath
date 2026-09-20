<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import { updateArtistCuration, verifyArtist } from "@/api/artistCuration";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useArtistCuration } from "@/composables/useArtistCuration";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type {
  AuthLetterStatus, BioSourceType, CurationUpdate, OwnerType, PreAgreementStatus,
} from "@/types/artistCuration";
import { SEVERITY_BADGE_CLASS } from "@/utils/severity";
import { firstBlockReason } from "@/utils/verifyBlocker";

const route = useRoute();
const { t } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("artists.manage"));

const id = computed(() => Number(route.params.id));
const { curation, loading, error, retry, set } = useArtistCuration(id);

const OWNER_TYPES: OwnerType[] = ["artist", "heir_or_estate", "gallery", "institution", "other"];
const LETTER: AuthLetterStatus[] = ["not_started", "pending", "signed", "not_applicable"];
const AGREEMENT: PreAgreementStatus[] = ["not_started", "pending", "yes", "no", "not_applicable"];
const BIO_SOURCES: BioSourceType[] = ["citation", "derived_from_linked_materials", "unspecified"];

const form = reactive({
  identified_through_note: "",
  key_contact_name: "",
  owner_type: "" as OwnerType | "",
  contact_email: "",
  contact_phone: "",
  ref_supervisor_note: "",
  authorization_letter_status: "not_started" as AuthLetterStatus,
  owner_pre_agreement_status: "not_started" as PreAgreementStatus,
  bio_source_type: "unspecified" as BioSourceType,
});

watch(curation, (c) => {
  if (!c) return;
  form.identified_through_note = c.identified_through.note ?? "";
  form.key_contact_name = c.contact.key_contact_name ?? "";
  form.owner_type = c.contact.owner_type ?? "";
  form.contact_email = c.contact.contact_email ?? "";
  form.contact_phone = c.contact.contact_phone ?? "";
  form.ref_supervisor_note = c.contact.ref_supervisor_note ?? "";
  form.authorization_letter_status = c.pipeline.authorization_letter.status;
  form.owner_pre_agreement_status = c.pipeline.owner_pre_agreement.status;
  form.bio_source_type = c.bio.source_type;
}, { immediate: true });

const saving = ref(false);
const saved = ref(false);
const verifying = ref(false);
const actionError = ref<string | null>(null);

const blockReason = computed(() => (curation.value ? firstBlockReason(curation.value.verify_blockers) : null));
const canVerify = computed(() => curation.value !== null && curation.value.verified_status !== "verified" && blockReason.value === null);
const verifyTooltip = computed(() =>
  blockReason.value ? t("curation.detail.verifyBlocked", { reason: blockReason.value }) : t("curation.detail.verifyReady"),
);
const metCount = computed(() => curation.value?.checklist.filter((c) => c.met).length ?? 0);

function payload(): CurationUpdate {
  const blank = (v: string): string | null => (v.trim() === "" ? null : v.trim());
  return {
    identified_through_note: blank(form.identified_through_note),
    key_contact_name: blank(form.key_contact_name),
    owner_type: form.owner_type || null,
    contact_email: blank(form.contact_email),
    contact_phone: blank(form.contact_phone),
    ref_supervisor_note: blank(form.ref_supervisor_note),
    authorization_letter_status: form.authorization_letter_status,
    owner_pre_agreement_status: form.owner_pre_agreement_status,
    bio_source_type: form.bio_source_type,
  };
}

async function save(): Promise<void> {
  saving.value = true;
  saved.value = false;
  actionError.value = null;
  try {
    set((await updateArtistCuration(id.value, payload())).data);
    saved.value = true;
  } catch (err) {
    actionError.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    saving.value = false;
  }
}

async function verify(): Promise<void> {
  verifying.value = true;
  actionError.value = null;
  try {
    await verifyArtist(id.value);
    await retry();
  } catch (err) {
    actionError.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    verifying.value = false;
  }
}

const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <ErrorState v-else-if="error" :error="error" @retry="retry" />
    <Spinner v-else-if="loading && !curation" class="mx-auto my-12 block" />

    <template v-else-if="curation">
      <RouterLink :to="localePath('admin.artists')" class="text-sm text-ink-muted hover:text-ink">&larr; {{ t("curation.detail.back") }}</RouterLink>

      <div class="mt-3 flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="curation.verified_status === 'verified' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'">{{ t(`curation.status.${curation.verified_status}`) }}</span>
            <span class="rounded-sm bg-info-soft px-1.5 py-0.5 text-xs font-medium tabular-nums text-info">{{ t("curation.registry.materials", { count: curation.linked_materials.length }) }}</span>
          </div>
          <h1 class="mt-2 text-balance text-3xl font-semibold tracking-tight text-ink"><LocalizedText :text="curation.name" /></h1>
          <p class="mt-1 text-sm text-ink-muted">{{ pick({ ar: curation.name.en, en: curation.name.ar })?.text }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft disabled:opacity-50" :disabled="saving" @click="save">
            {{ saving ? t("curation.detail.saving") : t("curation.detail.save") }}
          </button>
          <button
            type="button"
            class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted"
            data-testid="verify-button"
            :disabled="!canVerify || verifying"
            :title="verifyTooltip"
            @click="verify"
          >
            {{ verifying ? t("curation.detail.verifying") : curation.verified_status === "verified" ? t("curation.detail.verified") : t("curation.detail.verify") }}
          </button>
        </div>
      </div>
      <p v-if="saved" class="mt-2 text-sm text-success">{{ t("curation.detail.saved") }}</p>
      <p v-if="actionError" class="mt-2 text-sm text-danger">{{ actionError }}</p>

      <div class="mt-8 grid gap-10 lg:grid-cols-[18rem_1fr]">
        <aside class="space-y-6">
          <section class="rounded-lg border p-4" :class="curation.public_visibility === 'visible' ? 'border-line bg-surface' : 'border-danger bg-danger-soft'">
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">{{ t("curation.detail.checklist") }}</h2>
            <ul class="mt-3 space-y-2" data-testid="checklist">
              <li v-for="item in curation.checklist" :key="item.key" class="flex items-center gap-2 text-sm" :class="item.met ? 'text-ink-muted' : 'text-ink'">
                <input type="checkbox" class="size-4" :checked="item.met" disabled :aria-label="t(`curation.checklistItem.${item.key}`)" />
                <span>{{ t(`curation.checklistItem.${item.key}`) }}</span>
                <span v-if="!item.supported" class="text-xs text-ink-muted">({{ t("curation.detail.unsupported") }})</span>
              </li>
            </ul>
            <p class="mt-3 text-xs tabular-nums text-ink-muted">{{ metCount }} / {{ curation.checklist.length }}</p>
          </section>

          <section class="rounded-lg border border-line bg-surface p-4">
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">{{ t("curation.detail.pipeline") }}</h2>
            <dl class="mt-3 space-y-3 text-sm">
              <div>
                <dt class="text-ink-muted">{{ t("curation.detail.authorizationLetter") }}</dt>
                <dd>
                  <select v-model="form.authorization_letter_status" :class="input" :aria-label="t('curation.detail.authorizationLetter')">
                    <option v-for="s in LETTER" :key="s" :value="s">{{ t(`curation.docStatus.${s}`) }}</option>
                  </select>
                </dd>
              </div>
              <div>
                <dt class="text-ink-muted">{{ t("curation.detail.ownerPreAgreement") }}</dt>
                <dd>
                  <select v-model="form.owner_pre_agreement_status" :class="input" :aria-label="t('curation.detail.ownerPreAgreement')">
                    <option v-for="s in AGREEMENT" :key="s" :value="s">{{ t(`curation.docStatus.${s}`) }}</option>
                  </select>
                </dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-ink-muted">{{ t("curation.detail.publicVisibility") }}</dt>
                <dd class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="curation.public_visibility === 'visible' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'" data-testid="visibility">
                  {{ t(`curation.detail.${curation.public_visibility}`) }}
                </dd>
              </div>
            </dl>
          </section>
        </aside>

        <div class="space-y-10">
          <section>
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.detail.identity") }}</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
              <div><dt class="text-xs text-ink-muted">{{ t("curation.detail.artistCode") }}</dt><dd class="mt-1 rounded-md border border-line bg-surface px-3 py-2 text-ink">{{ curation.legacy_code ?? "—" }}</dd></div>
              <div>
                <dt class="text-xs text-ink-muted">{{ t("curation.detail.identifiedThrough") }}</dt>
                <dd><input v-model="form.identified_through_note" type="text" :class="input" /></dd>
              </div>
              <div><dt class="text-xs text-ink-muted">{{ t("curation.detail.nameAr") }}</dt><dd class="mt-1 rounded-md border border-line bg-surface px-3 py-2 text-ink">{{ curation.name.ar ?? "—" }}</dd></div>
              <div><dt class="text-xs text-ink-muted">{{ t("curation.detail.nameEn") }}</dt><dd class="mt-1 rounded-md border border-line bg-surface px-3 py-2 text-ink">{{ curation.name.en ?? "—" }}</dd></div>
              <div v-if="curation.name_as_in_sources?.length" class="sm:col-span-2">
                <dt class="text-xs text-ink-muted">{{ t("curation.detail.nameAsInSources") }}</dt>
                <dd class="mt-1 rounded-md border border-line bg-surface px-3 py-2 text-ink">{{ curation.name_as_in_sources.join(" · ") }}</dd>
              </div>
            </dl>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.detail.bio") }}</h2>
            <p v-if="curation.bio.source_type === 'derived_from_linked_materials'" class="mt-3 inline-block rounded-sm bg-warn-soft px-1.5 py-0.5 text-xs font-medium text-warn" data-testid="provisional-bio">
              {{ t("curation.detail.provisionalBio") }}
            </p>
            <p class="mt-3 text-pretty text-sm text-ink">
              <LocalizedText v-if="curation.bio.ar || curation.bio.en" :text="{ ar: curation.bio.ar, en: curation.bio.en }" />
              <span v-else class="text-ink-muted">{{ t("curation.detail.noBio") }}</span>
            </p>
            <label class="mt-3 block max-w-sm text-xs text-ink-muted">
              {{ t("curation.detail.bioSource") }}
              <select v-model="form.bio_source_type" :class="input">
                <option v-for="b in BIO_SOURCES" :key="b" :value="b">{{ t(`curation.bioSource.${b}`) }}</option>
              </select>
            </label>
          </section>

          <section data-testid="contact-section">
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.detail.contact") }}</h2>
            <p class="mt-2 text-xs text-ink-muted">{{ t("curation.detail.contactHelp") }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
              <label class="text-xs text-ink-muted">{{ t("curation.detail.keyContact") }}<input v-model="form.key_contact_name" type="text" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.detail.ownerType") }}
                <select v-model="form.owner_type" :class="input">
                  <option value="">—</option>
                  <option v-for="o in OWNER_TYPES" :key="o" :value="o">{{ t(`curation.ownerTypes.${o}`) }}</option>
                </select>
              </label>
              <label class="text-xs text-ink-muted">{{ t("curation.detail.email") }}<input v-model="form.contact_email" type="email" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.detail.phone") }}<input v-model="form.contact_phone" type="tel" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted sm:col-span-2">{{ t("curation.detail.supervisor") }}<input v-model="form.ref_supervisor_note" type="text" :class="input" /></label>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.detail.materials") }}</h2>
            <p v-if="curation.linked_materials.length === 0" class="mt-3 text-sm text-ink-muted">{{ t("curation.detail.noMaterials") }}</p>
            <ul v-else class="mt-2 divide-y divide-line" data-testid="materials">
              <li v-for="m in curation.linked_materials" :key="m.id" class="flex items-center justify-between gap-3 py-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-ink"><LocalizedText :text="m.title" /></p>
                  <p class="text-xs text-ink-muted">{{ m.legacy_ref }} · {{ m.item_type }}</p>
                </div>
                <span class="shrink-0 rounded-sm px-1.5 py-0.5 text-xs font-medium tabular-nums" :class="m.gap_count > 0 ? SEVERITY_BADGE_CLASS.blocking : SEVERITY_BADGE_CLASS.clear">
                  {{ t("curation.detail.materialGaps", { count: m.gap_count }) }}
                </span>
              </li>
            </ul>
          </section>
        </div>
      </div>
    </template>
  </section>
</template>
