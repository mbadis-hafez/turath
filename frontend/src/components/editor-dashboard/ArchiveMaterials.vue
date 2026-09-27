<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import type { ArchiveSection, ArchiveWorkStatus } from "@/types/editorDashboard";

const props = defineProps<{
  archive: ArchiveSection;
}>();

const { t } = useI18n();
const { localePath } = useLocalePath();

const STATUS_BADGE: Record<ArchiveWorkStatus, string> = {
  incomplete: "bg-warn-soft text-warn",
  draft: "bg-neutral-soft text-ink-muted",
  under_review: "bg-info-soft text-info",
};

const counts = computed(() => [
  { key: "draft" as const, value: props.archive.draft },
  { key: "incomplete" as const, value: props.archive.incomplete },
  { key: "under_review" as const, value: props.archive.under_review },
  { key: "published" as const, value: props.archive.published },
]);

const isEmpty = computed(() => counts.value.every((count) => count.value === 0));
</script>

<template>
  <section aria-labelledby="archive-heading" class="rounded-lg border border-line bg-surface p-4">
    <div class="flex items-baseline justify-between gap-2 border-b-2 border-ink pb-2">
      <h2 id="archive-heading" class="text-sm font-semibold text-ink">
        {{ t("editorDashboard.archive.title") }}
      </h2>
      <RouterLink
        :to="localePath('admin.archive', {}, { mine: '1' })"
        class="text-xs font-medium text-accent hover:underline"
        >{{ t("editorDashboard.archive.viewAll") }}</RouterLink
      >
    </div>
    <p v-if="isEmpty" class="mt-3 text-sm text-ink-muted" data-testid="archive-empty">
      {{ t("editorDashboard.archive.empty") }}
    </p>
    <template v-else>
      <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4" data-testid="archive-counts">
        <div v-for="count in counts" :key="count.key">
          <dt class="text-xs text-ink-muted">{{ t(`editorDashboard.archive.status.${count.key}`) }}</dt>
          <dd class="mt-0.5 text-xl font-semibold tabular-nums text-ink">{{ count.value }}</dd>
        </div>
      </dl>
      <ul v-if="archive.items.length > 0" class="mt-4 space-y-2 border-t border-line pt-3">
        <li
          v-for="item in archive.items"
          :key="item.id"
          class="flex items-center justify-between gap-2 text-sm"
        >
          <RouterLink
            :to="localePath('admin.archive.edit', { id: item.id })"
            class="min-w-0 truncate text-accent hover:underline"
          >
            <LocalizedText :text="item.title" />
            <template v-if="!item.title.ar && !item.title.en">{{ t("editorDashboard.untitled") }}</template>
          </RouterLink>
          <span class="flex shrink-0 items-center gap-2">
            <span v-if="item.completeness_pct !== null" class="text-xs tabular-nums text-ink-muted">
              {{ t("editorDashboard.complete", { pct: item.completeness_pct }) }}
            </span>
            <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="STATUS_BADGE[item.status]">
              {{ t(`editorDashboard.archive.status.${item.status}`) }}
            </span>
          </span>
        </li>
      </ul>
    </template>
  </section>
</template>
