<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import { listReviewQueue, recordReviewOutcome } from "@/api/dashboard";
import { listProposals } from "@/api/proposals";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import ProposalDiffViewer from "@/components/proposals/ProposalDiffViewer.vue";
import { isLocale, type AppLocale } from "@/i18n";
import { useAuthStore } from "@/stores/auth";
import { canReviewProposals } from "@/utils/permissions";
import { formatRelativeTime } from "@/utils/format";
import { ApiError, type PaginationMeta } from "@/types/api";
import type { ReviewQueueItem } from "@/types/completeness";
import { PROPOSAL_STATUSES, REVIEW_TYPES, type Proposal, type ProposalStatus, type ReviewType } from "@/types/proposal";

const { t, locale } = useI18n();
const auth = useAuthStore();
const route = useRoute();

const canReview = computed(() => canReviewProposals((p) => auth.can(p)));

// Entry points like the reviewer dashboard deep-link into this page via
// ?status=&review_type=&mine= — read once on load, never synced back to the URL.
const initialStatus = route.query.status;
const initialReviewType = route.query.review_type;

const status = ref<ProposalStatus | "">(
  typeof initialStatus === "string" && PROPOSAL_STATUSES.includes(initialStatus as ProposalStatus)
    ? (initialStatus as ProposalStatus)
    : "pending",
);
const reviewType = ref<ReviewType | "">(
  typeof initialReviewType === "string" && REVIEW_TYPES.includes(initialReviewType as ReviewType)
    ? (initialReviewType as ReviewType)
    : "",
);
const mine = ref(route.query.mine === "1");
const page = ref(1);

const items = ref<Proposal[]>([]);
const meta = ref<PaginationMeta | null>(null);
const loading = ref(false);
const error = ref<unknown>(null);
const openId = ref<string | null>(null);
let controller: AbortController | null = null;

// Standalone review-queue entries (whole-record reviews with no proposal, e.g.
// an archive item sent for archivist review) only exist in /review-queue —
// merge them in so the queue page really lists everything awaiting review.
const standalone = ref<ReviewQueueItem[]>([]);
const showStandalone = computed(
  () => canReview.value && !mine.value && (status.value === "" || status.value === "pending"),
);
const appLocale = computed<AppLocale>(() => (isLocale(locale.value) ? locale.value : "ar"));
const busyId = ref<string | null>(null);
const rejectingId = ref<string | null>(null);
const rejectNote = ref("");
const actionError = ref<string | null>(null);

async function loadStandalone(signal: AbortSignal): Promise<void> {
  if (!showStandalone.value) {
    standalone.value = [];
    return;
  }
  try {
    const response = await listReviewQueue(
      { status: "pending", review_type: reviewType.value || undefined },
      signal,
    );
    if (controller !== null && signal !== controller.signal) return;
    standalone.value = response.data.filter((item) => !item.is_proposal_backed);
  } catch {
    // The proposals list is the primary content; a queue failure must not break it.
    standalone.value = [];
  }
}

async function load(): Promise<void> {
  controller?.abort();
  const self = new AbortController();
  controller = self;
  loading.value = true;
  error.value = null;
  try {
    const response = await listProposals(
      { status: status.value, review_type: reviewType.value, mine: mine.value ? 1 : undefined, page: page.value },
      self.signal,
    );
    if (controller !== self) return;
    items.value = response.data;
    meta.value = response.meta;
    await loadStandalone(self.signal);
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    if (controller !== self) return;
    items.value = [];
    meta.value = null;
    error.value = err;
  } finally {
    if (controller === self) loading.value = false;
  }
}

async function approveStandalone(item: ReviewQueueItem): Promise<void> {
  busyId.value = item.id;
  actionError.value = null;
  try {
    await recordReviewOutcome(item.id, { outcome: "approved" });
    await load();
  } catch (err) {
    actionError.value = err instanceof ApiError && err.message ? err.message : t("proposals.standalone.actionError");
  } finally {
    busyId.value = null;
  }
}

async function rejectStandalone(item: ReviewQueueItem): Promise<void> {
  if (rejectNote.value.trim().length < 3) return;
  busyId.value = item.id;
  actionError.value = null;
  try {
    await recordReviewOutcome(item.id, { outcome: "rejected", review_note: rejectNote.value.trim() });
    rejectingId.value = null;
    rejectNote.value = "";
    await load();
  } catch (err) {
    actionError.value = err instanceof ApiError && err.message ? err.message : t("proposals.standalone.actionError");
  } finally {
    busyId.value = null;
  }
}
watch([status, reviewType, mine], () => (page.value = 1));
watch([status, reviewType, mine, page], () => void load(), { immediate: true });
onBeforeUnmount(() => controller?.abort());

const field = "rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <section>
    <div class="border-b-2 border-ink pb-6">
      <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ canReview ? t("proposals.queueTitle") : t("proposals.mineTitle") }}</h1>
      <p v-if="meta" class="mt-1 text-sm tabular-nums text-ink-muted">{{ t("proposals.count", { count: meta.total }) }}</p>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-2">
      <select v-model="status" :class="field" :aria-label="t('proposals.status')" data-testid="status-filter">
        <option value="">{{ t("proposals.allStatuses") }}</option>
        <option v-for="s in PROPOSAL_STATUSES" :key="s" :value="s">{{ t(`proposals.statuses.${s}`) }}</option>
      </select>
      <select v-if="canReview" v-model="reviewType" :class="field" :aria-label="t('proposals.reviewType')" data-testid="review-type-filter">
        <option value="">{{ t("proposals.allReviewTypes") }}</option>
        <option v-for="r in REVIEW_TYPES" :key="r" :value="r">{{ t(`proposals.reviewTypes.${r}`) }}</option>
      </select>
      <label v-if="canReview" class="flex cursor-pointer items-center gap-2 text-sm text-ink">
        <input v-model="mine" type="checkbox" class="size-4 accent-ink" data-testid="mine-toggle" />{{ t("proposals.onlyMine") }}
      </label>
    </div>

    <div class="mt-6">
      <ErrorState v-if="error" :error="error" @retry="load" />
      <Spinner v-else-if="loading && items.length === 0" class="mx-auto my-12 block" />
      <EmptyState v-else-if="items.length === 0 && standalone.length === 0" :title="t('proposals.empty2')" :description="canReview ? t('proposals.emptyHelpReviewer') : t('proposals.emptyHelpContributor')">
        <p v-if="!canReview" class="mt-3 text-sm text-ink-muted" data-testid="empty-submit-hint">{{ t("proposals.emptyHowToSubmit") }}</p>
      </EmptyState>
      <template v-else>
        <ul v-if="items.length > 0" class="space-y-3" data-testid="proposals-list">
          <li v-for="p in items" :key="p.id" data-testid="proposal-row">
            <button
              type="button"
              class="flex w-full flex-wrap items-center justify-between gap-3 rounded-md border border-line bg-surface px-4 py-3 text-start hover:bg-neutral-soft"
              :aria-expanded="openId === p.id"
              data-testid="proposal-toggle"
              @click="openId = openId === p.id ? null : p.id"
            >
              <span>
                <span class="block text-sm font-semibold text-ink">{{ p.record.label ?? `#${p.record.id}` }}</span>
                <span class="block text-xs text-ink-muted">{{ p.is_creation ? t("proposals.newRecord") : t("proposals.fieldsChanged", { count: Object.keys(p.field_diffs).length }) }} · {{ t(`proposals.reviewTypes.${p.review_type}`) }}</span>
              </span>
              <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="p.status === 'pending' ? 'bg-warn-soft text-warn' : 'bg-neutral-soft text-ink-muted'">{{ t(`proposals.statuses.${p.status}`) }}</span>
            </button>
            <ProposalDiffViewer v-if="openId === p.id" class="mt-2" :proposal="p" :can-review="canReview" :current-user-id="auth.user?.id ?? null" @reviewed="load" />
          </li>
        </ul>

        <section v-if="standalone.length > 0" class="mt-8" data-testid="standalone-queue">
          <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">{{ t("proposals.standalone.title") }}</h2>
          <ul class="mt-3 space-y-3">
            <li v-for="q in standalone" :key="q.id" class="rounded-md border border-line bg-surface px-4 py-3" data-testid="standalone-row">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-ink">
                    <LocalizedText v-if="q.title" :text="q.title" />
                    <template v-else>#{{ q.citable_id }}</template>
                  </p>
                  <p class="text-xs text-ink-muted">
                    {{ t(`proposals.standalone.recordTypes.${q.citable_type}`) }} · {{ t(`proposals.reviewTypes.${q.review_type}`) }}<template v-if="q.submitted_by"> · {{ t("proposals.standalone.submittedBy", { name: q.submitted_by.name }) }}</template> · {{ formatRelativeTime(q.submitted_at, appLocale) }}
                  </p>
                  <p v-if="q.note" class="mt-1 text-xs text-ink-muted">{{ q.note }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                  <button
                    type="button"
                    class="rounded-md bg-ink px-3 py-1.5 text-xs font-semibold text-paper hover:bg-ink/85 disabled:opacity-50"
                    :disabled="busyId === q.id"
                    data-testid="standalone-approve"
                    @click="approveStandalone(q)"
                  >
                    {{ t("proposals.standalone.approve") }}
                  </button>
                  <button
                    type="button"
                    class="rounded-md border border-danger px-3 py-1.5 text-xs font-medium text-danger hover:bg-danger-soft disabled:opacity-50"
                    :disabled="busyId === q.id"
                    data-testid="standalone-reject"
                    @click="rejectingId = rejectingId === q.id ? null : q.id; rejectNote = ''"
                  >
                    {{ t("proposals.standalone.reject") }}
                  </button>
                </div>
              </div>
              <div v-if="rejectingId === q.id" class="mt-3">
                <textarea
                  v-model="rejectNote"
                  rows="2"
                  :placeholder="t('proposals.standalone.rejectNote')"
                  class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none"
                  data-testid="reject-note"
                />
                <button
                  type="button"
                  class="mt-2 rounded-md bg-danger px-3 py-1.5 text-xs font-semibold text-surface disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="rejectNote.trim().length < 3 || busyId === q.id"
                  data-testid="reject-confirm"
                  @click="rejectStandalone(q)"
                >
                  {{ t("proposals.standalone.confirmReject") }}
                </button>
              </div>
            </li>
          </ul>
          <p v-if="actionError" class="mt-2 text-sm text-danger" data-testid="standalone-error">{{ actionError }}</p>
        </section>

        <Pagination class="mt-6" :meta="meta" @change="(n: number) => (page = n)" />
      </template>
    </div>
  </section>
</template>
