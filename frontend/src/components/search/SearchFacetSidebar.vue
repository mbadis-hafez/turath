<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { SECTION_KEYS, type SectionKey } from "@/composables/useSiteSearch";
import { formatNumber } from "@/utils/format";
import type { ArchiveFacets, ArchiveItemType } from "@/types/archive";
import type { AppLocale } from "@/i18n";

const props = defineProps<{
  types: SectionKey[];
  totals: Record<SectionKey, number>;
  itemTypes: ArchiveItemType[];
  archiveFacets: ArchiveFacets | null;
  verifiedOnly: boolean;
}>();

const emit = defineEmits<{
  "toggle-type": [value: SectionKey];
  "toggle-item-type": [value: ArchiveItemType];
  "update:verified-only": [value: boolean];
  clear: [];
}>();

const { t, locale } = useI18n();

const fmt = (value: number) => formatNumber(value, locale.value as AppLocale);

/** SectionKey "archive" maps to the i18n label "materials" (search.types.materials). */
const TYPE_LABEL_KEY: Record<SectionKey, string> = {
  artists: "artists",
  artworks: "artworks",
  archive: "materials",
  events: "events",
};

const typeOptions = computed(() =>
  SECTION_KEYS.map((key) => ({
    key,
    label: t(`search.types.${TYPE_LABEL_KEY[key]}`),
    count: props.totals[key],
    checked: props.types.includes(key),
  })),
);

/** No item-type facet until the archive section has actually returned one. */
const itemTypeOptions = computed(() => {
  const facet = props.archiveFacets?.item_type ?? [];
  const rows = [...facet];
  for (const selected of props.itemTypes) {
    if (!rows.some((r) => r.value === selected))
      rows.push({ value: selected, count: 0 });
  }
  return rows.map((r) => ({
    key: r.value,
    label: t(`archive.types.${r.value}`),
    count: r.count,
    checked: props.itemTypes.includes(r.value as ArchiveItemType),
  }));
});

const active = computed(
  () =>
    props.types.length < SECTION_KEYS.length ||
    props.itemTypes.length > 0 ||
    props.verifiedOnly,
);
</script>

<template>
  <aside
    class="hidden lg:block"
    :aria-label="t('search.filters.title')"
    data-testid="search-sidebar"
  >
    <div
      class="flex items-baseline justify-between gap-4 border-b-2 border-ink pb-3"
    >
      <h2 class="text-sm font-semibold text-ink">
        {{ t("search.filters.title") }}
      </h2>
      <button
        v-if="active"
        type="button"
        class="text-xs text-accent hover:text-accent-strong"
        data-testid="clear-filters"
        @click="emit('clear')"
      >
        {{ t("search.filters.clearAll") }}
      </button>
    </div>

    <fieldset class="border-b border-line py-5" data-testid="facet-type">
      <legend class="mb-3 text-sm font-semibold text-ink">
        {{ t("search.filters.type") }}
      </legend>
      <ul class="space-y-3">
        <li v-for="option in typeOptions" :key="option.key">
          <label
            class="flex cursor-pointer items-center gap-3 text-sm text-ink"
          >
            <input
              type="checkbox"
              class="size-4 shrink-0 accent-ink"
              :checked="option.checked"
              data-testid="facet-type-option"
              @change="emit('toggle-type', option.key)"
            />
            <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
            <span class="text-xs tabular-nums text-ink-muted">{{
              fmt(option.count)
            }}</span>
          </label>
        </li>
      </ul>
    </fieldset>

    <fieldset
      v-if="itemTypeOptions.length > 0"
      class="border-b border-line py-5"
      data-testid="facet-item-type"
    >
      <legend class="mb-3 text-sm font-semibold text-ink">
        {{ t("search.filters.materialType") }}
      </legend>
      <ul class="space-y-3">
        <li v-for="option in itemTypeOptions" :key="option.key">
          <label
            class="flex cursor-pointer items-center gap-3 text-sm text-ink"
            :class="option.count === 0 && !option.checked ? 'opacity-50' : ''"
          >
            <input
              type="checkbox"
              class="size-4 shrink-0 accent-ink"
              :checked="option.checked"
              data-testid="facet-item-type-option"
              @change="emit('toggle-item-type', option.key as ArchiveItemType)"
            />
            <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
            <span class="text-xs tabular-nums text-ink-muted">{{
              fmt(option.count)
            }}</span>
          </label>
        </li>
      </ul>
    </fieldset>

    <div class="flex items-center justify-between py-5">
      <span class="text-sm font-semibold text-ink">{{
        t("search.filters.verifiedOnly")
      }}</span>
      <button
        type="button"
        role="switch"
        :aria-checked="verifiedOnly"
        :aria-label="t('search.filters.verifiedOnly')"
        class="relative h-5 w-10 shrink-0 transition-colors"
        :class="verifiedOnly ? 'bg-ink' : 'bg-line'"
        data-testid="verified-only-toggle"
        @click="emit('update:verified-only', !verifiedOnly)"
      >
        <span
          class="absolute top-0.5 size-4 bg-paper transition-[inset-inline-start]"
          :class="verifiedOnly ? 'start-[22px]' : 'start-0.5'"
        ></span>
      </button>
    </div>
  </aside>
</template>
