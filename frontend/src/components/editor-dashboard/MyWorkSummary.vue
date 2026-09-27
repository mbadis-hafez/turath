<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import type { RouteLocationRaw } from "vue-router";

import { useLocalePath } from "@/composables/useLocalePath";
import type { MyWorkSummary } from "@/types/editorDashboard";

const props = defineProps<{
  myWork: MyWorkSummary;
}>();

const { t } = useI18n();
const { localePath } = useLocalePath();

interface WorkCard {
  key: keyof MyWorkSummary;
  to: RouteLocationRaw;
  warn?: boolean;
}

const cards = computed<WorkCard[]>(() => [
  {
    key: "drafts",
    to: { ...localePath("dashboard"), hash: "#continue-working" },
  },
  {
    key: "in_progress",
    to: localePath("proposals", {}, { status: "draft", mine: "1" }),
  },
  {
    key: "ready_for_review",
    to: localePath("proposals", {}, { status: "pending", mine: "1" }),
  },
  {
    key: "changes_requested",
    to: localePath("proposals", {}, { status: "changes_requested", mine: "1" }),
    warn: true,
  },
]);
</script>

<template>
  <section aria-labelledby="my-work-heading" class="mt-8">
    <h2 id="my-work-heading" class="text-xs font-semibold tracking-widest text-ink-faint uppercase">
      {{ t("editorDashboard.myWork.title") }}
    </h2>
    <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <RouterLink
        v-for="card in cards"
        :key="card.key"
        :to="card.to"
        class="rounded-lg border p-4 transition-colors"
        :class="
          card.warn && props.myWork[card.key] > 0
            ? 'border-warn bg-warn-soft hover:border-warn'
            : 'border-line bg-surface hover:border-accent'
        "
        :data-testid="`my-work-${card.key}`"
      >
        <p class="text-sm text-ink-muted">{{ t(`editorDashboard.myWork.${card.key}`) }}</p>
        <p
          class="mt-1 text-3xl font-semibold tabular-nums"
          :class="card.warn && props.myWork[card.key] > 0 ? 'text-warn' : 'text-ink'"
        >
          {{ props.myWork[card.key] }}
        </p>
      </RouterLink>
    </div>
  </section>
</template>
