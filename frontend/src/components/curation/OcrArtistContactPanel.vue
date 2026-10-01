<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { confirmOcrArtist, getOcrArtistContact, ocrRegionCropUrl, proposeOcrArtistContact } from "@/api/archive";
import { CONTACT_PROPOSAL_STATUS_CLASS, useContactProposalText } from "@/composables/useContactProposalText";
import { ApiError } from "@/types/api";
import type { ArtistSummary, ContactProposalValue, ExtractedContactValue, OcrArtistContactState } from "@/types/ocr";

/**
 * Authorization letter → ArtistContact (OCR spec §11). Nothing here writes to
 * an artist: confirming the artist only records the reviewer's choice, and
 * proposing sends an editorial draft to the archivist review queue, where a
 * different reviewer has to approve it before the contact changes.
 */
const props = defineProps<{
  archiveItemId: number;
  /** Changes whenever a form field is transcribed, so the pre-fill follows. */
  refreshKey: string;
}>();
const { t, locale } = useI18n();

const state = ref<OcrArtistContactState | null>(null);
const loadError = ref<string | null>(null);
const actionError = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});
const busy = ref(false);

const searchName = ref("");
const selectedArtistId = ref<number | null>(null);
const form = reactive({ name: "", role_note: "", email: "", phone: "", address: "", note: "" });
const targetContactId = ref<number | null>(null);

const CONTACT_KEYS = ["email", "phone", "address"] as const;
const OPEN_STATUSES = ["draft", "pending", "changes_requested"];
const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none";

let controller: AbortController | null = null;

async function load(name?: string): Promise<void> {
  controller?.abort();
  const self = new AbortController();
  controller = self;
  loadError.value = null;
  try {
    const response = await getOcrArtistContact(props.archiveItemId, name, self.signal);
    if (controller !== self) return;
    apply(response.data);
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    loadError.value = err instanceof Error ? err.message : "Failed to load";
  }
}

/** Pre-fill only what the reviewer hasn't typed yet — a refresh never clobbers their edits. */
function apply(next: OcrArtistContactState): void {
  state.value = next;
  if (searchName.value === "") searchName.value = next.search_name ?? "";
  if (selectedArtistId.value === null) selectedArtistId.value = next.confirmed_artist?.id ?? next.candidates[0]?.artist.id ?? null;
  if (form.name === "") form.name = next.extracted.artist_name.value ?? "";
  for (const key of CONTACT_KEYS) {
    if (form[key] === "") form[key] = next.extracted[key].value ?? "";
  }
}

async function act(fn: () => Promise<{ data: OcrArtistContactState }>): Promise<void> {
  busy.value = true;
  actionError.value = null;
  fieldErrors.value = {};
  try {
    state.value = (await fn()).data;
  } catch (err) {
    if (err instanceof ApiError && Object.keys(err.fieldErrors).length > 0) {
      fieldErrors.value = err.fieldErrors;
    }
    actionError.value = err instanceof Error ? err.message : "Failed to save";
  } finally {
    busy.value = false;
  }
}

function confirmArtist(): void {
  if (selectedArtistId.value === null) return;
  const artistId = selectedArtistId.value;
  targetContactId.value = null;
  void act(() => confirmOcrArtist(props.archiveItemId, artistId));
}

function propose(): void {
  const clean = (v: string) => (v.trim() === "" ? null : v.trim());
  void act(() => proposeOcrArtistContact(props.archiveItemId, {
    name: clean(form.name), role_note: clean(form.role_note),
    email: clean(form.email), phone: clean(form.phone), address: clean(form.address),
    target_contact_id: targetContactId.value, note: clean(form.note),
  }));
}

const proposalOpen = computed(() => OPEN_STATUSES.includes(state.value?.proposal?.status ?? ""));
const awaitingReview = computed(() => state.value?.proposal?.status === "pending");
const applied = computed(() => state.value?.proposal?.status === "approved");
const canEditArtist = computed(() => !proposalOpen.value && !applied.value);
const showForm = computed(() => state.value?.confirmed_artist != null && !awaitingReview.value && !applied.value);
const hasContactValue = computed(() => CONTACT_KEYS.some((k) => form[k].trim() !== ""));

function artistLabel(artist: ArtistSummary): string {
  const primary = locale.value === "ar" ? artist.name.ar ?? artist.name.en : artist.name.en ?? artist.name.ar;
  return primary ?? `#${artist.id}`;
}

/** Where a pre-filled value came from, so the reviewer knows what they're confirming. */
function sourceHint(value: ExtractedContactValue): string {
  if (value.form_field_id === null) return t("archive.ocr.artistContact.source.notDetected");
  if (value.needs_transcription) return t("archive.ocr.artistContact.source.needsTranscription", { label: value.field_label });
  return value.method === "ocr_derived"
    ? t("archive.ocr.artistContact.source.printed", { label: value.field_label })
    : t("archive.ocr.artistContact.source.transcribed", { label: value.field_label });
}

const { isUndecided, provenanceLine, fieldLabel, statusLabel, when } = useContactProposalText();

/** What the proposed value would replace, as far as this viewer may know. */
function currentLine(v: ContactProposalValue): string {
  if (v.action === "new_contact") return t("archive.ocr.artistContact.values.newContact");
  if (v.current_value_shown) return t("archive.ocr.artistContact.values.current", { value: v.current_value ?? "—" });
  if (isUndecided(v)) return t("archive.ocr.artistContact.values.currentHidden");
  return t("archive.ocr.artistContact.values.currentNotKept");
}

watch(() => [props.archiveItemId, props.refreshKey], () => void load(), { immediate: true });
onBeforeUnmount(() => controller?.abort());
</script>

<template>
  <section v-if="state?.applicable" class="space-y-4" data-testid="ocr-artist-contact-panel">
    <div>
      <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t("archive.ocr.artistContact.title") }}</h2>
      <p class="mt-1 text-xs text-ink-muted">{{ t("archive.ocr.artistContact.explainer") }}</p>
    </div>

    <p v-if="loadError" class="text-sm text-danger" role="alert">{{ loadError }}</p>
    <p v-if="actionError" class="text-sm text-danger" role="alert" data-testid="artist-contact-error">{{ actionError }}</p>

    <!-- Step 1: which artist is this letter from? -->
    <div class="rounded-md border border-line p-3" data-testid="artist-contact-identity">
      <h3 class="text-sm font-semibold text-ink">{{ t("archive.ocr.artistContact.identityTitle") }}</h3>
      <p class="mt-1 text-xs text-ink-muted">
        <template v-if="state.extracted.artist_name.value">{{ t("archive.ocr.artistContact.nameOnDocument", { name: state.extracted.artist_name.value }) }}</template>
        <template v-else>{{ sourceHint(state.extracted.artist_name) }}</template>
      </p>

      <p v-if="state.confirmed_artist && !canEditArtist" class="mt-2 text-sm text-ink" data-testid="artist-contact-confirmed">
        {{ t("archive.ocr.artistContact.confirmedLocked", { name: artistLabel(state.confirmed_artist) }) }}
      </p>

      <template v-else>
        <form class="mt-2 flex items-center gap-2" @submit.prevent="load(searchName)">
          <input v-model="searchName" type="search" :class="input" class="!mt-0 flex-1" :placeholder="t('archive.ocr.artistContact.searchPlaceholder')" data-testid="artist-contact-search" />
          <button type="submit" class="rounded-md border border-ink px-3 py-1.5 text-xs font-medium text-ink">{{ t("archive.ocr.artistContact.search") }}</button>
        </form>

        <p v-if="state.candidates.length === 0" class="mt-2 text-xs text-ink-muted" data-testid="artist-contact-no-candidates">{{ t("archive.ocr.artistContact.noCandidates") }}</p>
        <ul v-else class="mt-2 space-y-1">
          <li v-for="c in state.candidates" :key="c.artist.id">
            <label class="flex cursor-pointer items-start gap-2 rounded-md p-2 hover:bg-neutral-soft" data-testid="artist-candidate">
              <input v-model="selectedArtistId" type="radio" name="ocr-artist" :value="c.artist.id" class="mt-1" />
              <span class="flex-1">
                <span class="text-sm font-medium text-ink">{{ artistLabel(c.artist) }}</span>
                <span v-if="c.artist.legacy_code" class="ms-2 text-xs text-ink-muted">{{ c.artist.legacy_code }}</span>
                <span class="mt-0.5 block text-xs text-ink-muted">{{ c.basis.map((b) => t(`archive.ocr.artistContact.basis.${b}`)).join(" · ") }}</span>
              </span>
              <span
                class="rounded-sm px-1.5 py-0.5 text-xs font-medium"
                :class="c.strength === 'high' ? 'bg-success-soft text-success' : c.strength === 'medium' ? 'bg-warn-soft text-warn' : 'bg-neutral-soft text-ink-muted'"
                data-testid="artist-candidate-strength"
              >{{ t(`archive.ocr.artistContact.strength.${c.strength}`) }}</span>
            </label>
          </li>
        </ul>

        <p v-if="state.confirmed_artist" class="mt-2 text-xs text-success" data-testid="artist-contact-confirmed">
          {{ t("archive.ocr.artistContact.confirmed", { name: artistLabel(state.confirmed_artist) }) }}
        </p>
        <button
          type="button"
          class="mt-2 rounded-md bg-ink px-3 py-1.5 text-xs font-medium text-paper disabled:opacity-50"
          :disabled="busy || selectedArtistId === null || selectedArtistId === state.confirmed_artist?.id"
          data-testid="artist-contact-confirm"
          @click="confirmArtist"
        >
          {{ t("archive.ocr.artistContact.confirmArtist") }}
        </button>
      </template>
    </div>

    <!-- Step 2: the reviewer confirms each contact value against the scan, then proposes. -->
    <div v-if="showForm" class="rounded-md border border-line p-3" data-testid="artist-contact-form">
      <h3 class="text-sm font-semibold text-ink">{{ t("archive.ocr.artistContact.contactTitle") }}</h3>
      <p class="mt-1 text-xs text-ink-muted">{{ t("archive.ocr.artistContact.contactExplainer") }}</p>

      <p v-if="state.proposal?.status === 'changes_requested' && state.proposal.review_note" class="mt-2 rounded-md bg-danger-soft p-2 text-sm text-ink" data-testid="artist-contact-review-note">
        {{ t("archive.ocr.artistContact.changesRequested", { note: state.proposal.review_note }) }}
      </p>

      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="text-xs text-ink-muted">{{ t("curation.profileForm.contactName") }}<input v-model="form.name" type="text" :class="input" data-testid="artist-contact-name" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.profileForm.contactRole") }}<input v-model="form.role_note" type="text" :class="input" /></label>
        <label v-for="key in CONTACT_KEYS" :key="key" class="text-xs text-ink-muted" :class="key === 'address' ? 'sm:col-span-2' : ''">
          {{ t(`archive.ocr.artistContact.fields.${key}`) }}
          <input v-model="form[key]" :type="key === 'email' ? 'email' : key === 'phone' ? 'tel' : 'text'" :dir="key === 'address' ? undefined : 'ltr'" :class="input" :data-testid="`artist-contact-${key}`" />
          <span class="mt-0.5 block" :data-testid="`artist-contact-${key}-source`">{{ sourceHint(state.extracted[key]) }}</span>
          <span v-for="msg in fieldErrors[key] ?? []" :key="msg" class="mt-0.5 block text-danger">{{ msg }}</span>
        </label>
      </div>

      <label v-if="state.can_target_existing && state.existing_contacts && state.existing_contacts.length > 0" class="mt-3 block text-xs text-ink-muted">
        {{ t("archive.ocr.artistContact.target") }}
        <select v-model="targetContactId" :class="input" data-testid="artist-contact-target">
          <option :value="null">{{ t("archive.ocr.artistContact.addNew") }}</option>
          <option v-for="c in state.existing_contacts" :key="c.id" :value="c.id">
            {{ t("archive.ocr.artistContact.updateExisting", { name: c.name ?? c.email ?? c.phone ?? `#${c.id}` }) }}
          </option>
        </select>
      </label>

      <label class="mt-3 block text-xs text-ink-muted">{{ t("archive.ocr.artistContact.note") }}<textarea v-model="form.note" rows="2" :class="input" /></label>

      <p v-for="msg in fieldErrors.values ?? []" :key="msg" class="mt-2 text-xs text-danger">{{ msg }}</p>

      <div v-if="state.can_propose" class="mt-3">
        <button
          type="button"
          class="rounded-md bg-ink px-3 py-1.5 text-xs font-medium text-paper disabled:opacity-50"
          :disabled="busy || !hasContactValue"
          data-testid="artist-contact-propose"
          @click="propose"
        >
          {{ t("archive.ocr.artistContact.propose") }}
        </button>
        <p class="mt-1 text-xs text-ink-muted">{{ t("archive.ocr.artistContact.approvalNote") }}</p>
      </div>
      <p v-else class="mt-3 text-xs text-ink-muted" data-testid="artist-contact-cannot-propose">{{ t("archive.ocr.artistContact.cannotPropose") }}</p>
    </div>

    <!-- Step 3: where the proposal stands in the review queue, value by value. -->
    <div v-if="state.proposal" class="rounded-md border border-line p-3" data-testid="artist-contact-proposal">
      <div class="flex items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-ink">{{ t("archive.ocr.artistContact.proposalTitle") }}</h3>
        <span class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink" data-testid="artist-contact-proposal-status">
          {{ state.proposal.status ? t(`proposals.statuses.${state.proposal.status}`) : "—" }}
        </span>
      </div>
      <p v-if="awaitingReview" class="mt-1 text-xs text-ink-muted">{{ t("archive.ocr.artistContact.awaitingReview") }}</p>
      <p v-else-if="applied" class="mt-1 text-xs text-success">{{ t("archive.ocr.artistContact.applied") }}</p>
      <p v-else-if="state.proposal.status === 'rejected' && state.proposal.review_note" class="mt-1 text-xs text-ink">
        {{ t("archive.ocr.artistContact.rejected", { note: state.proposal.review_note }) }}
      </p>

      <ul v-if="state.contact_proposals.length > 0" class="mt-3 space-y-3" data-testid="contact-proposal-values">
        <li
          v-for="v in state.contact_proposals"
          :key="v.id"
          class="border-t border-line pt-3 text-xs"
          :class="v.status === 'superseded' ? 'opacity-70' : ''"
          data-testid="contact-proposal-value"
        >
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-semibold text-ink">{{ fieldLabel(v) }}</span>
            <span class="rounded-sm px-1.5 py-0.5 font-medium" :class="CONTACT_PROPOSAL_STATUS_CLASS[v.status]" data-testid="contact-proposal-status">{{ statusLabel(v) }}</span>
            <span v-if="v.superseded_reason" class="text-ink-muted" data-testid="contact-proposal-superseded">
              {{ t(`archive.ocr.artistContact.values.supersededReason.${v.superseded_reason}`) }}
            </span>
          </div>

          <div class="mt-1.5 grid gap-1 sm:grid-cols-2">
            <p class="text-ink">
              {{ t("archive.ocr.artistContact.values.proposed") }}
              <span class="font-medium" :dir="v.field === 'address' ? 'auto' : 'ltr'" data-testid="contact-proposal-proposed">{{ v.proposed_value }}</span>
            </p>
            <p class="text-ink-muted" data-testid="contact-proposal-current">{{ currentLine(v) }}</p>
          </div>
          <p v-if="v.replaces_existing && isUndecided(v)" class="mt-1 text-warn" data-testid="contact-proposal-replaces">
            {{ t("archive.ocr.artistContact.values.replacesExisting") }}
          </p>

          <div class="mt-1.5 flex flex-wrap items-start gap-3">
            <img
              v-if="v.source.has_crop && v.source.region_id !== null"
              :src="ocrRegionCropUrl(archiveItemId, v.source.region_id)"
              :alt="t('archive.ocr.artistContact.values.cropAlt', { field: fieldLabel(v) })"
              class="max-h-14 rounded-sm border border-line"
              data-testid="contact-proposal-crop"
            />
            <div class="flex-1 space-y-0.5 text-ink-muted">
              <p data-testid="contact-proposal-provenance">{{ provenanceLine(v) }}</p>
              <p v-if="v.has_correction_mark" class="text-warn" data-testid="contact-proposal-correction-mark">{{ t("archive.ocr.artistContact.values.correctionMark") }}</p>
              <p>{{ t("archive.ocr.artistContact.values.proposedBy", { name: v.proposed_by?.name ?? "—", when: when(v.proposed_at) }) }}</p>
              <p v-if="v.reviewed_by || v.reviewed_at" data-testid="contact-proposal-reviewed">
                {{ t("archive.ocr.artistContact.values.reviewedBy", { name: v.reviewed_by?.name ?? "—", when: when(v.reviewed_at) }) }}
              </p>
              <p v-if="v.review_note" class="text-ink">{{ t("archive.ocr.artistContact.values.reviewNote", { note: v.review_note }) }}</p>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>
