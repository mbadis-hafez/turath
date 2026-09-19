<script setup lang="ts">
import { useI18n } from "vue-i18n";

import type { ArtistsSortOption } from "@/composables/useArtistsList";

const props = defineProps<{
  search: string;
  sort: ArtistsSortOption;
  verifiedOnly: boolean;
}>();

const emit = defineEmits<{
  "update:search": [value: string];
  "update:sort": [value: ArtistsSortOption];
  "update:verifiedOnly": [value: boolean];
}>();

const { t } = useI18n();

function onSortChange(event: Event): void {
  const value = (event.target as HTMLSelectElement).value;
  emit("update:sort", value === "recent" ? "recent" : "name");
}
</script>

<template>
  <form
    class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4 sm:flex-row sm:flex-wrap sm:items-end"
    role="search"
    @submit.prevent
  >
    <label class="flex min-w-48 flex-1 flex-col gap-1 text-sm">
      <span class="sr-only">{{ t("common.search") }}</span>
      <input
        type="search"
        class="rounded-md border border-line bg-paper px-3 py-1.5 text-ink"
        :placeholder="t('artists.searchPlaceholder')"
        :value="props.search"
        @input="
          emit('update:search', ($event.target as HTMLInputElement).value)
        "
      />
    </label>

    <label class="flex flex-col gap-1 text-sm">
      <span class="font-medium text-ink-muted">{{ t("artists.sortBy") }}</span>
      <select
        class="rounded-md border border-line bg-paper px-2 py-1.5 text-ink"
        :value="props.sort"
        @change="onSortChange"
      >
        <option value="name">{{ t("artists.sort.name") }}</option>
        <option value="recent">{{ t("artists.sort.recent") }}</option>
      </select>
    </label>

    <label
      class="flex items-center gap-2 text-sm text-ink"
    >
      <input
        type="checkbox"
        class="size-4 rounded border-line accent-teal-700"
        :checked="props.verifiedOnly"
        @change="
          emit('update:verifiedOnly', ($event.target as HTMLInputElement).checked)
        "
      />
      <span>{{ t("artists.verifiedOnly") }}</span>
    </label>
  </form>
</template>
