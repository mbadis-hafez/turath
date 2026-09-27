<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { isLocale, type AppLocale } from "@/i18n";
import type { NeedsReviewItem } from "@/types/reviewerDashboard";
import { formatRelativeTime } from "@/utils/format";
import { reviewLinkFor } from "@/utils/reviewerDashboard";
import { SEVERITY_BADGE_CLASS } from "@/utils/severity";
import type { CompletenessSeverity } from "@/types/completeness";

const props = defineProps<{
  items: NeedsReviewItem[];
}>();

const { t, locale } = useI18n();
const { localePath } = useLocalePath();

function badgeClass(severity: string | null): string {
  return SEVERITY_BADGE_CLASS[severity as CompletenessSeverity] ?? "bg-neutral-soft text-ink-muted";
}

const rows = computed(() =>
  props.items.map((item) => ({
    item,
    to: reviewLinkFor(localePath, item.citable_type, item.review_type),
    submittedAgo: formatRelativeTime(
      item.submitted_at,
      isLocale(locale.value) ? (locale.value as AppLocale) : "ar",
    ),
  })),
);
</script>

<template>
  <section aria-labelledby="needs-review-heading" class="rounded-lg border border-line bg-surface p-4">
    <div class="flex items-center justify-between border-b-2 border-ink pb-2">
      <h2 id="needs-review-heading" class="text-sm font-semibold text-ink">
        {{ t("reviewerDashboard.needsReview.title") }}
      </h2>
      <RouterLink :to="localePath('proposals')" class="text-xs font-medium text-accent hover:underline">
        {{ t("reviewerDashboard.needsReview.viewAll") }}
      </RouterLink>
    </div>

    <p v-if="rows.length === 0" class="mt-3 text-sm text-ink-muted" data-testid="needs-review-empty">
      {{ t("reviewerDashboard.needsReview.empty") }}
    </p>

    <ul v-else class="mt-3 divide-y divide-line" data-testid="needs-review-list">
      <li v-for="row in rows" :key="row.item.id" class="py-3 first:pt-0 last:pb-0">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-xs text-ink-faint">
              {{ t(`reviewerDashboard.citableType.${row.item.citable_type}`) }}
              <span v-if="row.item.severity" class="ms-1 rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="badgeClass(row.item.severity)">
                {{ t(`dashboard.severity.${row.item.severity}`) }}
              </span>
            </p>
            <p class="mt-0.5 truncate font-medium text-ink">
              <LocalizedText v-if="row.item.title" :text="row.item.title" />
              <template v-else>{{ t("reviewerDashboard.needsReview.untitled") }}</template>
            </p>
            <p class="mt-0.5 text-xs text-ink-muted">
              <template v-if="row.item.submitted_by">{{ t("reviewerDashboard.needsReview.submittedBy", { name: row.item.submitted_by.name }) }} · </template>{{ row.submittedAgo }}
            </p>
            <p class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-ink-muted">
              <span v-if="row.item.completeness_pct !== null" class="tabular-nums">{{ t("reviewerDashboard.completeness", { pct: row.item.completeness_pct }) }}</span>
              <span v-if="row.item.verification_status">{{ t("reviewerDashboard.verification", { status: t(`reviewerDashboard.verificationStatus.${row.item.verification_status}`) }) }}</span>
            </p>
          </div>
          <RouterLink
            :to="row.to"
            class="shrink-0 rounded-md border border-line px-2.5 py-1 text-xs font-medium text-accent hover:border-accent"
            data-testid="needs-review-action"
          >
            {{ t("reviewerDashboard.needsReview.review") }}
          </RouterLink>
        </div>
      </li>
    </ul>
  </section>
</template>
