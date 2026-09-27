<script setup lang="ts">
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import type { ReturnedContentItem } from "@/types/reviewerDashboard";
import { reviewLinkFor } from "@/utils/reviewerDashboard";

defineProps<{
  items: ReturnedContentItem[];
}>();

const { t } = useI18n();
const { localePath } = useLocalePath();
</script>

<template>
  <section aria-labelledby="returned-heading" class="rounded-lg border border-line bg-surface p-4">
    <div class="flex items-center justify-between border-b-2 border-ink pb-2">
      <h2 id="returned-heading" class="text-sm font-semibold text-ink">
        {{ t("reviewerDashboard.returnedContent.title") }}
      </h2>
      <RouterLink
        :to="localePath('proposals', {}, { status: 'pending' })"
        class="text-xs font-medium text-accent hover:underline"
      >
        {{ t("reviewerDashboard.returnedContent.viewAll") }}
      </RouterLink>
    </div>
    <p v-if="items.length === 0" class="mt-3 text-sm text-ink-muted" data-testid="returned-empty">
      {{ t("reviewerDashboard.returnedContent.empty") }}
    </p>
    <ul v-else class="mt-3 divide-y divide-line" data-testid="returned-list">
      <li v-for="item in items" :key="item.id" class="py-3 first:pt-0 last:pb-0">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-xs text-ink-faint">
              {{ item.citable_type ? t(`reviewerDashboard.citableType.${item.citable_type}`) : "" }}
            </p>
            <p class="mt-0.5 truncate font-medium text-ink">
              <LocalizedText v-if="item.title" :text="item.title" />
              <template v-else>{{ t("reviewerDashboard.needsReview.untitled") }}</template>
            </p>
            <p v-if="item.proposed_by" class="mt-0.5 text-xs text-ink-muted">
              {{ t("reviewerDashboard.needsReview.submittedBy", { name: item.proposed_by.name }) }}
            </p>
            <p v-if="item.previous_review_note" class="mt-1 truncate text-xs text-ink-muted">
              {{ t("reviewerDashboard.returnedContent.previousNote", { note: item.previous_review_note }) }}
            </p>
          </div>
          <RouterLink
            :to="reviewLinkFor(localePath, item.citable_type, item.review_type)"
            class="shrink-0 rounded-md border border-line px-2.5 py-1 text-xs font-medium text-accent hover:border-accent"
          >
            {{ t("reviewerDashboard.returnedContent.reReview") }}
          </RouterLink>
        </div>
      </li>
    </ul>
  </section>
</template>
