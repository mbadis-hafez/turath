<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { listArtists } from "@/api/artists";
import { useLocalized } from "@/composables/useLocalized";
import type { ArtistListItem } from "@/types/artist";
import type { ArtworkCategory } from "@/types/artwork";

const props = defineProps<{
  category: "" | ArtworkCategory;
  artist: number | null;
  yearFrom: number | null;
  yearTo: number | null;
}>();

const emit = defineEmits<{
  category: [category: "" | ArtworkCategory];
  artist: [artist: number | null];
  yearFrom: [year: number | null];
  yearTo: [year: number | null];
  clear: [];
}>();

const { t } = useI18n();
const { pick } = useLocalized();

const CATEGORIES: ArtworkCategory[] = [
  "painting", "drawing", "printmaking", "sculpture", "mixed_media", "paper_work", "photography", "installation", "other",
];

function toggleCategory(c: ArtworkCategory): void {
  emit("category", props.category === c ? "" : c);
}

/* --- artist typeahead ---------------------------------------------------- */
const TYPEAHEAD_DEBOUNCE_MS = 300;
const artistInput = ref("");
const suggestions = ref<ArtistListItem[]>([]);
const typeaheadOpen = ref(false);
const typeaheadLoading = ref(false);
const selectedArtist = ref<ArtistListItem | null>(null);

let typeaheadTimer: ReturnType<typeof setTimeout> | null = null;
let typeaheadController: AbortController | null = null;

function suggestionLabel(artist: ArtistListItem): string {
  return pick(artist.name)?.text ?? `#${artist.id}`;
}

async function fetchSuggestions(term: string): Promise<void> {
  typeaheadController?.abort();
  const self = new AbortController();
  typeaheadController = self;
  typeaheadLoading.value = true;
  try {
    const response = await listArtists({ q: term, per_page: 10 }, self.signal);
    if (typeaheadController !== self) return;
    suggestions.value = response.data;
    typeaheadOpen.value = true;
  } catch {
    if (typeaheadController === self) suggestions.value = [];
  } finally {
    if (typeaheadController === self) typeaheadLoading.value = false;
  }
}

function onArtistInput(term: string): void {
  if (typeaheadTimer !== null) clearTimeout(typeaheadTimer);
  typeaheadTimer = setTimeout(() => {
    typeaheadTimer = null;
    void fetchSuggestions(term);
  }, TYPEAHEAD_DEBOUNCE_MS);
}

function selectArtist(artist: ArtistListItem): void {
  selectedArtist.value = artist;
  artistInput.value = "";
  suggestions.value = [];
  typeaheadOpen.value = false;
  emit("artist", artist.id);
}

function clearArtist(): void {
  selectedArtist.value = null;
  artistInput.value = "";
  suggestions.value = [];
  typeaheadOpen.value = false;
  emit("artist", null);
}

async function resolveArtist(id: number): Promise<void> {
  try {
    const response = await listArtists({ per_page: 100 });
    const found = response.data.find((artist) => artist.id === id);
    if (found && props.artist === id) selectedArtist.value = found;
  } catch {
    // keep the generic label
  }
}

watch(
  () => props.artist,
  (id, previous) => {
    if (id === null) {
      clearArtist();
    } else if (id !== selectedArtist.value?.id) {
      selectedArtist.value = null;
      if (id !== previous) void resolveArtist(id);
    }
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  typeaheadController?.abort();
  if (typeaheadTimer !== null) clearTimeout(typeaheadTimer);
});

/* --- period ---------------------------------------------------------- */
function toYear(raw: string): number | null {
  const parsed = Number.parseInt(raw, 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}
const yearFromInput = computed({
  get: () => (props.yearFrom === null ? "" : String(props.yearFrom)),
  set: (raw: string) => emit("yearFrom", toYear(raw)),
});
const yearToInput = computed({
  get: () => (props.yearTo === null ? "" : String(props.yearTo)),
  set: (raw: string) => emit("yearTo", toYear(raw)),
});

const active = computed(() => props.category !== "" || props.artist !== null || props.yearFrom !== null || props.yearTo !== null);
</script>

<template>
  <aside data-testid="artwork-filters" :aria-label="t('artworks.browse.filter')">
    <div class="flex items-baseline justify-between border-b-2 border-ink pb-3">
      <h2 class="text-xs font-semibold text-ink-muted">{{ t("artworks.browse.filter") }}</h2>
      <button v-if="active" type="button" class="text-xs text-accent-strong hover:underline" data-testid="clear-filters" @click="emit('clear')">{{ t("artworks.browse.clear") }}</button>
    </div>

    <fieldset class="border-b border-line py-5" data-testid="facet-category">
      <legend class="mb-3 text-base font-semibold text-ink">{{ t("artworks.browse.groups.category") }}</legend>
      <ul class="space-y-3">
        <li v-for="c in CATEGORIES" :key="c">
          <label class="flex cursor-pointer items-center gap-3 text-ink">
            <input type="checkbox" class="size-4 accent-ink" :checked="category === c" data-testid="facet-option" @change="toggleCategory(c)" />
            {{ t(`artworks.category.${c}`) }}
          </label>
        </li>
      </ul>
    </fieldset>

    <fieldset class="border-b border-line py-5">
      <legend class="mb-3 text-base font-semibold text-ink">{{ t("artworks.filterByArtist") }}</legend>
      <div v-if="artist !== null" class="flex items-center gap-2">
        <span class="inline-flex items-center gap-2 bg-neutral-soft px-3 py-1.5 text-sm text-ink">
          {{ selectedArtist ? suggestionLabel(selectedArtist) : `#${artist}` }}
          <button type="button" class="text-ink-muted hover:text-ink" :aria-label="t('artworks.filterByArtist')" @click="clearArtist">
            <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8" /></svg>
          </button>
        </span>
      </div>
      <div v-else class="relative">
        <input
          v-model="artistInput"
          type="search"
          :placeholder="t('artworks.filterByArtist')"
          class="w-full border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-ink focus:outline-none"
          @input="onArtistInput(($event.target as HTMLInputElement).value)"
        />
        <ul v-if="typeaheadOpen && suggestions.length > 0" class="absolute z-10 mt-1 max-h-60 w-full overflow-auto border border-line bg-surface py-1 shadow-md" role="listbox">
          <li v-for="s in suggestions" :key="s.id">
            <button type="button" class="block w-full px-3 py-1.5 text-start text-sm text-ink hover:bg-neutral-soft" role="option" :aria-selected="false" @click="selectArtist(s)">
              {{ suggestionLabel(s) }}
            </button>
          </li>
        </ul>
        <p v-else-if="typeaheadLoading" class="absolute z-10 mt-1 w-full border border-line bg-surface px-3 py-1.5 text-sm text-ink-muted shadow-md">{{ t("common.loading") }}</p>
      </div>
    </fieldset>

    <fieldset class="py-5">
      <legend class="mb-4 text-base font-semibold text-ink">{{ t("artworks.browse.groups.period") }}</legend>
      <div class="flex items-end gap-3">
        <label class="block w-24">
          <span class="mb-1 block text-xs text-ink-muted">{{ t("artworks.yearFrom") }}</span>
          <input v-model="yearFromInput" type="number" min="1" inputmode="numeric" class="w-full border border-line bg-surface px-2 py-1.5 text-sm text-ink focus:border-ink focus:outline-none" />
        </label>
        <label class="block w-24">
          <span class="mb-1 block text-xs text-ink-muted">{{ t("artworks.yearTo") }}</span>
          <input v-model="yearToInput" type="number" min="1" inputmode="numeric" class="w-full border border-line bg-surface px-2 py-1.5 text-sm text-ink focus:border-ink focus:outline-none" />
        </label>
      </div>
    </fieldset>
  </aside>
</template>
