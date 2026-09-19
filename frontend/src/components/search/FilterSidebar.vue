<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useI18n } from "vue-i18n";

import { formatNumber } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const { t, locale } = useI18n();

interface FilterItem {
  label: string;
  count: number;
  checked: boolean;
}

interface FilterGroup {
  label: string;
  items: FilterItem[];
}

const groups = reactive<FilterGroup[]>([
  {
    label: t("search.filters.type"),
    items: [
      { label: t("search.types.artists"), count: 12, checked: true },
      { label: t("search.types.artworks"), count: 48, checked: true },
      { label: t("search.types.materials"), count: 73, checked: true },
      { label: t("search.types.events"), count: 14, checked: false },
    ],
  },
  {
    label: t("search.filters.medium"),
    items: [
      { label: t("search.mediums.oil"), count: 19, checked: false },
      { label: t("search.mediums.watercolor"), count: 11, checked: false },
      { label: t("search.mediums.mixed"), count: 6, checked: false },
    ],
  },
  {
    label: t("search.filters.city"),
    items: [
      { label: t("search.cities.jeddah"), count: 63, checked: false },
      { label: t("search.cities.riyadh"), count: 58, checked: false },
      { label: t("search.cities.dammam"), count: 21, checked: false },
    ],
  },
  {
    label: t("search.filters.holding"),
    items: [
      { label: t("search.holdings.moc"), count: 34, checked: false },
      { label: t("search.holdings.athr"), count: 17, checked: false },
      { label: t("search.holdings.private"), count: 42, checked: false },
    ],
  },
  {
    label: t("search.filters.materialType"),
    items: [
      { label: t("search.materialTypes.articles"), count: 86, checked: false },
      { label: t("search.materialTypes.photos"), count: 49, checked: true },
      { label: t("search.materialTypes.catalogs"), count: 23, checked: false },
      { label: t("search.materialTypes.av"), count: 16, checked: false },
    ],
  },
]);

const verifiedOnly = ref(true);

const fmt = (value: number) => formatNumber(value, locale.value as AppLocale);

const hasChecked = computed(() =>
  groups.some((group) => group.items.some((item) => item.checked)),
);

function clearAll(): void {
  groups.forEach((group) =>
    group.items.forEach((item) => (item.checked = false)),
  );
  verifiedOnly.value = false;
}
</script>

<template>
  <aside class="hidden lg:block">
    <div class="flex items-baseline justify-between gap-4">
      <h2 class="text-lg font-semibold text-ink">
        {{ t("search.filters.title") }}
      </h2>
      <button
        v-if="hasChecked || verifiedOnly"
        type="button"
        class="text-sm text-accent hover:text-accent-strong"
        @click="clearAll"
      >
        {{ t("search.filters.clearAll") }}
      </button>
    </div>

    <button
      type="button"
      class="mt-4 flex w-full items-center justify-between border-b-2 border-ink pb-2 text-sm font-medium text-ink"
    >
      {{ t("search.filters.bestMatch") }}
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        class="size-4"
      >
        <path d="m6 9 6 6 6-6" />
      </svg>
    </button>

    <section
      v-for="group in groups"
      :key="group.label"
      class="mt-6 border-t border-line pt-4"
    >
      <h3 class="mb-3 text-sm font-semibold text-ink">{{ group.label }}</h3>
      <ul class="space-y-2">
        <li v-for="item in group.items" :key="item.label">
          <label
            class="flex cursor-pointer items-center gap-2.5 text-sm text-ink"
          >
            <input
              v-model="item.checked"
              type="checkbox"
              class="size-4 shrink-0 accent-ink"
            />
            <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
            <span class="text-ink-muted tabular-nums">{{
              fmt(item.count)
            }}</span>
          </label>
        </li>
      </ul>
    </section>

    <section class="mt-6 border-t border-line pt-4">
      <h3 class="text-sm font-semibold text-ink">
        {{ t("search.filters.period") }}
      </h3>
      <div class="relative mt-5" aria-hidden="true">
        <div class="h-0.5 bg-line"></div>
        <div
          class="absolute inset-y-0 start-[12%] end-[8%] bg-ink"
          style="height: 2px"
        ></div>
        <span
          class="absolute top-1/2 size-3 -translate-y-1/2 bg-ink"
          style="inset-inline-start: 12%"
        ></span>
        <span
          class="absolute top-1/2 size-3 -translate-y-1/2 bg-ink"
          style="inset-inline-end: 8%"
        ></span>
      </div>
      <div
        class="mt-2 flex justify-between text-xs text-ink-muted tabular-nums"
      >
        <span>{{ t("search.filters.periodStart") }}</span>
        <span>{{ t("search.filters.periodEnd") }}</span>
      </div>
    </section>

    <div
      class="mt-6 flex items-center justify-between border-t border-line pt-4"
    >
      <span class="text-sm font-semibold text-ink">{{
        t("search.filters.verifiedOnly")
      }}</span>
      <button
        type="button"
        role="switch"
        :aria-checked="verifiedOnly"
        :aria-label="t('search.filters.verifiedOnly')"
        class="relative h-5 w-10 transition-colors"
        :class="verifiedOnly ? 'bg-ink' : 'bg-line'"
        @click="verifiedOnly = !verifiedOnly"
      >
        <span
          class="absolute top-0.5 size-4 bg-paper transition-[inset-inline-start]"
          :class="verifiedOnly ? 'start-0.5' : 'start-[22px]'"
        ></span>
      </button>
    </div>
  </aside>
</template>
