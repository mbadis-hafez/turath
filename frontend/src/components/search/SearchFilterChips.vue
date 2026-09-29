<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { ArchiveItemType } from "@/types/archive";

const props = defineProps<{
  itemTypes: ArchiveItemType[];
  verifiedOnly: boolean;
}>();

const emit = defineEmits<{
  "remove-item-type": [value: ArchiveItemType];
  "remove-verified-only": [];
}>();

const { t } = useI18n();

interface Chip {
  key: string;
  label: string;
  remove: () => void;
}

const chips = computed<Chip[]>(() => [
  ...props.itemTypes.map((value) => ({
    key: `item-type-${value}`,
    label: t(`archive.types.${value}`),
    remove: () => emit("remove-item-type", value),
  })),
  ...(props.verifiedOnly
    ? [
        {
          key: "verified",
          label: t("search.filters.verifiedOnly"),
          remove: () => emit("remove-verified-only"),
        },
      ]
    : []),
]);
</script>

<template>
  <ul
    v-if="chips.length > 0"
    class="flex flex-wrap items-center gap-2"
    data-testid="filter-chips"
  >
    <li v-for="chip in chips" :key="chip.key">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 border border-line px-2.5 py-1 text-xs text-ink hover:border-ink"
        data-testid="filter-chip"
        @click="chip.remove()"
      >
        {{ chip.label }}
        <span aria-hidden="true">×</span>
        <span class="sr-only">{{
          t("search.filters.remove", { label: chip.label })
        }}</span>
      </button>
    </li>
  </ul>
</template>
