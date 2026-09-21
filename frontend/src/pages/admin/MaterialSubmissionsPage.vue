<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { listSubmissions } from "@/api/submissions";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import SubmissionDetail from "@/components/submissions/SubmissionDetail.vue";
import { useAuthStore } from "@/stores/auth";
import { ApiError, type PaginationMeta } from "@/types/api";
import { SUBMISSION_STATUSES, type SubmissionRow, type SubmissionStatus } from "@/types/submission";
import { formatDateTime } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const { t, locale } = useI18n();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canReview = computed(() => auth.can("materials.review"));

const status = ref<SubmissionStatus | "">("");
const page = ref(1);
const items = ref<SubmissionRow[]>([]);
const meta = ref<PaginationMeta | null>(null);
const loading = ref(false);
const error = ref<unknown>(null);
const openId = ref<number | null>(null);
let controller: AbortController | null = null;

async function load(): Promise<void> {
  if (!canReview.value) return;
  controller?.abort();
  const self = new AbortController();
  controller = self;
  loading.value = true;
  error.value = null;
  try {
    const response = await listSubmissions({ status: status.value, page: page.value }, self.signal);
    if (controller !== self) return;
    items.value = response.data;
    meta.value = response.meta;
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
watch(status, () => (page.value = 1));
watch([status, page], () => void load(), { immediate: true });
onBeforeUnmount(() => controller?.abort());

const STATUS_CLASS: Record<SubmissionStatus, string> = {
  submitted: "bg-danger-soft text-danger", initial_review: "bg-warn-soft text-warn", cataloging: "bg-warn-soft text-warn",
  authorization_pending: "bg-info-soft text-info", published: "bg-success-soft text-success",
  rejected: "bg-neutral-soft text-ink-muted", withdrawn: "bg-neutral-soft text-ink-muted",
};
const field = "rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <section>
    <ErrorState v-if="!canReview" :error="forbidden" />
    <template v-else>
      <div class="border-b-2 border-ink pb-6">
        <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("submissions.title") }}</h1>
        <p v-if="meta" class="mt-1 text-sm tabular-nums text-ink-muted">{{ t("submissions.count", { count: meta.total }) }}</p>
      </div>

      <div class="mt-6">
        <select v-model="status" :class="field" :aria-label="t('proposals.status')" data-testid="status-filter">
          <option value="">{{ t("proposals.allStatuses") }}</option>
          <option v-for="s in SUBMISSION_STATUSES" :key="s" :value="s">{{ t(`submissions.statuses.${s}`) }}</option>
        </select>
      </div>

      <div class="mt-6">
        <ErrorState v-if="error" :error="error" @retry="load" />
        <Spinner v-else-if="loading && items.length === 0" class="mx-auto my-12 block" />
        <EmptyState v-else-if="items.length === 0" :title="t('submissions.empty')" :description="t('submissions.emptyHelp')" />
        <template v-else>
          <ul class="space-y-3" data-testid="submissions-list">
            <li v-for="s in items" :key="s.id" data-testid="submission-row">
              <button type="button" class="flex w-full flex-wrap items-center justify-between gap-3 rounded-md border border-line bg-surface px-4 py-3 text-start hover:bg-neutral-soft" :aria-expanded="openId === s.id" data-testid="submission-toggle" @click="openId = openId === s.id ? null : s.id">
                <span class="min-w-0">
                  <span class="block text-sm font-semibold text-ink">#{{ s.id }} · {{ t(`submission.roles.${s.submitter_role}`) }}<template v-if="s.city"> · {{ s.city }}</template></span>
                  <span class="block max-w-2xl truncate text-xs text-ink-muted">{{ s.description }}</span>
                </span>
                <span class="flex items-center gap-3 text-xs tabular-nums text-ink-muted">
                  {{ t("submissions.files", { count: s.file_count }) }} · {{ formatDateTime(s.submitted_at, locale as AppLocale) }}
                  <span class="rounded-sm px-1.5 py-0.5 font-medium" :class="STATUS_CLASS[s.status]" data-testid="row-status">{{ t(`submissions.statuses.${s.status}`) }}</span>
                </span>
              </button>
              <SubmissionDetail v-if="openId === s.id" :id="s.id" class="mt-2" @changed="load" />
            </li>
          </ul>
          <Pagination class="mt-6" :meta="meta" @change="(n: number) => (page = n)" />
        </template>
      </div>
    </template>
  </section>
</template>
