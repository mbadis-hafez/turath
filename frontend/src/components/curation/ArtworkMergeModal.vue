<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { getArtworkCuration, listAdminArtworks, mergeArtworks } from "@/api/artworkCuration";
import { labelOf } from "@/components/curation/ArtworkPickers";
import { useLocalized } from "@/composables/useLocalized";
import type { AdminArtworkRow, ArtworkCuration } from "@/types/artworkCuration";

const emit = defineEmits<{ close: []; merged: [] }>();

const { t } = useI18n();
const { pick } = useLocalized();

type Side = "survivor" | "duplicate";
const FIELDS = ["title_ar", "title_en", "medium_ar", "medium_en", "category", "artist_id", "holder_id", "notes_en"] as const;
type Field = (typeof FIELDS)[number];

const chosen = ref<Record<Side, AdminArtworkRow | null>>({ survivor: null, duplicate: null });
const data = ref<Record<Side, ArtworkCuration | null>>({ survivor: null, duplicate: null });
const term = ref<Record<Side, string>>({ survivor: "", duplicate: "" });
const suggestions = ref<Record<Side, AdminArtworkRow[]>>({ survivor: [], duplicate: [] });
const resolution = ref<Record<string, Side>>({});
const loading = ref(false);
const submitting = ref(false);
const error = ref<string | null>(null);

async function search(side: Side): Promise<void> {
  const value = term.value[side].trim();
  suggestions.value[side] = value ? (await listAdminArtworks({ q: value })).data.slice(0, 6) : [];
}

function choose(side: Side, row: AdminArtworkRow): void {
  chosen.value[side] = row;
  suggestions.value[side] = [];
  term.value[side] = "";
}

function fieldValue(c: ArtworkCuration | null, f: Field): string {
  if (!c) return "";
  switch (f) {
    case "title_ar": return c.title.ar ?? "";
    case "title_en": return c.title.en ?? "";
    case "medium_ar": return c.medium.ar ?? "";
    case "medium_en": return c.medium.en ?? "";
    case "category": return c.category;
    case "artist_id": return c.artist ? labelOf(c.artist.name) : "";
    case "holder_id": return c.holder ? labelOf(c.holder.name) : "";
    default: return c.notes.en ?? "";
  }
}

watch(chosen, async (rows) => {
  data.value = { survivor: null, duplicate: null };
  resolution.value = {};
  if (!rows.survivor || !rows.duplicate || rows.survivor.id === rows.duplicate.id) return;
  loading.value = true;
  try {
    const [s, d] = await Promise.all([getArtworkCuration(rows.survivor.id), getArtworkCuration(rows.duplicate.id)]);
    data.value = { survivor: s.data, duplicate: d.data };
    // Keep the survivor, but take the duplicate's value where the survivor has none.
    for (const f of FIELDS) {
      if (!fieldValue(s.data, f) && fieldValue(d.data, f)) resolution.value[f] = "duplicate";
    }
  } finally {
    loading.value = false;
  }
}, { deep: true });

const same = computed(() => chosen.value.survivor !== null && chosen.value.survivor.id === chosen.value.duplicate?.id);
const ready = computed(() => data.value.survivor !== null && data.value.duplicate !== null && !same.value);
const differing = computed(() => FIELDS.filter((f) => fieldValue(data.value.survivor, f) !== fieldValue(data.value.duplicate, f)));

function payload(): Record<string, Side> {
  const out: Record<string, Side> = {};
  for (const f of differing.value) out[f] = resolution.value[f] ?? "survivor";
  return out;
}

async function submit(): Promise<void> {
  const { survivor, duplicate } = chosen.value;
  if (!survivor || !duplicate) return;
  submitting.value = true;
  error.value = null;
  try {
    await mergeArtworks({ survivor_id: survivor.id, duplicate_id: duplicate.id, field_resolution: payload() });
    emit("merged");
  } catch (err) {
    error.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    submitting.value = false;
  }
}

defineExpose({ payload });
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4" role="dialog" aria-modal="true" aria-labelledby="amerge-title" @keydown.esc="emit('close')">
    <form class="max-h-[90dvh] w-full max-w-2xl overflow-y-auto rounded-lg border border-line bg-surface p-6" @submit.prevent="submit">
      <h2 id="amerge-title" class="text-lg font-semibold text-ink">{{ t("curation.artworkMerge.title") }}</h2>
      <p class="mt-1 text-pretty text-sm text-ink-muted">{{ t("curation.artworkMerge.help") }}</p>

      <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div v-for="side in (['survivor', 'duplicate'] as const)" :key="side">
          <p class="text-xs font-medium text-ink-muted">{{ t(`curation.merge.${side}`) }}</p>
          <p v-if="chosen[side]" class="mt-1 text-sm font-medium text-ink" :data-testid="`picked-${side}`">
            {{ pick(chosen[side]!.title)?.text }}
            <span v-if="chosen[side]!.artist" class="text-ink-muted">· {{ pick(chosen[side]!.artist!.name)?.text }}</span>
          </p>
          <input v-model="term[side]" type="search" :placeholder="t('curation.artworkMerge.search')" class="mt-1 w-full rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none" @input="search(side)" />
          <ul v-if="suggestions[side].length" class="mt-1 rounded-md border border-line bg-surface py-1">
            <li v-for="a in suggestions[side]" :key="a.id">
              <button type="button" class="block w-full px-3 py-1.5 text-start text-sm text-ink hover:bg-neutral-soft" @click="choose(side, a)">
                {{ pick(a.title)?.text }} <span v-if="a.artist" class="text-ink-muted">· {{ pick(a.artist.name)?.text }}</span>
              </button>
            </li>
          </ul>
        </div>
      </div>

      <p v-if="same" class="mt-3 text-sm text-danger">{{ t("curation.merge.sameRecord") }}</p>
      <p v-if="loading" class="mt-3 text-sm text-ink-muted">{{ t("curation.merge.loading") }}</p>

      <fieldset v-if="ready && differing.length" class="mt-5 space-y-3">
        <legend class="text-sm font-medium text-ink">{{ t("curation.merge.fields") }}</legend>
        <div v-for="f in differing" :key="f" class="rounded-md border border-line p-3 text-sm">
          <p class="text-xs font-medium text-ink-muted">{{ f }}</p>
          <label class="mt-1 flex items-start gap-2">
            <input v-model="resolution[f]" type="radio" :name="`f-${f}`" value="survivor" :checked="(resolution[f] ?? 'survivor') === 'survivor'" />
            <span>{{ t("curation.merge.keep") }}: <bdi class="text-ink">{{ fieldValue(data.survivor, f) || "—" }}</bdi></span>
          </label>
          <label class="mt-1 flex items-start gap-2">
            <input v-model="resolution[f]" type="radio" :name="`f-${f}`" value="duplicate" />
            <span>{{ t("curation.merge.take") }}: <bdi class="text-ink">{{ fieldValue(data.duplicate, f) || "—" }}</bdi></span>
          </label>
        </div>
      </fieldset>

      <p v-if="error" class="mt-3 text-sm text-danger">{{ error }}</p>

      <div class="mt-6 flex justify-end gap-2">
        <button type="button" class="rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink hover:bg-neutral-soft" @click="emit('close')">{{ t("curation.merge.cancel") }}</button>
        <button type="submit" class="rounded-md bg-accent px-4 py-1.5 text-sm font-medium text-surface hover:bg-accent-strong disabled:cursor-not-allowed disabled:opacity-50" :disabled="!ready || submitting" data-testid="merge-submit">
          {{ submitting ? t("curation.merge.merging") : t("curation.merge.submit") }}
        </button>
      </div>
    </form>
  </div>
</template>
