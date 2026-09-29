<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { findNationality, flagEmoji, NATIONALITIES, type Nationality } from "@/data/nationalities";
import { isLocale, type AppLocale } from "@/i18n";

/**
 * Nationality picker: a searchable combobox whose options carry the official
 * bilingual demonym pair; choosing one fills both the Arabic and English
 * fields. Stored values that match nothing in the list (legacy free text,
 * historical or compound nationalities) fall back to an "Other" mode showing
 * the plain bilingual inputs untouched. Same idiom as EntityPicker.
 */
withDefaults(defineProps<{ inputClass?: string }>(), { inputClass: "" });

const ar = defineModel<string | null>("ar", { default: null });
const en = defineModel<string | null>("en", { default: null });

const { t, locale } = useI18n();
const appLocale = computed<AppLocale>(() => (isLocale(locale.value) ? locale.value : "ar"));

const labelFor = (n: Nationality): string => (appLocale.value === "ar" ? n.ar : n.en);
const secondaryFor = (n: Nationality): string => (appLocale.value === "ar" ? n.en : n.ar);

type Mode = "list" | "other";

function initialState(): { mode: Mode; picked: Nationality | null } {
  const match = findNationality(ar.value, en.value);
  if (match) return { mode: "list", picked: match };
  return ar.value?.trim() || en.value?.trim()
    ? { mode: "other", picked: null }
    : { mode: "list", picked: null };
}

const initial = initialState();
const mode = ref<Mode>(initial.mode);
const picked = ref<Nationality | null>(initial.picked);
const term = ref("");
const open = ref(false);

/** Re-sync when the parent swaps the pair wholesale (e.g. a loaded draft). */
watch([ar, en], () => {
  const state = initialState();
  // A cleared pair while the user is typing a custom value must not snap back.
  if (mode.value === "other" && state.mode === "other") return;
  mode.value = state.mode;
  picked.value = state.picked;
  if (state.picked) term.value = "";
});

/** Saudi first (the common case here), then alphabetical in the active locale. */
const sorted = computed(() =>
  [...NATIONALITIES].sort((a, b) => {
    if (a.code === "SA") return -1;
    if (b.code === "SA") return 1;
    return labelFor(a).localeCompare(labelFor(b), appLocale.value);
  }),
);

const filtered = computed(() => {
  const q = term.value.trim().toLowerCase();
  if (q === "") return sorted.value;
  return sorted.value.filter(
    (n) => n.en.toLowerCase().includes(q) || n.ar.includes(term.value.trim()),
  );
});

function choose(n: Nationality): void {
  picked.value = n;
  ar.value = n.ar;
  en.value = n.en;
  term.value = "";
  open.value = false;
}

function chooseOther(): void {
  mode.value = "other";
  picked.value = null;
  term.value = "";
  open.value = false;
}

function clearPicked(): void {
  picked.value = null;
  ar.value = null;
  en.value = null;
  term.value = "";
}

function backToList(): void {
  mode.value = "list";
  ar.value = null;
  en.value = null;
}

function closeOnEscape(e: KeyboardEvent): void {
  if (e.key === "Escape") {
    open.value = false;
    term.value = "";
  }
}
</script>

<template>
  <div>
    <template v-if="mode === 'list'">
      <label class="text-xs text-ink-muted" for="nationality-search">{{ t("curation.profileForm.nationality") }}</label>
      <p
        v-if="picked"
        class="mt-1 flex items-center justify-between gap-2 rounded-md border border-line bg-neutral-soft px-3 py-2 text-sm text-ink"
        data-testid="nationality-picked"
      >
        <span><span aria-hidden="true" class="me-1.5">{{ flagEmoji(picked.code) }}</span>{{ labelFor(picked) }}<span class="ms-1.5 text-xs text-ink-muted">{{ secondaryFor(picked) }}</span></span>
        <button type="button" class="text-xs text-ink-muted hover:text-danger" @click="clearPicked">{{ t("common.clear") }}</button>
      </p>
      <div v-else class="relative">
        <input
          id="nationality-search"
          v-model="term"
          type="search"
          role="combobox"
          :aria-expanded="open"
          aria-controls="nationality-options"
          :placeholder="t('curation.profileForm.nationalityPlaceholder')"
          :class="inputClass"
          class="mt-1"
          data-testid="nationality-search"
          autocomplete="off"
          @focus="open = true"
          @input="open = true"
          @keydown="closeOnEscape"
          @blur="open = false"
        />
        <ul
          v-if="open"
          id="nationality-options"
          role="listbox"
          class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border border-line bg-surface py-1 shadow-sm"
          data-testid="nationality-options"
        >
          <li v-for="n in filtered" :key="n.code" role="option" :aria-selected="false">
            <button
              type="button"
              class="block w-full px-3 py-1.5 text-start text-sm text-ink hover:bg-neutral-soft"
              @mousedown.prevent="choose(n)"
            >
              <span aria-hidden="true" class="me-1.5">{{ flagEmoji(n.code) }}</span>{{ labelFor(n) }}
              <span class="ms-1.5 text-xs text-ink-muted">{{ secondaryFor(n) }}</span>
            </button>
          </li>
          <li v-if="filtered.length === 0" class="px-3 py-1.5 text-xs text-ink-muted">
            {{ t("curation.profileForm.nationalityNoMatch") }}
          </li>
          <li class="border-t border-line" role="option" :aria-selected="false">
            <button
              type="button"
              class="block w-full px-3 py-1.5 text-start text-sm font-medium text-accent hover:bg-neutral-soft"
              data-testid="nationality-other"
              @mousedown.prevent="chooseOther"
            >
              {{ t("curation.profileForm.nationalityOther") }}
            </button>
          </li>
        </ul>
      </div>
    </template>
    <template v-else>
      <div class="flex items-center justify-between gap-2">
        <p class="text-xs text-ink-muted">{{ t("curation.profileForm.nationality") }}</p>
        <button
          type="button"
          class="text-xs font-medium text-accent hover:underline"
          data-testid="nationality-back-to-list"
          @click="backToList"
        >
          {{ t("curation.profileForm.nationalityBackToList") }}
        </button>
      </div>
      <div class="mt-1 grid gap-4 sm:grid-cols-2" data-testid="nationality-custom">
        <label class="text-xs text-ink-muted">
          {{ t("curation.profileForm.nationalityAr") }}
          <input v-model="ar" type="text" dir="rtl" :class="inputClass" />
        </label>
        <label class="text-xs text-ink-muted">
          {{ t("curation.profileForm.nationalityEn") }}
          <input v-model="en" type="text" dir="ltr" :class="inputClass" />
        </label>
      </div>
    </template>
  </div>
</template>
