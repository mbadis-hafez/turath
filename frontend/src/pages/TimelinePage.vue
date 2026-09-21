<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute } from "vue-router";

import { listThemes } from "@/api/artistCuration";
import { getTimeline } from "@/api/events";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Spinner from "@/components/common/Spinner.vue";
import Tabs from "@/components/common/Tabs.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import type { Theme } from "@/types/artistCuration";
import type { TimelineBucket, TimelineEntry, TimelineKind } from "@/types/event";

const { t } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();
const route = useRoute();

const includeLifespans = ref(false);
const includeArtworks = ref(false);
const initialTheme = Number(route.query.theme);
const themeId = ref<number | null>(Number.isInteger(initialTheme) && initialTheme > 0 ? initialTheme : null);
const themes = ref<Theme[]>([]);
void listThemes().then((r) => (themes.value = r.data)).catch(() => (themes.value = []));

const kinds = computed<TimelineKind[]>(() => [
  "event",
  ...(includeLifespans.value ? (["artist_lifespan"] as const) : []),
  ...(includeArtworks.value ? (["artwork"] as const) : []),
]);

const buckets = ref<TimelineBucket[]>([]);
const loading = ref(false);
const error = ref<unknown>(null);
let controller: AbortController | null = null;

async function load(): Promise<void> {
  controller?.abort();
  const self = new AbortController();
  controller = self;
  loading.value = true;
  error.value = null;
  try {
    const response = await getTimeline({ type: kinds.value, theme_id: themeId.value ? [themeId.value] : [] }, self.signal);
    if (controller === self) buckets.value = response.data;
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    if (controller === self) { buckets.value = []; error.value = err; }
  } finally {
    if (controller === self) loading.value = false;
  }
}
watch([kinds, themeId], () => void load(), { immediate: true });
watch(() => route.query.theme, (value) => {
  const parsed = Number(value);
  themeId.value = Number.isInteger(parsed) && parsed > 0 ? parsed : null;
});
onBeforeUnmount(() => controller?.abort());

const decadeOf = (year: number): number => Math.floor(year / 10) * 10;
const decades = computed(() => [...new Set(buckets.value.map((b) => decadeOf(b.year)))].sort((a, b) => a - b));
const active = ref("all");
watch(decades, (list) => { if (active.value !== "all" && !list.includes(Number(active.value))) active.value = "all"; });
const tabs = computed(() => [{ key: "all", label: t("events.timeline.all") }, ...decades.value.map((d) => ({ key: String(d), label: `${d}s` }))]);
const visible = computed(() => (active.value === "all" ? buckets.value : buckets.value.filter((b) => decadeOf(b.year) === Number(active.value))));

const MARK: Record<TimelineKind, string> = {
  event: "size-3 rounded-none bg-ink",
  artist_lifespan: "size-3 rounded-full border-2 border-accent bg-surface",
  artwork: "size-3 rotate-45 bg-info",
};

function link(e: TimelineEntry) {
  if (e.kind === "event") return localePath("events.show", { id: e.id });
  if (e.kind === "artist_lifespan") return localePath("artists.show", { slug: e.slug ?? "" });
  return localePath("artworks.show", { id: e.id });
}
const label = (e: TimelineEntry) => (e.kind === "artist_lifespan" ? e.name : e.title) ?? { ar: null, en: null };
const field = "rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <section>
    <div class="border-b-2 border-ink pb-6">
      <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("events.timeline.title") }}</h1>
      <p class="mt-1 text-sm text-ink-muted">{{ t("events.timeline.subtitle") }}</p>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-4">
      <label class="flex cursor-pointer items-center gap-2 text-sm text-ink"><input v-model="includeLifespans" type="checkbox" class="size-4 accent-ink" data-testid="include-lifespans" />{{ t("events.timeline.includeLifespans") }}</label>
      <label class="flex cursor-pointer items-center gap-2 text-sm text-ink"><input v-model="includeArtworks" type="checkbox" class="size-4 accent-ink" data-testid="include-artworks" />{{ t("events.timeline.includeArtworks") }}</label>
      <select v-model="themeId" :class="field" :aria-label="t('events.edit.themes')" data-testid="theme-filter">
        <option :value="null">{{ t("events.timeline.allThemes") }}</option>
        <option v-for="th in themes" :key="th.id" :value="th.id">{{ pick(th.label)?.text }}</option>
      </select>
      <ul class="ms-auto flex flex-wrap items-center gap-4 text-xs text-ink-muted" aria-label="Legend">
        <li v-for="k in kinds" :key="k" class="flex items-center gap-2"><span :class="MARK[k]" aria-hidden="true" />{{ t(`events.timeline.kinds.${k}`) }}</li>
      </ul>
    </div>

    <div class="mt-6">
      <ErrorState v-if="error" :error="error" @retry="load" />
      <Spinner v-else-if="loading && buckets.length === 0" class="mx-auto my-12 block" />
      <EmptyState v-else-if="buckets.length === 0" :title="t('events.timeline.empty')" :description="t('events.timeline.emptyHelp')" />
      <Tabs v-else v-model:active-key="active" :tabs="tabs">
        <template #[active]>
          <ol class="mt-4 border-s-2 border-ink ps-6" data-testid="timeline">
            <li v-for="b in visible" :key="b.year" class="pb-8" data-testid="year-bucket">
              <h2 class="-ms-[2.1rem] mb-3 inline-block bg-paper px-2 text-lg font-semibold tabular-nums text-ink">{{ b.year }}</h2>
              <ul class="space-y-3">
                <li v-for="e in b.entries" :key="`${e.kind}-${e.id}`" class="flex items-start gap-3" :data-kind="e.kind" data-testid="timeline-entry">
                  <span class="mt-1.5 shrink-0" :class="MARK[e.kind]" aria-hidden="true" />
                  <div>
                    <RouterLink :to="link(e)" class="text-base font-semibold text-ink hover:underline"><LocalizedText :text="label(e)" /></RouterLink>
                    <p class="text-xs text-ink-muted">
                      {{ t(`events.timeline.kinds.${e.kind}`) }}<template v-if="e.event_type"> · {{ t(`events.types.${e.event_type}`) }}</template>
                      <template v-if="e.kind === 'artist_lifespan' && e.year_to"> · {{ e.year_from }}–{{ e.year_to }}</template>
                      <template v-if="e.kind === 'artwork' && e.artist"> · <LocalizedText :text="e.artist" /></template>
                    </p>
                  </div>
                </li>
              </ul>
            </li>
          </ol>
        </template>
      </Tabs>
    </div>
  </section>
</template>
