<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import RecordRow from "@/components/dashboard/RecordRow.vue";
import type { DashboardEntityType, DashboardRecord } from "@/types/completeness";

const props = defineProps<{
  entityType: DashboardEntityType;
  records: DashboardRecord[];
}>();

const emit = defineEmits<{
  resolve: [record: DashboardRecord];
}>();

const { t } = useI18n();

const items = computed(() =>
  props.records.filter((r) => r.entity_type === props.entityType),
);
</script>

<template>
  <section v-if="items.length > 0" :aria-labelledby="`group-${entityType}`">
    <h2 :id="`group-${entityType}`" class="mb-3 text-lg font-semibold text-ink">
      {{ t(`dashboard.entity.${entityType}`) }}
      <span class="ms-1 text-sm font-normal tabular-nums text-ink-muted">{{ items.length }}</span>
    </h2>
    <ul class="space-y-3">
      <RecordRow
        v-for="record in items"
        :key="`${record.entity_type}-${record.id}`"
        :record="record"
        @resolve="emit('resolve', $event)"
      />
    </ul>
  </section>
</template>
