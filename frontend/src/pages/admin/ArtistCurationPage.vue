<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import {
  deleteArtistPortrait, publishArtist, searchStaffOptions, setArtistPortraitRights, syncArtistEntries, syncArtistSocialLinks,
  updateArtist, updateArtistAssignment, updateArtistCuration, uploadArtistPortrait, verifyArtist,
} from "@/api/artistCuration";
import { getRecordCompleteness } from "@/api/dashboard";
import { submitCreationReview } from "@/api/editorial";
import ContactsEditor from "@/components/curation/ContactsEditor.vue";
import DraftStatusBanner from "@/components/curation/DraftStatusBanner.vue";
import EntityPicker, { type PickerOption } from "@/components/curation/EntityPicker.vue";
import EntryListEditor from "@/components/curation/EntryListEditor.vue";
import PortraitPicker from "@/components/curation/PortraitPicker.vue";
import ProfileCompletenessPanel from "@/components/curation/ProfileCompletenessPanel.vue";
import SocialLinksEditor from "@/components/curation/SocialLinksEditor.vue";
import { useArtistProfileForm, dateValueToInput } from "@/composables/useArtistProfileForm";
import RevisionHistoryPanel from "@/components/proposals/RevisionHistoryPanel.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useArtistCuration } from "@/composables/useArtistCuration";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useRecordDraft } from "@/composables/useRecordDraft";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type { ArtistDraftPayload } from "@/types/proposal";
import type { ArtistCuration, AuthLetterStatus, BioSourceType, ChecklistSection, CurationUpdate, OwnerType, PortraitRights, PreAgreementStatus, StaffOption } from "@/types/artistCuration";
import type { CompletenessSection, ProfileCompletenessSummary } from "@/types/completeness";
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

const profile = useArtistProfileForm();
const portraitRights = ref<PortraitRights>("unknown");
const portraitBusy = ref(false);

// Who is responsible for progressing this record — operational metadata, not
// editorial content, so it's applied directly (like Verify/Portrait) rather
// than staged through the draft/review pipeline.
const assignee = ref<PickerOption | null>(null);
const assigneeBusy = ref(false);
async function searchStaff(q: string): Promise<PickerOption[]> {
  const { data } = await searchStaffOptions(q);
  return data.map((s: StaffOption) => ({ id: s.id, label: `${s.name} (${s.email})` }));
}
async function onAssigneeChange(next: PickerOption | null): Promise<void> {
  assignee.value = next;
  assigneeBusy.value = true;
  actionError.value = null;
  try {
    await updateArtistAssignment(id.value, next?.id ?? null);
  } catch (err) {
    actionError.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    assigneeBusy.value = false;
  }
}

// Editors (and admins, who also hold proposals.submit) work through the draft
// pipeline: saves upsert a draft proposal instead of touching the live record.
const draftMode = computed(() => auth.can("proposals.submit"));
const {
  status: draftStatus, reviewNote: draftReviewNote, blocker: draftBlocker, pendingSubmit,
  draftPayload, dirtySections, hasContent, init: initDraft, saveSection, submit: submitDraftForReview,
  resetLocally: resetDraftLocally,
} = useRecordDraft();
// Unreviewed creation (005): nothing about this artist — verifying,
// publishing, its content read as established — is final until a reviewer
// approves the creation-review item every new artist gets automatically.
const creationPending = computed(() => curation.value !== null && curation.value.creation_approved_at === null);
const creationReview = computed(() => curation.value?.creation_review ?? null);
const isCreationCreator = computed(() => creationReview.value !== null && auth.user?.id === creationReview.value.created_by_user_id);

const draftLocked = computed(() =>
  (draftMode.value && (draftStatus.value === "pending" || draftBlocker.value !== null))
  || (creationPending.value && !isCreationCreator.value),
);

const form = reactive({
  identified_through_note: "",
  owner_type: "" as OwnerType | "",
  ref_supervisor_note: "",
  authorization_letter_status: "not_started" as AuthLetterStatus,
  owner_pre_agreement_status: "not_started" as PreAgreementStatus,
  bio_source_type: "unspecified" as BioSourceType,
});

function applyCuration(c: ArtistCuration): void {
  form.identified_through_note = c.identified_through.note ?? "";
  form.owner_type = c.contact.owner_type ?? "";
  form.ref_supervisor_note = c.contact.ref_supervisor_note ?? "";
  form.authorization_letter_status = c.pipeline.authorization_letter.status;
  form.owner_pre_agreement_status = c.pipeline.owner_pre_agreement.status;
  form.bio_source_type = c.bio.source_type;
  profile.load(c);
  portraitRights.value = c.portrait.rights_status;
  assignee.value = c.assigned_to ? { id: c.assigned_to.id, label: `${c.assigned_to.name} (${c.assigned_to.email})` } : null;
}

/** Draft sections win over live data when initializing the form models. */
function applyDraftToForm(): void {
  const p = draftPayload.value as ArtistDraftPayload;
  if (p.fields) {
    if (p.fields.nationality) profile.form.nationality = { ...p.fields.nationality };
    if (p.fields.classification) profile.form.classification = { ...p.fields.classification };
    if ("birth" in p.fields) profile.form.birthDate = dateValueToInput(p.fields.birth ?? null);
    if ("death" in p.fields) profile.form.deathDate = dateValueToInput(p.fields.death ?? null);
  }
  if (p.educations) profile.form.entries.educations = JSON.parse(JSON.stringify(p.educations));
  if (p.activities) profile.form.entries.activities = JSON.parse(JSON.stringify(p.activities));
  if (p.social_links) profile.form.socialLinks = JSON.parse(JSON.stringify(p.social_links));
  if (p.curation) {
    const cu = p.curation;
    if (cu.identified_through_note !== undefined) form.identified_through_note = cu.identified_through_note ?? "";
    if (cu.owner_type !== undefined) form.owner_type = cu.owner_type ?? "";
    if (cu.ref_supervisor_note !== undefined) form.ref_supervisor_note = cu.ref_supervisor_note ?? "";
    if (cu.authorization_letter_status) form.authorization_letter_status = cu.authorization_letter_status;
    if (cu.owner_pre_agreement_status) form.owner_pre_agreement_status = cu.owner_pre_agreement_status;
    if (cu.bio_source_type) form.bio_source_type = cu.bio_source_type;
    if (cu.contacts) profile.form.contacts = JSON.parse(JSON.stringify(cu.contacts));
  }
}

watch(curation, (c) => {
  if (!c) return;
  applyCuration(c);
}, { immediate: true });

watch(curation, async (c) => {
  if (!c || !draftMode.value) return;
  try {
    await initDraft("artists", id.value);
  } catch {
    // A failed draft lookup must not block the page; the live record still loads.
    return;
  }
  applyDraftToForm();
}, { immediate: true });

const saving = ref(false);
const saved = ref(false);
const verifying = ref(false);
const actionError = ref<string | null>(null);

const blockReason = computed(() => (curation.value ? firstBlockReason(curation.value.verify_blockers) : null));
const canVerify = computed(() =>
  curation.value !== null && curation.value.verified_status !== "verified" && blockReason.value === null && !creationPending.value,
);
const verifyTooltip = computed(() => {
  if (creationPending.value) return t("draft.creationVerifyBlocked");
  return blockReason.value ? t("curation.detail.verifyBlocked", { reason: blockReason.value }) : t("curation.detail.verifyReady");
});

const publishing = ref(false);
const publishBlockReason = computed(() => (curation.value ? firstBlockReason(curation.value.publish_blockers) : null));
const canPublish = computed(() =>
  curation.value !== null && curation.value.publication_status !== "published" && publishBlockReason.value === null && canManage.value,
);
const publishTooltip = computed(() =>
  publishBlockReason.value ? t("curation.detail.publishBlocked", { reason: publishBlockReason.value }) : t("curation.detail.publishReady"),
);
const isMet = (key: string): boolean => curation.value?.checklist.find((c) => c.key === key)?.met ?? false;
const isAdminMet = (key: string): boolean => curation.value?.admin_checklist.find((c) => c.key === key)?.met ?? false;
/** Profile summary derived from the 11-item checklist, for the completeness panel. */
const SECTION_KEYS: ChecklistSection[] = ["identity", "biography", "media"];
const profileSummary = computed<ProfileCompletenessSummary>(() => {
  const items = curation.value?.checklist ?? [];
  const met = items.filter((c) => c.met).length;
  const sections = Object.fromEntries(
    SECTION_KEYS.map((section): [ChecklistSection, CompletenessSection] => {
      const inSection = items.filter((c) => c.section === section);
      const sectionMet = inSection.filter((c) => c.met).length;
      return [section, {
        met: sectionMet,
        total: inSection.length,
        percentage: inSection.length === 0 ? 0 : Math.round((sectionMet / inSection.length) * 100),
      }];
    }),
  ) as ProfileCompletenessSummary["sections"];
  return {
    percentage: items.length === 0 ? 0 : Math.round((met / items.length) * 100),
    met_count: met,
    total_count: items.length,
    complete: items.length > 0 && met === items.length,
    sections,
    missing: items
      .filter((c) => !c.met)
      .map((c) => ({ key: c.key, label: { ar: "", en: "" }, section: c.section ?? "identity" })),
  };
});
const identityMissing = computed(() => curation.value?.checklist.filter((c) => c.section === "identity" && !c.met).length ?? 0);
const contactComplete = computed(() => isAdminMet("contact") && isAdminMet("authorization_letter"));
// "Name verified" reads its own citation data (a field citation on "name"),
// not the profile checklist — the checklist no longer carries that item.
const nameVerified = ref(false);
getRecordCompleteness("artist", id.value)
  .then(({ data }) => {
    nameVerified.value = (data.citations ?? []).some((c) => c.field_key === "name");
  })
  .catch(() => {
    nameVerified.value = false;
  });
const lifeDates = computed(() => {
  const l = curation.value?.life_dates;
  return l && (l.birth || l.death) ? `${l.birth ?? "?"} – ${l.death ?? "?"}` : null;
});
// Dates count as recorded once either the birth year or the
// death-year-or-living item is met.
const lifeDatesMissing = computed(() => !isMet("birth_year") && !isMet("death_year_or_living"));
const badgeClass = (met: boolean): string => (met ? "bg-success-soft text-success" : "bg-danger-soft text-danger");

function payload(): CurationUpdate {
  const blank = (v: string): string | null => (v.trim() === "" ? null : v.trim());
  return {
    identified_through_note: blank(form.identified_through_note),
    owner_type: form.owner_type || null,
    contacts: profile.contactsPayload(),
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
    const current = curation.value;
    // While its creation is unreviewed, the creator edits the live record
    // directly — there is no established state for the section-diff draft
    // pipeline to protect yet (005 research.md R3). draftMode's normal
    // section-diff path only applies once creation_approved_at is set.
    if (draftMode.value && !creationPending.value) {
      // Nothing changes live: each section is merged into the draft payload.
      const entries = profile.entriesPayload();
      await saveSection("fields", profile.profilePayload(current?.city, true));
      await saveSection("educations", entries.educations);
      await saveSection("activities", entries.activities);
      await saveSection("social_links", profile.socialPayload());
      await saveSection("curation", payload());
    } else {
      await updateArtist(id.value, profile.profilePayload(current?.city, true));
      await syncArtistEntries(id.value, profile.entriesPayload());
      await syncArtistSocialLinks(id.value, profile.socialPayload());
      set((await updateArtistCuration(id.value, payload())).data);
    }
    saved.value = true;
  } catch (err) {
    actionError.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    saving.value = false;
  }
}

const draftNotice = ref<string | null>(null);

async function sendForReview(): Promise<void> {
  draftNotice.value = null;
  actionError.value = null;
  try {
    await submitDraftForReview();
    draftNotice.value = t("draft.submitSuccess");
  } catch (err) {
    // A submit refusal (e.g. "nothing in this draft differs from the
    // current record", a 422 from the review-type check, ...) has a specific,
    // actionable reason; showing only the generic fallback here hid it.
    actionError.value = err instanceof Error ? err.message : t("draft.submitError");
  }
}

const creationSubmitting = ref(false);
async function sendCreationForReview(): Promise<void> {
  draftNotice.value = null;
  actionError.value = null;
  creationSubmitting.value = true;
  try {
    await submitCreationReview("artists", id.value);
    draftNotice.value = t("draft.submitSuccess");
    await retry();
  } catch (err) {
    actionError.value = err instanceof Error ? err.message : t("draft.submitError");
  } finally {
    creationSubmitting.value = false;
  }
}

function discardDraft(): void {
  draftNotice.value = null;
  actionError.value = null;
  resetDraftLocally();
  saved.value = false;
  if (curation.value) applyCuration(curation.value);
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

async function publish(): Promise<void> {
  publishing.value = true;
  actionError.value = null;
  try {
    await publishArtist(id.value);
    await retry();
  } catch (err) {
    actionError.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    publishing.value = false;
  }
}

async function onPortraitSelect(file: File): Promise<void> {
  portraitBusy.value = true;
  actionError.value = null;
  try {
    await uploadArtistPortrait(id.value, file, portraitRights.value);
    await retry();
  } catch (err) {
    actionError.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    portraitBusy.value = false;
  }
}

async function onPortraitRemove(): Promise<void> {
  portraitBusy.value = true;
  try {
    await deleteArtistPortrait(id.value);
    await retry();
  } finally {
    portraitBusy.value = false;
  }
}

async function onRightsChange(value: PortraitRights): Promise<void> {
  portraitRights.value = value;
  if (curation.value?.portrait.has_portrait) {
    portraitBusy.value = true;
    try {
      await setArtistPortraitRights(id.value, value);
      await retry();
    } finally {
      portraitBusy.value = false;
    }
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
      <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
        <RouterLink :to="localePath('admin.artists')" class="hover:text-ink">{{ t("curation.detail.back") }}</RouterLink>
        <span aria-hidden="true"> &rsaquo; </span>
        <bdi>{{ curation.legacy_code }} · {{ curation.name.en ?? curation.name.ar }}</bdi>
      </nav>

      <div class="mt-3 flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink-muted">{{ t("curation.detail.type") }}</span>
            <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="curation.verified_status === 'verified' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'" data-testid="status-badge">{{ t(`curation.status.${curation.verified_status}`) }}</span>
            <span class="rounded-sm bg-info-soft px-1.5 py-0.5 text-xs font-medium tabular-nums text-info">{{ t("curation.registry.materials", { count: curation.linked_materials.length }) }}</span>
          </div>
          <h1 class="mt-2 text-balance text-3xl font-semibold tracking-tight text-ink"><LocalizedText :text="curation.name" /></h1>
          <p class="mt-1 text-sm text-ink-muted">
            {{ pick({ ar: curation.name.en, en: curation.name.ar })?.text }}<template v-if="curation.city.ar || curation.city.en"> · <LocalizedText :text="curation.city" /></template>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" data-testid="save-button" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft disabled:opacity-50" :disabled="saving || draftLocked" @click="save">
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
          <button
            v-if="canManage"
            type="button"
            class="rounded-md bg-accent px-4 py-2 text-sm font-semibold text-surface disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted"
            data-testid="publish-button"
            :disabled="!canPublish || publishing"
            :title="publishTooltip"
            @click="publish"
          >
            {{ publishing ? t("curation.detail.publishing") : curation.publication_status === "published" ? t("curation.detail.published") : t("curation.detail.publish") }}
          </button>
        </div>
      </div>
      <DraftStatusBanner
        v-if="creationPending"
        :status="creationReview?.status ?? null"
        :blocker="isCreationCreator ? null : { proposal_id: creationReview?.proposal_id ?? '' }"
        :can-submit="true"
        :submitting="creationSubmitting"
        :review-note="creationReview?.review_note ?? null"
        :is-creation="true"
        @submit="sendCreationForReview"
      />
      <DraftStatusBanner
        v-else-if="draftMode"
        :status="draftStatus"
        :blocker="draftBlocker"
        :can-submit="dirtySections.size > 0 || hasContent"
        :submitting="pendingSubmit"
        :review-note="draftReviewNote"
        @submit="sendForReview"
        @discard="discardDraft"
      />
      <p v-if="saved" class="mt-2 text-sm text-success" data-testid="saved-feedback">{{ draftMode ? t("draft.sectionSaved") : t("curation.detail.saved") }}</p>
      <p v-if="draftNotice" class="mt-2 text-sm text-success" data-testid="draft-notice">{{ draftNotice }}</p>
      <p v-if="actionError" class="mt-2 text-sm text-danger">{{ actionError }}</p>

      <div class="mt-8 grid gap-10 lg:grid-cols-[20rem_1fr]">
        <aside class="space-y-6">
          <section class="rounded-lg border border-line bg-surface p-4">
            <PortraitPicker
              :rights="portraitRights"
              :url="curation.portrait.url"
              :busy="portraitBusy"
              @update:rights="onRightsChange"
              @select="onPortraitSelect"
              @remove="onPortraitRemove"
            />
          </section>

          <ProfileCompletenessPanel
            :summary="profileSummary"
            :items="curation.checklist"
            :tone="curation.public_visibility === 'visible' ? 'neutral' : 'danger'"
          />

          <section v-if="curation.admin_checklist.length" class="rounded-lg border border-line bg-surface p-4">
            <h2 class="text-base font-semibold text-ink">{{ t("curation.detail.adminChecklist") }}</h2>
            <ul class="mt-3 space-y-2" data-testid="admin-checklist">
              <li v-for="item in curation.admin_checklist" :key="item.key" class="flex items-center gap-2 text-sm" :class="item.met ? 'text-ink-muted' : 'text-ink'">
                <input type="checkbox" class="size-4" :checked="item.met" disabled :aria-label="t(`curation.checklistItem.${item.key}`)" />
                <span>{{ t(`curation.checklistItem.${item.key}`) }}</span>
                <span v-if="!item.supported" class="text-xs text-ink-muted">({{ t("curation.detail.unsupported") }})</span>
              </li>
            </ul>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("curation.detail.workPath") }}</h2>
            <dl class="divide-y divide-line text-sm" data-testid="work-path">
              <div class="flex items-center justify-between py-3"><dt class="text-ink">{{ t("curation.detail.materialsCount") }}</dt><dd class="rounded-sm bg-info-soft px-1.5 py-0.5 text-xs font-medium tabular-nums text-info">{{ curation.linked_materials.length }}</dd></div>
              <div class="flex items-center justify-between py-3"><dt class="text-ink">{{ t("curation.detail.authorizationLetter") }}</dt><dd class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="badgeClass(isAdminMet('authorization_letter'))">{{ t(`curation.docStatus.${form.authorization_letter_status}`) }}</dd></div>
              <div class="flex items-center justify-between py-3"><dt class="text-ink">{{ t("curation.detail.ownerPreAgreement") }}</dt><dd class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="badgeClass(['yes', 'not_applicable'].includes(form.owner_pre_agreement_status))">{{ t(`curation.docStatus.${form.owner_pre_agreement_status}`) }}</dd></div>
              <div class="flex items-center justify-between py-3"><dt class="text-ink">{{ t("curation.detail.nameVerified") }}</dt><dd class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="badgeClass(nameVerified)">{{ nameVerified ? t("curation.detail.yes") : t("curation.detail.no") }}</dd></div>
              <div class="flex items-center justify-between py-3"><dt class="text-ink">{{ t("curation.detail.publicVisibility") }}</dt><dd class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="badgeClass(curation.public_visibility === 'visible')" data-testid="visibility">{{ t(`curation.detail.${curation.public_visibility}`) }}</dd></div>
            </dl>
          </section>
        </aside>

        <div class="space-y-10">
          <section>
            <div class="flex items-baseline justify-between border-b-2 border-ink pb-2">
              <h2 class="text-sm font-semibold uppercase text-ink">{{ t("curation.detail.identity") }}</h2>
              <span v-if="identityMissing > 0" class="text-xs text-danger" data-testid="identity-missing">{{ t("curation.detail.missingFields", { count: identityMissing }) }}</span>
            </div>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
              <div><dt class="text-xs text-ink-muted">{{ t("curation.detail.artistCode") }}</dt><dd class="mt-1 rounded-md border border-line bg-surface px-3 py-2 text-ink">{{ curation.legacy_code ?? "—" }}</dd></div>
              <div><dt class="text-xs text-ink-muted">{{ t("curation.detail.identifiedThrough") }}</dt><dd><input v-model="form.identified_through_note" type="text" :class="input" /></dd></div>
              <div><dt class="text-xs text-ink-muted">{{ t("curation.detail.nameAr") }}</dt><dd class="mt-1 rounded-md border border-line bg-surface px-3 py-2 text-ink">{{ curation.name.ar ?? "—" }}</dd></div>
              <div><dt class="text-xs text-ink-muted">{{ t("curation.detail.nameEn") }}</dt><dd class="mt-1 rounded-md border border-line bg-surface px-3 py-2 text-ink">{{ curation.name.en ?? "—" }}</dd></div>
              <div>
                <dt class="flex justify-between text-xs text-ink-muted"><span>{{ t("curation.detail.nameVerified") }}</span><span v-if="!nameVerified" class="text-danger">{{ t("curation.detail.missing") }}</span></dt>
                <dd class="mt-1 rounded-md border px-3 py-2" :class="nameVerified ? 'border-line bg-surface text-ink' : 'border-danger bg-danger-soft text-danger'" data-testid="name-verified-field">
                  {{ nameVerified ? t("curation.detail.yes") : t("curation.detail.nameNotVerified") }}
                </dd>
              </div>
              <div>
                <dt class="text-xs text-ink-muted">{{ t("curation.detail.nameAsInSources") }}</dt>
                <dd class="mt-1 rounded-md border border-line bg-surface px-3 py-2 text-ink">{{ curation.name_as_in_sources?.join(" · ") || "—" }}</dd>
              </div>
              <div>
                <dt class="text-xs text-ink-muted">{{ t("curation.detail.city") }}</dt>
                <dd class="mt-1 rounded-md border border-line bg-surface px-3 py-2 text-ink"><LocalizedText v-if="curation.city.ar || curation.city.en" :text="curation.city" /><template v-else>—</template></dd>
              </div>
              <div>
                <dt class="flex justify-between text-xs text-ink-muted"><span>{{ t("curation.detail.lifeDates") }}</span><span v-if="lifeDatesMissing" class="text-danger">{{ t("curation.detail.missing") }}</span></dt>
                <dd class="mt-1 rounded-md border px-3 py-2" :class="lifeDatesMissing ? 'border-danger bg-danger-soft text-danger' : 'border-line bg-surface text-ink'" data-testid="life-dates-field">
                  {{ lifeDates ?? t("curation.detail.notRecorded") }}
                </dd>
              </div>
            </dl>
            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
              <label class="text-xs text-ink-muted">{{ t("curation.profileForm.birthDate") }}<input v-model="profile.form.birthDate" type="date" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.profileForm.deathDate") }}<input v-model="profile.form.deathDate" type="date" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.profileForm.nationalityAr") }}<input v-model="profile.form.nationality.ar" type="text" dir="rtl" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.profileForm.nationalityEn") }}<input v-model="profile.form.nationality.en" type="text" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.profileForm.classificationAr") }}<input v-model="profile.form.classification.ar" type="text" dir="rtl" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("curation.profileForm.classificationEn") }}<input v-model="profile.form.classification.en" type="text" dir="ltr" :class="input" /></label>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.detail.bio") }}</h2>
            <div class="mt-3 rounded-md border border-line bg-surface p-4">
              <p class="text-pretty text-sm text-ink">
                <LocalizedText v-if="curation.bio.ar || curation.bio.en" :text="{ ar: curation.bio.ar, en: curation.bio.en }" />
                <span v-else class="text-ink-muted">{{ t("curation.detail.noBio") }}</span>
              </p>
              <p v-if="curation.bio.source_type === 'derived_from_linked_materials'" class="mt-2 text-xs text-warn" data-testid="provisional-bio">{{ t("curation.detail.provisionalBio") }}</p>
            </div>
            <label class="mt-3 block max-w-sm text-xs text-ink-muted">
              {{ t("curation.detail.bioSource") }}
              <select v-model="form.bio_source_type" :class="input">
                <option v-for="b in BIO_SOURCES" :key="b" :value="b">{{ t(`curation.bioSource.${b}`) }}</option>
              </select>
            </label>
          </section>

          <section v-for="g in ([['educations', 'addEducation', true], ['activities', 'addActivity', false]] as const)" :key="g[0]" :data-testid="`${g[0]}-section`">
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t(`curation.profileForm.${g[0]}`) }}</h2>
            <div class="mt-4"><EntryListEditor v-model="profile.form.entries[g[0]]" :add-label="t(`curation.profileForm.${g[1]}`)" :range="g[2]" :typed="g[0] === 'activities'" /></div>
          </section>

          <section data-testid="socials-section">
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.profileForm.socials") }}</h2>
            <div class="mt-4"><SocialLinksEditor v-model="profile.form.socialLinks" /></div>
          </section>

          <section data-testid="contact-section">
            <div class="flex items-baseline justify-between border-b-2 border-ink pb-2">
              <h2 class="text-sm font-semibold uppercase text-ink">{{ t("curation.detail.contact") }}</h2>
              <span v-if="contactComplete" class="text-xs text-accent">{{ t("curation.detail.complete") }}</span>
            </div>
            <p class="mt-2 text-xs text-ink-muted">{{ t("curation.detail.contactHelp") }}</p>
            <div class="mt-4"><ContactsEditor v-model="profile.form.contacts" /></div>
            <div class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
              <label class="text-xs text-ink-muted">{{ t("curation.detail.ownerType") }}
                <select v-model="form.owner_type" :class="input">
                  <option value="">—</option>
                  <option v-for="o in OWNER_TYPES" :key="o" :value="o">{{ t(`curation.ownerTypes.${o}`) }}</option>
                </select>
              </label>
              <label class="text-xs text-ink-muted">{{ t("curation.detail.authorizationLetter") }}
                <select v-model="form.authorization_letter_status" :class="input" :aria-label="t('curation.detail.authorizationLetter')">
                  <option v-for="s in LETTER" :key="s" :value="s">{{ t(`curation.docStatus.${s}`) }}</option>
                </select>
                <span class="mt-1 block text-ink" dir="ltr">{{ curation.pipeline.authorization_letter.file_name ?? t("curation.detail.noFile") }}</span>
              </label>
              <label class="text-xs text-ink-muted">{{ t("curation.detail.ownerPreAgreement") }}
                <select v-model="form.owner_pre_agreement_status" :class="input" :aria-label="t('curation.detail.ownerPreAgreement')">
                  <option v-for="s in AGREEMENT" :key="s" :value="s">{{ t(`curation.docStatus.${s}`) }}</option>
                </select>
              </label>
              <div class="text-xs text-ink-muted" data-testid="assigned-staff">{{ t("curation.detail.assignedStaff") }}
                <EntityPicker :model-value="assignee" :search="searchStaff" :placeholder="t('curation.detail.assignedStaffPlaceholder')" @update:model-value="onAssigneeChange" />
                <p class="mt-1 text-ink-faint">{{ assigneeBusy ? t("curation.detail.saving") : t("curation.detail.assignedStaffHelp") }}</p>
              </div>
              <label class="text-xs text-ink-muted sm:col-span-2">{{ t("curation.detail.supervisor") }}<input v-model="form.ref_supervisor_note" type="text" :class="input" /></label>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("curation.detail.materials") }}</h2>
            <p v-if="curation.linked_materials.length === 0" class="mt-3 text-sm text-ink-muted">{{ t("curation.detail.noMaterials") }}</p>
            <ul v-else class="divide-y divide-line" data-testid="materials">
              <li v-for="m in curation.linked_materials" :key="m.id" class="flex items-center gap-4 py-4">
                <div class="size-14 shrink-0 rounded-sm bg-neutral-soft" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                  <p class="truncate text-base font-semibold text-ink"><LocalizedText :text="m.title" /></p>
                  <p class="text-xs text-ink-muted" dir="ltr">{{ m.legacy_ref }}</p>
                </div>
                <p class="hidden text-xs text-ink-muted sm:block">{{ m.item_type }}<template v-if="m.year"> · {{ m.year }}</template></p>
                <span class="shrink-0 rounded-sm px-1.5 py-0.5 text-xs font-medium tabular-nums" :class="m.gap_count > 0 ? SEVERITY_BADGE_CLASS.blocking : SEVERITY_BADGE_CLASS.clear">
                  {{ t("curation.detail.materialGaps", { count: m.gap_count }) }}
                </span>
              </li>
            </ul>
          </section>
          <RevisionHistoryPanel class="mt-10" type="artists" :record-id="id" manage-permission="artists.manage" @rolled-back="retry" />
        </div>
      </div>
    </template>
  </section>
</template>
