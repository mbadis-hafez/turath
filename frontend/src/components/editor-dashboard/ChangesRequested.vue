<script setup lang="ts">
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import type { AttentionItem } from "@/types/editorDashboard";
import { RECORD_ROUTE_NAMES } from "@/utils/editorDashboard";

defineProps<{
  count: number;
  items: AttentionItem[];
}>();

const { t } = useI18n();
const { localePath } = useLocalePath();
</script>

<template>
  <section
    aria-labelledby="changes-requested-heading"
    class="rounded-lg border border-warn bg-warn-soft p-4"
    data-testid="changes-requested"
  >
    <h2 id="changes-requested-heading" class="border-b-2 border-warn pb-2 text-sm font-semibold text-ink">
      {{ t("editorDashboard.changesRequested.title") }}
    </h2>
    <p class="mt-2 text-sm font-medium text-warn">
      {{ t("editorDashboard.changesRequested.count", count) }}
    </p>
    <ul v-if="items.length > 0" class="mt-3 space-y-3">
      <li v-for="item in items.slice(0, 3)" :key="`${item.entity_type}:${item.id}`" class="text-sm">
        <p class="font-medium text-ink">
          <span class="me-1 text-xs text-ink-faint">{{ t(`editorDashboard.entities.${item.entity_type}`) }}</span>
          <LocalizedText :text="item.title" />
          <template v-if="!item.title.ar && !item.title.en">{{ t("editorDashboard.untitled") }}</template>
        </p>
        <p v-if="item.reason_note" class="text-xs text-ink-muted">{{ item.reason_note }}</p>
        <RouterLink
          :to="localePath(RECORD_ROUTE_NAMES[item.entity_type], { id: item.id })"
          class="mt-0.5 inline-block text-xs font-medium text-accent hover:underline"
          >{{ t("editorDashboard.changesRequested.view") }}</RouterLink
        >
      </li>
    </ul>
    <RouterLink
      :to="localePath('proposals', {}, { status: 'changes_requested', mine: '1' })"
      class="mt-3 inline-block rounded-md border border-warn px-3 py-1.5 text-xs font-semibold text-warn transition-colors hover:bg-warn hover:text-surface"
      >{{ t("editorDashboard.changesRequested.reviewAll") }}</RouterLink
    >
  </section>
</template>
