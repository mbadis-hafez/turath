<script setup lang="ts">
import {
  SelectContent,
  SelectItem,
  SelectItemText,
  SelectPortal,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectViewport,
} from "reka-ui";
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { useLocalized } from "@/composables/useLocalized";
import type { ArtistFacets } from "@/types/artist";
import type { ArtistsSortOption } from "@/composables/useArtistsList";
import { ARCHIVE_ITEM_TYPES } from "@/types/archive";

const props = defineProps<{
  search: string;
  sort: ArtistsSortOption;
  city: string;
  themeId: number | null;
  itemType: string;
  facets: ArtistFacets | null;
}>();

const emit = defineEmits<{
  "update:search": [value: string];
  "update:sort": [value: ArtistsSortOption];
  "update:city": [value: string];
  "update:themeId": [value: number | null];
  "update:itemType": [value: string];
}>();

const { t } = useI18n();
const { pick } = useLocalized();

const buttonBase =
  "flex items-center gap-2 border px-3.5 py-2.5 text-[13.5px] text-ink transition-colors focus:outline-none";
const selectTrigger =
  "data-[state=open]:border-ink hover:border-ink border-line bg-paper justify-between min-w-[10rem]";

const ALL_VALUE = "__all__";

interface SelectOption<T> {
  value: T;
  label: string;
  count: number;
}

const allOption = {
  value: ALL_VALUE,
  label: t("artists.filter.all"),
  count: 0,
};

const cityOptions = computed<SelectOption<string>[]>(() => [
  allOption,
  ...(props.facets?.city ?? []).map((f) => ({
    value: f.value,
    label: f.value,
    count: f.count,
  })),
]);

const themeOptions = computed<SelectOption<string>[]>(() => [
  allOption,
  ...(props.facets?.theme_id ?? []).map((f) => ({
    value: String(f.value),
    label: pick(f.label)?.text ?? String(f.value),
    count: f.count,
  })),
]);

const typeOptions = computed<SelectOption<string>[]>(() => [
  allOption,
  ...ARCHIVE_ITEM_TYPES.map((type) => {
    const facet = props.facets?.item_type.find((f) => f.value === type);
    return {
      value: type,
      label: t(`archive.types.${type}`),
      count: facet?.count ?? 0,
    };
  }).filter((o) => o.count > 0 || props.itemType === o.value),
]);

function cityDisplay(value: string): string {
  if (value === "") return t("artists.filter.city");
  return value;
}

function themeDisplay(value: number | null): string {
  if (value === 0 || value === null) return t("artists.filter.theme");
  const option = themeOptions.value.find(
    (o) => Number.parseInt(o.value, 10) === value,
  );
  return option?.label ?? t("artists.filter.theme");
}

function typeDisplay(value: string): string {
  if (value === "") return t("artists.filter.type");
  return t(`archive.types.${value}`);
}

function onCityChange(value: string): void {
  emit("update:city", value === ALL_VALUE ? "" : value);
}

function onThemeChange(value: string): void {
  emit(
    "update:themeId",
    value === ALL_VALUE ? null : Number.parseInt(value, 10),
  );
}

function onTypeChange(value: string): void {
  emit("update:itemType", value === ALL_VALUE ? "" : value);
}

const sortButton = (active: boolean) =>
  `${buttonBase} ${active ? "border-ink bg-ink text-paper" : "border-line bg-paper hover:border-ink"}`;
</script>

<template>
  <div
    class="flex flex-wrap items-center gap-3.5 border-b border-line py-4"
    role="search"
  >
    <div
      class="flex min-w-[280px] flex-1 items-center gap-3 border border-ink bg-paper px-3.5 py-2.5"
    >
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        class="size-[17px] shrink-0 text-ink-faint"
      >
        <circle cx="11" cy="11" r="7" />
        <path d="m20 20-3.6-3.6" />
      </svg>
      <input
        :value="search"
        type="search"
        class="min-w-0 flex-1 bg-transparent text-[14px] text-ink placeholder:text-ink-faint focus:outline-none"
        :placeholder="t('artists.searchPlaceholder')"
        :aria-label="t('artists.searchPlaceholder')"
        data-testid="artist-search"
        @input="
          $emit('update:search', ($event.target as HTMLInputElement).value)
        "
      />
    </div>

    <SelectRoot
      :model-value="city === '' ? ALL_VALUE : city"
      @update:model-value="onCityChange"
    >
      <SelectTrigger
        :class="`${buttonBase} ${selectTrigger}`"
        :aria-label="t('artists.filter.city')"
        data-testid="city-filter"
      >
        <SelectValue :placeholder="t('artists.filter.city')">
          {{ cityDisplay(city) }}
        </SelectValue>
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
          class="size-[11px] text-ink-faint"
        >
          <path d="m6 9 6 6 6-6" />
        </svg>
      </SelectTrigger>
      <SelectPortal to="body">
        <SelectContent
          class="z-50 max-h-80 min-w-[12rem] overflow-hidden border border-ink bg-paper shadow-sm"
          position="popper"
          :side-offset="4"
          align="start"
        >
          <SelectViewport class="max-h-80 overflow-y-auto">
            <SelectItem
              v-for="option in cityOptions"
              :key="`city-${option.value}`"
              :value="option.value"
              class="flex cursor-pointer items-center justify-between gap-4 px-3.5 py-2 text-[13.5px] text-ink outline-none hover:bg-neutral-soft data-[state=checked]:bg-neutral-soft"
            >
              <SelectItemText>{{ option.label }}</SelectItemText>
              <span class="text-xs tabular-nums text-ink-faint">{{
                option.count
              }}</span>
            </SelectItem>
          </SelectViewport>
        </SelectContent>
      </SelectPortal>
    </SelectRoot>

    <SelectRoot
      :model-value="themeId === null ? ALL_VALUE : String(themeId)"
      @update:model-value="onThemeChange"
    >
      <SelectTrigger
        :class="`${buttonBase} ${selectTrigger}`"
        :aria-label="t('artists.filter.theme')"
        data-testid="theme-filter"
      >
        <SelectValue :placeholder="t('artists.filter.theme')">
          {{ themeDisplay(themeId) }}
        </SelectValue>
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
          class="size-[11px] text-ink-faint"
        >
          <path d="m6 9 6 6 6-6" />
        </svg>
      </SelectTrigger>
      <SelectPortal to="body">
        <SelectContent
          class="z-50 max-h-80 min-w-[12rem] overflow-hidden border border-ink bg-paper shadow-sm"
          position="popper"
          :side-offset="4"
          align="start"
        >
          <SelectViewport class="max-h-80 overflow-y-auto">
            <SelectItem
              v-for="option in themeOptions"
              :key="`theme-${option.value}`"
              :value="option.value"
              class="flex cursor-pointer items-center justify-between gap-4 px-3.5 py-2 text-[13.5px] text-ink outline-none hover:bg-neutral-soft data-[state=checked]:bg-neutral-soft"
            >
              <SelectItemText>{{ option.label }}</SelectItemText>
              <span class="text-xs tabular-nums text-ink-faint">{{
                option.count
              }}</span>
            </SelectItem>
          </SelectViewport>
        </SelectContent>
      </SelectPortal>
    </SelectRoot>

    <SelectRoot
      :model-value="itemType === '' ? ALL_VALUE : itemType"
      @update:model-value="onTypeChange"
    >
      <SelectTrigger
        :class="`${buttonBase} ${selectTrigger}`"
        :aria-label="t('artists.filter.type')"
        data-testid="type-filter"
      >
        <SelectValue :placeholder="t('artists.filter.type')">
          {{ typeDisplay(itemType) }}
        </SelectValue>
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
          class="size-[11px] text-ink-faint"
        >
          <path d="m6 9 6 6 6-6" />
        </svg>
      </SelectTrigger>
      <SelectPortal to="body">
        <SelectContent
          class="z-50 max-h-80 min-w-[12rem] overflow-hidden border border-ink bg-paper shadow-sm"
          position="popper"
          :side-offset="4"
          align="start"
        >
          <SelectViewport class="max-h-80 overflow-y-auto">
            <SelectItem
              v-for="option in typeOptions"
              :key="`type-${option.value}`"
              :value="option.value"
              class="flex cursor-pointer items-center justify-between gap-4 px-3.5 py-2 text-[13.5px] text-ink outline-none hover:bg-neutral-soft data-[state=checked]:bg-neutral-soft"
            >
              <SelectItemText>{{ option.label }}</SelectItemText>
              <span class="text-xs tabular-nums text-ink-faint">{{
                option.count
              }}</span>
            </SelectItem>
          </SelectViewport>
        </SelectContent>
      </SelectPortal>
    </SelectRoot>

    <div class="flex" role="group" :aria-label="t('artists.sortBy')">
      <button
        type="button"
        :class="`${sortButton(sort === 'alphabetical')} border-e-0 rounded-s-sm`"
        :aria-pressed="sort === 'alphabetical'"
        data-testid="sort-alphabetical"
        @click="$emit('update:sort', 'alphabetical')"
      >
        {{ t("artists.sort.alphabetical") }}
      </button>
      <button
        type="button"
        :class="`${sortButton(sort === 'materials')} rounded-e-sm`"
        :aria-pressed="sort === 'materials'"
        data-testid="sort-materials"
        @click="$emit('update:sort', 'materials')"
      >
        {{ t("artists.sort.materials") }}
      </button>
    </div>
  </div>
</template>
