<script setup lang="ts">
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";

import { approveProposal, rejectProposal } from "@/api/proposals";
import FieldDiffTable from "@/components/proposals/FieldDiffTable.vue";
import { ApiError } from "@/types/api";
import type { Proposal, ProposalConflict } from "@/types/proposal";
import { formatDateTime } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const props = defineProps<{ proposal: Proposal; canReview: boolean }>();
const emit = defineEmits<{ reviewed: [] }>();

const { t, locale } = useI18n();

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

const STATUS_CLASS: Record<string, string> = {
  pending: "bg-warn-soft text-warn", approved: "bg-success-soft text-success",
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
        <li v-for="c in conflicts" :key="c.field">
          {{ c.field }} — {{ t("proposals.conflictAgainst", { assumed: String(c.proposed_against ?? "—"), current: String(c.current ?? "—") }) }}
        </li>
      </ul>
    </div>

    <FieldDiffTable class="mt-4" :rows="rows" :labels="proposal.field_labels" />

    <ul v-if="proposal.proposed_citations.length" class="mt-4 space-y-1 text-xs text-ink-muted" data-testid="proposed-citations">
      <li v-for="(c, n) in proposal.proposed_citations" :key="n">{{ t("proposals.citationFor", { field: c.field_key }) }}</li>
    </ul>

    <p v-if="!isPending && proposal.review_note" class="mt-4 text-sm text-ink-muted" data-testid="review-note">
      {{ t("proposals.reviewedBy", { name: proposal.reviewed_by?.name ?? "—" }) }}: {{ proposal.review_note }}
    </p>

    <template v-if="canReview && isPending">
      <label class="mt-4 block text-xs text-ink-muted">{{ t("proposals.reviewNote") }}
        <textarea v-model="note" rows="2" class="mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none" data-testid="review-note-input" />
      </label>
      <p v-if="error" class="mt-2 text-sm text-danger" role="alert">{{ error }}</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <button v-if="conflicts.length === 0" type="button" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:opacity-50" :disabled="busy" data-testid="approve" @click="approve(false)">{{ t("proposals.approve") }}</button>
        <button v-else type="button" class="rounded-md bg-warn px-4 py-2 text-sm font-semibold text-surface disabled:opacity-50" :disabled="busy" data-testid="approve-confirm" @click="approve(true)">{{ t("proposals.approveAnyway") }}</button>
        <button type="button" class="rounded-md border border-danger px-4 py-2 text-sm font-medium text-danger hover:bg-danger-soft disabled:opacity-50" :disabled="busy" data-testid="reject" @click="reject">{{ t("proposals.reject") }}</button>
      </div>
    </template>
    <p v-else-if="error" class="mt-2 text-sm text-danger" role="alert">{{ error }}</p>
  </article>
</template>
