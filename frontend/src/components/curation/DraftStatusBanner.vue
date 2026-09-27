<script setup lang="ts">
import { ref } from "vue";
import { useI18n } from "vue-i18n";

import ConfirmDialog from "@/components/common/ConfirmDialog.vue";
import type { ProposalStatus } from "@/types/proposal";

defineProps<{
  status: ProposalStatus | null;
  blocker: { proposal_id: string } | null;
  canSubmit: boolean;
  submitting: boolean;
  reviewNote: string | null;
  /** True for a record's creation-review item (005) — no discard action, and the draft badge reads as "new record" rather than "unsaved changes." */
  isCreation?: boolean;
}>();

const emit = defineEmits<{
  submit: [];
  discard: [];
}>();

const { t } = useI18n();

const confirmDiscard = ref(false);
</script>

<template>
  <div
    v-if="blocker"
    class="mt-4 rounded-lg border border-warn bg-warn-soft p-4"
    data-testid="draft-banner"
    data-state="blocked"
  >
    <p class="text-sm font-semibold text-warn">{{ t("draft.blockedTitle") }}</p>
    <p class="mt-1 text-sm text-ink">{{ t("draft.blockedBody") }}</p>
  </div>

  <div
    v-else-if="status === 'pending'"
    class="mt-4 rounded-lg border border-info bg-info-soft p-4"
    data-testid="draft-banner"
    data-state="pending"
  >
    <p class="text-sm font-semibold text-info">{{ t("draft.awaitingReview") }}</p>
  </div>

  <div
    v-else-if="status === 'changes_requested' || status === 'draft'"
    class="mt-4 rounded-lg border p-4"
    :class="status === 'draft' ? 'border-line bg-neutral-soft' : 'border-danger bg-danger-soft'"
    data-testid="draft-banner"
    :data-state="status"
  >
    <p class="text-sm font-semibold" :class="status === 'draft' ? 'text-ink' : 'text-danger'">
      {{ status === "draft" ? t(isCreation ? "draft.creationBadge" : "draft.draftBadge") : t("draft.changesRequested") }}
    </p>
    <p v-if="status === 'draft' && isCreation" class="mt-1 text-sm text-ink-muted">{{ t("draft.creationHint") }}</p>
    <p v-if="status === 'changes_requested' && reviewNote" class="mt-1 text-sm text-ink" data-testid="reviewer-note">
      {{ t("draft.reviewerNote", { note: reviewNote }) }}
    </p>
    <p v-if="status === 'changes_requested'" class="mt-1 text-sm text-ink-muted">{{ t("draft.changesRequestedHint") }}</p>
    <div class="mt-3 flex flex-wrap items-center gap-2">
      <button
        type="button"
        class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted"
        data-testid="send-for-review"
        :disabled="!canSubmit || submitting"
        :title="!canSubmit ? t('draft.sendForReviewHint') : undefined"
        @click="emit('submit')"
      >
        {{ submitting ? t("common.loading") : t("draft.sendForReview") }}
      </button>
      <button
        v-if="status === 'draft' && !isCreation"
        type="button"
        class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-surface"
        data-testid="discard-draft"
        @click="confirmDiscard = true"
      >
        {{ t("draft.discardDraft") }}
      </button>
    </div>
  </div>

  <p v-else class="mt-4 text-xs text-ink-muted" data-testid="draft-hint">{{ t("draft.intro") }}</p>

  <ConfirmDialog
    :open="confirmDiscard"
    :title="t('draft.discardConfirmTitle')"
    :description="t('draft.discardConfirmBody')"
    :confirm-label="t('draft.discardConfirmAction')"
    :cancel-label="t('draft.discardCancel')"
    @confirm="confirmDiscard = false; emit('discard')"
    @cancel="confirmDiscard = false"
  />
</template>
