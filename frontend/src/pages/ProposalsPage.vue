<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { listProposals } from "@/api/proposals";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import ProposalDiffViewer from "@/components/proposals/ProposalDiffViewer.vue";
import { useAuthStore } from "@/stores/auth";
import type { PaginationMeta } from "@/types/api";
import { PROPOSAL_STATUSES, REVIEW_TYPES, type Proposal, type ProposalStatus, type ReviewType } from "@/types/proposal";

const { t } = useI18n();
const auth = useAuthStore();

const MANAGE = ["artists.manage", "artworks.manage", "archive.manage", "events.manage"];
const canReview = computed(() => MANAGE.some((p) => auth.can(p)));

const status = ref<ProposalStatus | "">("pending");
const reviewType = ref<ReviewType | "">("");
const mine = ref(false);
const page = ref(1);

const items = ref<Proposal[]>([]);
const meta = ref<PaginationMeta | null>(null);
const loading = ref(false);
const error = ref<unknown>(null);
const openId = ref<string | null>(null);
let controller: AbortController | null = null;

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
      <EmptyState v-else-if="items.length === 0" :title="t('proposals.empty2')" :description="canReview ? t('proposals.emptyHelpReviewer') : t('proposals.emptyHelpContributor')" />
      <template v-else>
        <ul class="space-y-3" data-testid="proposals-list">
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
                <span class="block text-xs text-ink-muted">{{ t("proposals.fieldsChanged", { count: Object.keys(p.field_diffs).length }) }} · {{ t(`proposals.reviewTypes.${p.review_type}`) }}</span>
              </span>
              <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="p.status === 'pending' ? 'bg-warn-soft text-warn' : 'bg-neutral-soft text-ink-muted'">{{ t(`proposals.statuses.${p.status}`) }}</span>
            </button>
            <ProposalDiffViewer v-if="openId === p.id" class="mt-2" :proposal="p" :can-review="canReview" @reviewed="load" />
          </li>
        </ul>
        <Pagination class="mt-6" :meta="meta" @change="(n: number) => (page = n)" />
      </template>
    </div>
  </section>
</template>
