<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";

import { getHomeOverview } from "@/api/home";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Spinner from "@/components/common/Spinner.vue";
import HomeSectionHeader from "@/components/home/HomeSectionHeader.vue";
import RecentItem from "@/components/home/RecentItem.vue";
import ThemeCard from "@/components/home/ThemeCard.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import type { Bilingual } from "@/types/artist";
import type { HomeOverview } from "@/types/home";
import { formatDateTime, formatNumber } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const { t, locale } = useI18n();
const { localePath, pushLocalePath } = useLocalePath();
const { pick } = useLocalized();

const appLocale = computed(() => locale.value as AppLocale);
const text = (value: Bilingual) => pick(value)?.text ?? "";

const query = ref("");
const overview = ref<HomeOverview | null>(null);
const loading = ref(true);
const error = ref<unknown>(null);
let controller: AbortController | null = null;

const stats = computed(() =>
  overview.value === null ? [] : Object.entries(overview.value.stats).map(([key, value]) => ({
    label: t(`home.stats.${key}`),
    value: formatNumber(value, appLocale.value),
  })),
);

const updateLabel = computed(() =>
  overview.value?.updated_at
    ? t("home.lastUpdatedAt", { date: formatDateTime(overview.value.updated_at, appLocale.value) })
    : "",
);

const itemsLabel = (count: number) =>
  t("home.artistsSection.items", {
    count: formatNumber(count, appLocale.value),
  });

const archiveFeature = computed(() => overview.value?.archive_feature ?? null);

async function load(): Promise<void> {
  controller?.abort();
  const current = new AbortController();
  controller = current;
  loading.value = true;
  error.value = null;

  try {
    const response = await getHomeOverview(current.signal);
    if (controller === current) overview.value = response.data;
  } catch (caught) {
    if (caught instanceof DOMException && caught.name === "AbortError") return;
    if (controller === current) error.value = caught;
  } finally {
    if (controller === current) loading.value = false;
  }
}

/** The box says "artist, artwork, article, photograph", so it searches all of them, not just the archive. */
function search(term = query.value): void {
  const cleaned = term.trim();
  void pushLocalePath(cleaned === "" ? "archive.records" : "search", {}, cleaned === "" ? {} : { q: cleaned });
}

function applySuggestion(suggestion: Bilingual): void {
  query.value = text(suggestion);
  search(query.value);
}

onMounted(() => void load());
onBeforeUnmount(() => controller?.abort());
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
          <dl v-if="overview">
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
          <div v-else-if="loading" class="py-8 text-center"><Spinner /></div>
          <p class="mt-3.5 text-[11px] leading-relaxed text-ink-faint font-latin">
            {{ updateLabel }}
          </p>
        </aside>
      </div>

      <form
        role="search"
        class="mt-11 flex items-center gap-4 border-b-2 border-ink pb-4"
        @submit.prevent="search()"
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
          v-for="suggestion in overview?.popular_searches ?? []"
          :key="suggestion.term.en ?? suggestion.term.ar ?? ''"
          type="button"
          class="border border-line px-3 py-1.5 text-[13px] text-ink transition-colors hover:border-ink"
          @click="applySuggestion(suggestion.term)"
        >
          {{ text(suggestion.term) }}
        </button>
      </div>
    </section>

    <!-- Thematic axes -->
    <section id="themes" class="scroll-mt-24 px-6 pt-16 sm:px-12">
      <HomeSectionHeader
        :title="t('home.themes.title')"
        :link-label="t('home.themes.viewAll')"
        :to="localePath('timeline')"
      />
      <div
        v-if="overview && overview.themes.length > 0"
        class="grid grid-cols-1 gap-7 min-[480px]:grid-cols-2 xl:grid-cols-4"
      >
        <ThemeCard
          v-for="theme in overview.themes"
          :key="theme.id"
          :theme="theme"
          :locale="appLocale"
        />
      </div>
      <EmptyState v-else-if="!loading" :title="t('home.emptyThemes')" />
    </section>

    <!-- From the archive -->
    <section id="archive" class="scroll-mt-24 px-6 pt-16 sm:px-12">
      <HomeSectionHeader
        :title="t('home.archive.title')"
        :link-label="t('home.archive.browse')"
        :to="localePath('archive.records')"
      />
      <div v-if="archiveFeature" class="flex flex-wrap items-start gap-11">
        <article class="min-w-[320px] flex-[1.4]">
          <div class="aspect-[16/10] bg-neutral-soft" aria-hidden="true"></div>
          <div
            class="mt-4 flex flex-wrap items-center gap-2 text-[10.5px] font-latin"
          >
            <span
              class="bg-ink px-2 py-0.75 font-bold text-paper uppercase"
              >{{ t(`archive.type.${archiveFeature.item_type}`) }}</span
            >
            <span class="text-ink-faint tabular-nums">{{ archiveFeature.content?.display ?? "—" }}</span>
          </div>
          <h3
            class="mt-3 max-w-[26ch] text-3xl leading-[1.45] font-bold text-balance text-ink font-display"
          >
            {{ text(archiveFeature.title) || t('archive.restricted') }}
          </h3>
          <p
            class="mt-3 max-w-[58ch] text-[15.5px] leading-[1.95] text-pretty text-ink-muted"
          >
            {{ archiveFeature.description ? text(archiveFeature.description) : t('archive.restrictedHelp') }}
          </p>
          <p v-if="archiveFeature.creator_name" class="mt-3 text-[11.5px] text-ink-faint font-latin">{{ archiveFeature.creator_name }}</p>
        </article>

        <div class="min-w-[300px] flex-1">
          <RecentItem
            v-for="item in overview?.recent_archive_items ?? []"
            :key="item.id"
            :item="item"
            :locale="appLocale"
          />
        </div>
      </div>
      <EmptyState v-else-if="!loading" :title="t('home.emptyArchive')" />
    </section>

    <!-- Artists -->
    <section id="artists" class="scroll-mt-24 px-6 pt-16 sm:px-12">
      <HomeSectionHeader
        :title="t('home.artistsSection.title')"
        :link-label="t('home.artistsSection.viewAll')"
        :to="localePath('artists.index')"
      />
      <div
        v-if="overview && overview.artists.length > 0"
        class="grid grid-cols-2 gap-5 min-[480px]:grid-cols-3 xl:grid-cols-6"
      >
        <RouterLink
          v-for="artist in overview.artists"
          :key="artist.id"
          :to="localePath('artists.show', { slug: artist.slug })"
          class="group"
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
            {{ itemsLabel(artist.materials_count) }}
          </p>
        </RouterLink>
      </div>
      <EmptyState v-else-if="!loading" :title="t('home.emptyArtists')" />
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
          <RouterLink
            v-for="place in overview?.places ?? []"
            :key="`${place.name.ar}-${place.name.en}`"
            :to="localePath('archive.records', {}, { place: text(place.name) })"
            class="flex items-center justify-between gap-4 border-b border-line py-3.25 transition-colors hover:text-accent"
          >
            <span class="text-[15px] text-ink">{{ text(place.name) }}</span>
            <span
              class="shrink-0 text-[12px] whitespace-nowrap text-ink-muted tabular-nums font-latin"
              >{{ itemsLabel(place.materials_count) }}</span
            >
          </RouterLink>
          <EmptyState v-if="overview && overview.places.length === 0" :title="t('home.emptyPlaces')" />
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
            <RouterLink
              :to="localePath('submit')"
              class="bg-ink px-5.5 py-3 text-[14.5px] font-semibold text-paper transition-colors hover:bg-accent"
              >{{ t("home.contribute.submit") }}</RouterLink
            >
            <RouterLink
              :to="localePath('methodology')"
              class="border border-ink px-5.5 py-3 text-[14.5px] font-semibold text-ink transition-colors hover:bg-ink hover:text-paper"
              >{{ t("home.contribute.methodology") }}</RouterLink
            >
          </div>
        </div>
      </div>
    </section>
  </div>
  <div v-if="error" class="mx-auto mt-10 max-w-[90rem] px-6 sm:px-12"><ErrorState :error="error" @retry="load" /></div>
</template>
