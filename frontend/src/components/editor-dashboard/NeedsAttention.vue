<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import type { AttentionItem } from "@/types/editorDashboard";
import { attentionActionFor, RECORD_ROUTE_NAMES } from "@/utils/editorDashboard";
import { SEVERITY_BADGE_CLASS } from "@/utils/severity";
import type { CompletenessSeverity } from "@/types/completeness";

const props = defineProps<{
  items: AttentionItem[];
}>();

const { t } = useI18n();
const { localePath } = useLocalePath();

function badgeClass(severity: string | null): string {
  return (
    SEVERITY_BADGE_CLASS[severity as CompletenessSeverity] ??
    "bg-neutral-soft text-ink-muted"
  );
}

const rows = computed(() =>
  props.items.map((item) => ({
    item,
    to: localePath(RECORD_ROUTE_NAMES[item.entity_type], { id: item.id }),
    action: attentionActionFor(item.kind),
  })),
);
</script>

<template>
  <section aria-labelledby="attention-heading" class="rounded-lg border border-line bg-surface p-4">
    <h2 id="attention-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("editorDashboard.attention.title") }}
    </h2>
    <p v-if="rows.length === 0" class="mt-3 text-sm text-ink-muted" data-testid="attention-empty">
      {{ t("editorDashboard.attention.empty") }}
    </p>
    <ul v-else class="mt-3 divide-y divide-line" data-testid="attention-list">
      <li v-for="row in rows" :key="`${row.item.entity_type}:${row.item.id}`" class="py-3 first:pt-0 last:pb-0">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="text-xs text-ink-faint">
              {{ t(`editorDashboard.entities.${row.item.entity_type}`) }}
              <span
                class="ms-1 rounded-sm px-1.5 py-0.5 text-xs font-medium"
                :class="badgeClass(row.item.severity)"
                >{{ t(`editorDashboard.attention.kinds.${row.item.kind}`) }}</span
              >
            </p>
            <p class="mt-0.5 truncate font-medium text-ink">
              <LocalizedText :text="row.item.title" />
              <template v-if="!row.item.title.ar && !row.item.title.en">{{
                t("editorDashboard.untitled")
              }}</template>
            </p>
            <p v-if="row.item.reason_note" class="mt-0.5 text-xs text-ink-muted">
              {{ row.item.reason_note }}
            </p>
          </div>
          <div class="flex shrink-0 items-center gap-3">
            <span
              v-if="row.item.completeness_pct !== null"
              class="text-xs tabular-nums text-ink-muted"
              >{{ t("editorDashboard.complete", { pct: row.item.completeness_pct }) }}</span
            >
            <RouterLink
              :to="row.to"
              class="rounded-md border border-line px-2.5 py-1 text-xs font-medium text-accent hover:border-accent"
              >{{ t(`editorDashboard.attention.actions.${row.action}`) }}</RouterLink
            >
          </div>
        </div>
      </li>
    </ul>
  </section>
</template>
