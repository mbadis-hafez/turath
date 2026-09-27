<script setup lang="ts">
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import type { VerificationIssueItem } from "@/types/reviewerDashboard";
import { reviewLinkFor } from "@/utils/reviewerDashboard";

defineProps<{
  items: VerificationIssueItem[];
}>();

const { t } = useI18n();
const { localePath } = useLocalePath();
</script>

<template>
  <section aria-labelledby="verification-heading" class="rounded-lg border border-line bg-surface p-4">
    <h2 id="verification-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("reviewerDashboard.verificationIssues.title") }}
    </h2>
    <p v-if="items.length === 0" class="mt-3 text-sm text-ink-muted" data-testid="verification-empty">
      {{ t("reviewerDashboard.verificationIssues.empty") }}
    </p>
    <ul v-else class="mt-3 divide-y divide-line" data-testid="verification-list">
      <li
        v-for="issue in items"
        :key="`${issue.kind}:${issue.citable_type}:${issue.id}:${issue.field_key ?? ''}`"
        class="py-3 first:pt-0 last:pb-0"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-xs text-ink-faint">
              {{ issue.citable_type ? t(`reviewerDashboard.citableType.${issue.citable_type}`) : "" }}
              <span class="ms-1 rounded-sm bg-warn-soft px-1.5 py-0.5 text-xs font-medium text-warn">
                {{ t(`reviewerDashboard.verificationIssues.kind.${issue.kind}`) }}
              </span>
            </p>
            <p class="mt-0.5 truncate font-medium text-ink">
              <LocalizedText v-if="issue.title" :text="issue.title" />
              <template v-else>{{ t("reviewerDashboard.needsReview.untitled") }}</template>
            </p>
            <ul v-if="issue.issues && issue.issues.length > 0" class="mt-0.5 text-xs text-ink-muted">
              <li v-for="(message, i) in issue.issues" :key="i">{{ message }}</li>
            </ul>
            <p v-if="issue.completeness_pct !== undefined" class="mt-1 text-xs tabular-nums text-ink-muted">
              {{ t("reviewerDashboard.completeness", { pct: issue.completeness_pct }) }}
            </p>
          </div>
          <RouterLink
            :to="reviewLinkFor(localePath, issue.citable_type)"
            class="shrink-0 rounded-md border border-line px-2.5 py-1 text-xs font-medium text-accent hover:border-accent"
          >
            {{ t("reviewerDashboard.needsReview.review") }}
          </RouterLink>
        </div>
      </li>
    </ul>
  </section>
</template>
