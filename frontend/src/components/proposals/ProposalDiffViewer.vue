<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { getProposalDiff, requestChanges as requestProposalChanges } from "@/api/editorial";
import { approveProposal, listRevisions, rejectProposal } from "@/api/proposals";
import FieldDiffTable from "@/components/proposals/FieldDiffTable.vue";
import ProposalDocumentEvidence from "@/components/proposals/ProposalDocumentEvidence.vue";
import ProposalSectionDiffs from "@/components/proposals/ProposalSectionDiffs.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { ApiError } from "@/types/api";
import type { Proposal, ProposalConflict, RecordType, Revision } from "@/types/proposal";
import type { ProposalDocumentEvidence as DocumentEvidence, SectionDiff } from "@/types/proposalDiff";
import { formatDateTime } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const props = defineProps<{ proposal: Proposal; canReview: boolean; currentUserId: number | null }>();
const emit = defineEmits<{ reviewed: [] }>();

const { t, te, locale } = useI18n();
const { localePath } = useLocalePath();

/** Where this record's own curation/edit page lives — there is no diff for a
 * creation review (nothing to diff against), so reviewers read the proposed
 * content directly on the record's page instead (005). */
const RECORD_ROUTE: Record<RecordType, string> = {
  artists: "admin.artists.show",
  artworks: "admin.artworks.show",
  events: "admin.events.edit",
  "archive-items": "admin.archive.edit",
};
const recordHref = computed(() => {
  const type = props.proposal.record.type;
  if (type === null) return null;
  return localePath(RECORD_ROUTE[type], { id: props.proposal.record.id });
});

const busy = ref(false);
const error = ref<string | null>(null);
const note = ref("");
/** Set when the API reports the record drifted since the proposal was written (D74). */
const conflicts = ref<ProposalConflict[]>(props.proposal.conflicts ?? []);

const conflictedFields = computed(() => new Set(conflicts.value.map((c) => c.field)));
const rows = computed(() =>
  Object.entries(props.proposal.field_diffs).map(([field, diff]) => {
    const conflict = conflicts.value.find((c) => c.field === field);
    return {
      field,
      // Once drifted, the honest "before" is the live value, not the stale one.
      before: conflict ? conflict.current : diff.old_value_at_proposal_time,
      after: diff.proposed_value,
      conflicted: conflictedFields.value.has(field),
    };
  }),
);
const isPending = computed(() => props.proposal.status === "pending");
const isOwn = computed(() => props.proposal.proposed_by?.id != null && props.proposal.proposed_by.id === props.currentUserId);
const isSectioned = computed(() => props.proposal.payload != null && Object.keys(props.proposal.payload).length > 0);

const sections = ref<SectionDiff[] | null>(null);
/** The documents a value was read from, when the draft was proposed from one. */
const evidence = ref<DocumentEvidence[] | null>(null);
const diffFailed = ref(false);
let controller: AbortController | null = null;

async function loadDiff(): Promise<void> {
  controller?.abort();
  if (!isSectioned.value || props.proposal.is_creation) return;
  const self = new AbortController();
  controller = self;
  sections.value = null;
  diffFailed.value = false;
  try {
    const response = await getProposalDiff(props.proposal.id, self.signal);
    if (controller !== self) return;
    sections.value = response.data.sections;
    evidence.value = response.data.evidence ?? null;
    // Drift is known before approving, not only from a 409 afterwards.
    if (isPending.value && (response.data.conflicts ?? []).length > 0) conflicts.value = response.data.conflicts;
  } catch {
    if (controller !== self) return;
    // Fall back to the flat field_diffs rendering the legacy flow uses.
    diffFailed.value = true;
  }
}
watch(() => props.proposal.id, () => void loadDiff(), { immediate: true });

/** Creation reviews never diff (nothing to diff against, 005), but a prior
 * "changes requested" round can be followed by direct edits to the record
 * (ArtistCurationPage.save() while creation_approved_at is still null writes
 * straight to the record). Surfacing revisions logged after that round is the
 * only way a reviewer sees what actually changed since they last looked. */
const sinceRevisions = ref<Revision[]>([]);
let revisionsController: AbortController | null = null;
async function loadRevisionsSinceReview(): Promise<void> {
  revisionsController?.abort();
  sinceRevisions.value = [];
  const { is_creation, reviewed_at, record } = props.proposal;
  if (!is_creation || !reviewed_at || record.type === null) return;
  const self = new AbortController();
  revisionsController = self;
  try {
    const response = await listRevisions(record.type, record.id, self.signal);
    if (revisionsController !== self) return;
    const reviewedAt = new Date(reviewed_at).getTime();
    sinceRevisions.value = response.data.filter((r) => new Date(r.applied_at).getTime() > reviewedAt);
  } catch {
    if (revisionsController !== self) return;
    // Advisory only — the "Open the record" link still lets the reviewer check manually.
  }
}
watch(() => props.proposal.id, () => void loadRevisionsSinceReview(), { immediate: true });
/** Oldest → newest so each field's "before" is its value at the last review, and "after" its latest value. */
const sinceRows = computed(() => {
  const map = new Map<string, { field: string; before: unknown; after: unknown }>();
  for (const r of [...sinceRevisions.value].reverse()) {
    for (const [field, diff] of Object.entries(r.field_diffs)) {
      const existing = map.get(field);
      map.set(field, { field, before: existing ? existing.before : diff.old, after: diff.new });
    }
  }
  return [...map.values()];
});
const sinceLabels = computed(() =>
  sinceRevisions.value.reduce<Record<string, { ar: string | null; en: string | null }>>((acc, r) => ({ ...acc, ...r.field_labels }), {}),
);

onBeforeUnmount(() => {
  controller?.abort();
  revisionsController?.abort();
});

async function approve(confirmConflict = false): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    await approveProposal(props.proposal.id, { review_note: note.value || undefined, confirm_conflict: confirmConflict });
    emit("reviewed");
  } catch (err) {
    if (err instanceof ApiError && err.status === 409) {
      conflicts.value = (err.body as { conflicts?: ProposalConflict[] } | null)?.conflicts ?? [];
      error.value = t("proposals.conflictWarning");
    } else {
      error.value = err instanceof Error ? err.message : t("errors.generic");
    }
  } finally {
    busy.value = false;
  }
}

async function reject(): Promise<void> {
  if (note.value.trim().length < 3) {
    error.value = t("proposals.noteRequired");
    return;
  }
  busy.value = true;
  error.value = null;
  try {
    await rejectProposal(props.proposal.id, note.value);
    emit("reviewed");
  } catch (err) {
    error.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    busy.value = false;
  }
}

async function requestChanges(): Promise<void> {
  if (note.value.trim().length < 3) {
    error.value = t("proposals.noteRequiredChanges");
    return;
  }
  busy.value = true;
  error.value = null;
  try {
    await requestProposalChanges(props.proposal.id, note.value);
    emit("reviewed");
  } catch (err) {
    error.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    busy.value = false;
  }
}

const STATUS_CLASS: Record<string, string> = {
  draft: "bg-neutral-soft text-ink-muted", pending: "bg-warn-soft text-warn",
  changes_requested: "bg-danger-soft text-danger", approved: "bg-success-soft text-success",
  rejected: "bg-danger-soft text-danger", superseded: "bg-neutral-soft text-ink-muted",
};
</script>

<template>
  <article class="rounded-lg border border-line bg-surface p-5" data-testid="proposal-diff">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <div class="flex flex-wrap items-center gap-2">
          <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="STATUS_CLASS[proposal.status]" data-testid="proposal-status">{{ t(`proposals.statuses.${proposal.status}`) }}</span>
          <span class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink-muted">{{ t(`proposals.reviewTypes.${proposal.review_type}`) }}</span>
        </div>
        <p class="mt-2 text-sm text-ink-muted">
          {{ t("proposals.submittedBy", { name: proposal.proposed_by?.name ?? "—" }) }} · {{ formatDateTime(proposal.created_at, locale as AppLocale) }}
        </p>
      </div>
    </div>

    <p class="mt-3 text-pretty text-sm text-ink" data-testid="rationale">{{ proposal.rationale }}</p>

    <div v-if="conflicts.length" class="mt-4 rounded-md border border-warn bg-warn-soft p-3 text-sm text-warn" role="alert" data-testid="conflict-warning">
      <p class="font-semibold">{{ t("proposals.conflictTitle") }}</p>
      <p class="mt-1 text-pretty">{{ t("proposals.conflictBody") }}</p>
      <ul class="mt-2 list-disc ps-5">
        <li v-for="c in conflicts" :key="c.field" data-testid="conflict-item">
          <template v-if="c.collection">{{ t("proposals.conflictCollection", { collection: te(`proposals.collections.${c.field}`) ? t(`proposals.collections.${c.field}`) : c.field }) }}</template>
          <template v-else>{{ c.field }} — {{ t("proposals.conflictAgainst", { assumed: String(c.proposed_against ?? "—"), current: String(c.current ?? "—") }) }}</template>
        </li>
      </ul>
    </div>

    <div v-if="proposal.is_creation" class="mt-4 rounded-md border border-line bg-neutral-soft p-3 text-sm text-ink" data-testid="creation-review-notice">
      <p>{{ t("proposals.creationNoDiff") }}</p>
      <RouterLink v-if="recordHref" :to="recordHref" target="_blank" class="mt-2 inline-block font-medium underline" data-testid="creation-review-record-link">{{ t("proposals.creationViewRecord") }}</RouterLink>
      <div v-if="sinceRevisions.length > 0" class="mt-3 border-t border-line pt-3" data-testid="creation-revisions-since">
        <p class="font-medium text-accent-strong">{{ t("proposals.creationRevisionsSince", { count: sinceRevisions.length }) }}</p>
        <FieldDiffTable class="mt-2" :rows="sinceRows" :labels="sinceLabels" />
      </div>
    </div>
    <template v-else-if="isSectioned">
      <p v-if="sections === null && !diffFailed" class="mt-4 text-xs text-ink-muted" data-testid="diff-loading">{{ t("proposals.diffLoading") }}</p>
      <ProposalSectionDiffs v-else-if="!diffFailed && sections !== null" class="mt-4" :sections="sections" />
      <FieldDiffTable v-else class="mt-4" :rows="rows" :labels="proposal.field_labels" />
      <ProposalDocumentEvidence v-if="evidence && evidence.length > 0" :evidence="evidence" />
    </template>
    <FieldDiffTable v-else class="mt-4" :rows="rows" :labels="proposal.field_labels" />

    <ul v-if="proposal.proposed_citations.length" class="mt-4 space-y-1 text-xs text-ink-muted" data-testid="proposed-citations">
      <li v-for="(c, n) in proposal.proposed_citations" :key="n">{{ t("proposals.citationFor", { field: c.field_key }) }}</li>
    </ul>

    <p v-if="!isPending && proposal.review_note" class="mt-4 text-sm text-ink-muted" data-testid="review-note">
      {{ t("proposals.reviewedBy", { name: proposal.reviewed_by?.name ?? "—" }) }}: {{ proposal.review_note }}
    </p>

    <template v-if="canReview && isPending && !isOwn">
      <label class="mt-4 block text-xs text-ink-muted">{{ t("proposals.reviewNote") }}
        <textarea v-model="note" rows="2" class="mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none" data-testid="review-note-input" />
      </label>
      <p v-if="error" class="mt-2 text-sm text-danger" role="alert">{{ error }}</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <button v-if="conflicts.length === 0" type="button" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:opacity-50" :disabled="busy" data-testid="approve" @click="approve(false)">{{ t("proposals.approve") }}</button>
        <button v-else type="button" class="rounded-md bg-warn px-4 py-2 text-sm font-semibold text-surface disabled:opacity-50" :disabled="busy" data-testid="approve-confirm" @click="approve(true)">{{ t("proposals.approveAnyway") }}</button>
        <button type="button" class="rounded-md border border-danger px-4 py-2 text-sm font-medium text-danger hover:bg-danger-soft disabled:opacity-50" :disabled="busy" data-testid="reject" @click="reject">{{ t("proposals.reject") }}</button>
        <button type="button" class="rounded-md border border-warn px-4 py-2 text-sm font-medium text-warn hover:bg-warn-soft disabled:opacity-50" :disabled="busy" data-testid="request-changes" @click="requestChanges">{{ t("proposals.requestChanges") }}</button>
      </div>
    </template>
    <p v-else-if="canReview && isPending && isOwn" class="mt-4 text-sm text-ink-muted" data-testid="own-proposal">{{ t("proposals.ownProposal") }}</p>
    <p v-else-if="error" class="mt-2 text-sm text-danger" role="alert">{{ error }}</p>
  </article>
</template>
