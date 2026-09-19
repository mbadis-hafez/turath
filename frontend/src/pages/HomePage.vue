<script setup lang="ts">
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";

import HomeSectionHeader from "@/components/home/HomeSectionHeader.vue";
import RecentItem from "@/components/home/RecentItem.vue";
import ThemeCard from "@/components/home/ThemeCard.vue";
import {
  archiveFeature,
  cityEntries,
  homeStats,
  homeThemes,
  lastUpdate,
  popularSearches,
  recentItems,
  spotlightArtists,
  type LocalizedText,
} from "@/data/homeDemo";
import { formatNumber } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const { t, locale } = useI18n();

const appLocale = computed(() => locale.value as AppLocale);
const text = (value: LocalizedText) =>
  appLocale.value === "ar" ? value.ar : value.en;

const query = ref("");

const stats = computed(() =>
  homeStats.map((stat) => ({
    label: t(`home.stats.${stat.key}`),
    value: formatNumber(stat.value, appLocale.value),
  })),
);

const updateLabel = computed(() =>
  t("home.lastUpdate", {
    batch: lastUpdate.batch,
    date: text(lastUpdate.date),
  }),
);

const itemsLabel = (count: number) =>
  t("home.artistsSection.items", {
    count: formatNumber(count, appLocale.value),
  });

const applySuggestion = (suggestion: LocalizedText) => {
  query.value = text(suggestion);
};
</script>

<template>
  <div class="mx-auto max-w-[90rem]">
    <!-- Hero + search -->
    <section class="px-6 pt-14 sm:px-12">
      <div class="flex flex-wrap items-start gap-12">
        <div class="min-w-[320px] flex-1">
          <p
            class="text-[11px] tracking-widest text-ink-faint uppercase font-latin"
          >
            {{ t("home.eyebrow") }}
          </p>
          <h1
            class="mt-4 max-w-[20ch] text-5xl leading-[1.3] font-bold text-balance text-ink font-display sm:text-6xl"
          >
            {{ t("home.heroTitle") }}
          </h1>
          <p
            class="mt-5 max-w-[56ch] text-[17px] leading-[1.95] text-pretty text-ink-muted"
          >
            {{ t("home.heroBody") }}
          </p>
        </div>

        <aside
          class="w-full shrink-0 ps-9 sm:w-[330px] sm:border-s sm:border-line"
        >
          <dl>
            <div
              v-for="stat in stats"
              :key="stat.label"
              class="flex items-baseline justify-between gap-3 border-b border-line py-3.5"
            >
              <dt class="text-[14.5px] text-ink">{{ stat.label }}</dt>
              <dd
                class="text-[26px] font-bold text-ink tabular-nums font-display"
              >
                {{ stat.value }}
              </dd>
            </div>
          </dl>
          <p class="mt-3.5 text-[11px] leading-relaxed text-ink-faint font-latin">
            {{ updateLabel }}
          </p>
        </aside>
      </div>

      <form
        role="search"
        class="mt-11 flex items-center gap-4 border-b-2 border-ink pb-4"
        @submit.prevent
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
          class="size-[22px] shrink-0 text-ink"
        >
          <circle cx="11" cy="11" r="7" />
          <path d="m20 20-3.6-3.6" />
        </svg>
        <input
          v-model="query"
          type="text"
          class="min-w-0 flex-1 bg-transparent text-[25px] text-ink placeholder:text-ink-faint font-display focus:outline-none"
          :placeholder="t('home.searchPlaceholder')"
          :aria-label="t('home.searchLabel')"
        />
        <button
          type="submit"
          class="bg-ink px-4.5 py-2 text-[12px] text-paper transition-colors hover:bg-accent font-latin"
        >
          {{ t("home.searchButton") }}
        </button>
      </form>

      <div
        class="mt-4 flex flex-wrap items-center gap-2.5 text-[12px] text-ink-faint font-latin"
      >
        <span>{{ t("home.popular") }}</span>
        <button
          v-for="suggestion in popularSearches"
          :key="suggestion.en"
          type="button"
          class="border border-line px-3 py-1.5 text-[13px] text-ink transition-colors hover:border-ink"
          @click="applySuggestion(suggestion)"
        >
          {{ text(suggestion) }}
        </button>
      </div>
    </section>

    <!-- Thematic axes -->
    <section id="themes" class="scroll-mt-24 px-6 pt-16 sm:px-12">
      <HomeSectionHeader
        :title="t('home.themes.title')"
        :link-label="t('home.themes.viewAll')"
      />
      <div
        class="grid grid-cols-1 gap-7 min-[480px]:grid-cols-2 xl:grid-cols-4"
      >
        <ThemeCard
          v-for="theme in homeThemes"
          :key="theme.title.en"
          :theme="theme"
          :locale="appLocale"
        />
      </div>
    </section>

    <!-- From the archive -->
    <section id="archive" class="scroll-mt-24 px-6 pt-16 sm:px-12">
      <HomeSectionHeader
        :title="t('home.archive.title')"
        :link-label="t('home.archive.browse')"
      />
      <div class="flex flex-wrap items-start gap-11">
        <article class="min-w-[320px] flex-[1.4]">
          <div class="aspect-[16/10] bg-neutral-soft" aria-hidden="true"></div>
          <div
            class="mt-4 flex flex-wrap items-center gap-2 text-[10.5px] font-latin"
          >
            <span
              class="bg-ink px-2 py-0.75 font-bold text-paper uppercase"
              >{{ archiveFeature.kindLabel.en }} ·
              {{ text(archiveFeature.kindLabel) }}</span
            >
            <span class="text-ink-faint tabular-nums">{{
              text(archiveFeature.meta)
            }}</span>
          </div>
          <h3
            class="mt-3 max-w-[26ch] text-3xl leading-[1.45] font-bold text-balance text-ink font-display"
          >
            {{ text(archiveFeature.title) }}
          </h3>
          <p
            class="mt-3 max-w-[58ch] text-[15.5px] leading-[1.95] text-pretty text-ink-muted"
          >
            {{ text(archiveFeature.body) }}
          </p>
          <p class="mt-3 text-[11.5px] text-ink-faint font-latin">
            {{ text(archiveFeature.collection) }} · {{ archiveFeature.recordId }}
          </p>
        </article>

        <div class="min-w-[300px] flex-1">
          <RecentItem
            v-for="item in recentItems"
            :key="item.title.en"
            :item="item"
            :locale="appLocale"
          />
        </div>
      </div>
    </section>

    <!-- Artists -->
    <section id="artists" class="scroll-mt-24 px-6 pt-16 sm:px-12">
      <HomeSectionHeader
        :title="t('home.artistsSection.title')"
        :link-label="t('home.artistsSection.viewAll')"
      />
      <div
        class="grid grid-cols-2 gap-5 min-[480px]:grid-cols-3 xl:grid-cols-6"
      >
        <a
          v-for="artist in spotlightArtists"
          :key="artist.name.en"
          href="#"
          class="group"
          @click.prevent
        >
          <div
            class="aspect-[3/4] bg-neutral-soft transition-colors group-hover:bg-sand"
            aria-hidden="true"
          ></div>
          <p
            class="mt-2.75 text-[19px] leading-[1.4] font-bold text-ink font-display"
          >
            {{ text(artist.name) }}
          </p>
          <p class="mt-1 text-[11px] leading-relaxed text-ink-faint font-latin">
            {{ artist.name.en }}
          </p>
          <p
            class="mt-1.5 text-[11px] text-ink-muted tabular-nums font-latin"
          >
            {{ itemsLabel(artist.items) }}
          </p>
        </a>
      </div>
    </section>

    <!-- Cities + contribute -->
    <section class="px-6 pt-16 sm:px-12">
      <div class="flex flex-wrap items-start gap-11">
        <div class="min-w-[300px] flex-1">
          <h2
            class="border-b-2 border-ink pb-2.5 text-[11px] tracking-widest text-ink-faint uppercase font-latin"
          >
            {{ t("home.cities.title") }}
          </h2>
          <a
            v-for="city in cityEntries"
            :key="city.name.en"
            href="#"
            class="flex items-center justify-between gap-4 border-b border-line py-3.25 transition-colors hover:text-accent"
            @click.prevent
          >
            <span class="text-[15px] text-ink">{{ text(city.name) }}</span>
            <span
              class="shrink-0 text-[12px] whitespace-nowrap text-ink-muted tabular-nums font-latin"
              >{{ itemsLabel(city.items) }}</span
            >
          </a>
        </div>

        <div class="min-w-[300px] flex-1 border border-ink p-8">
          <h2
            class="text-[28px] leading-[1.45] font-bold text-balance text-ink font-display"
          >
            {{ t("home.contribute.title") }}
          </h2>
          <p
            class="mt-3 text-[15.5px] leading-[1.95] text-pretty text-ink-muted"
          >
            {{ t("home.contribute.body") }}
          </p>
          <div class="mt-6 flex flex-wrap gap-3">
            <a
              href="#"
              class="bg-ink px-5.5 py-3 text-[14.5px] font-semibold text-paper transition-colors hover:bg-accent"
              @click.prevent
              >{{ t("home.contribute.submit") }}</a
            >
            <a
              href="#"
              class="border border-ink px-5.5 py-3 text-[14.5px] font-semibold text-ink transition-colors hover:bg-ink hover:text-paper"
              @click.prevent
              >{{ t("home.contribute.methodology") }}</a
            >
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
