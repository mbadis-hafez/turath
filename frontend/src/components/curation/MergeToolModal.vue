<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { getArtistCuration, listAdminArtists, mergeArtists } from "@/api/artistCuration";
import { useLocalized } from "@/composables/useLocalized";
import type { AdminArtistRow, ArtistCuration } from "@/types/artistCuration";

const emit = defineEmits<{
  close: [];
  merged: [];
}>();

const { t } = useI18n();
const { pick } = useLocalized();

/** Fields the bundle exposes for a per-field choice (ArtistMerger::MERGEABLE subset). */
const FIELDS = ["name_ar", "name_en", "bio_ar", "bio_en", "key_contact_name", "owner_type"] as const;
type Field = (typeof FIELDS)[number];

const survivor = ref<AdminArtistRow | null>(null);
const duplicate = ref<AdminArtistRow | null>(null);
const survivorData = ref<ArtistCuration | null>(null);
const duplicateData = ref<ArtistCuration | null>(null);
const resolution = ref<Record<string, "survivor" | "duplicate">>({});
const loading = ref(false);
const submitting = ref(false);
const error = ref<string | null>(null);

const term = ref<Record<"survivor" | "duplicate", string>>({ survivor: "", duplicate: "" });
const suggestions = ref<Record<"survivor" | "duplicate", AdminArtistRow[]>>({ survivor: [], duplicate: [] });

async function search(side: "survivor" | "duplicate"): Promise<void> {
  const value = term.value[side].trim();
  suggestions.value[side] = value ? (await listAdminArtists({ q: value })).data.slice(0, 6) : [];
}

function choose(side: "survivor" | "duplicate", artist: AdminArtistRow): void {
  (side === "survivor" ? survivor : duplicate).value = artist;
  suggestions.value[side] = [];
  term.value[side] = "";
}

function fieldValue(data: ArtistCuration | null, field: Field): string {
  if (!data) return "";
  switch (field) {
    case "name_ar": return data.name.ar ?? "";
    case "name_en": return data.name.en ?? "";
    case "bio_ar": return data.bio.ar ?? "";
    case "bio_en": return data.bio.en ?? "";
    case "key_contact_name": return data.contact.key_contact_name ?? "";
    default: return data.contact.owner_type ?? "";
  }
}

watch([survivor, duplicate], async () => {
  survivorData.value = duplicateData.value = null;
  resolution.value = {};
  if (!survivor.value || !duplicate.value || survivor.value.id === duplicate.value.id) return;
  loading.value = true;
  try {
    [survivorData.value, duplicateData.value] = (
      await Promise.all([getArtistCuration(survivor.value.id), getArtistCuration(duplicate.value.id)])
    ).map((r) => r.data) as [ArtistCuration, ArtistCuration];
    // Default per D94: keep the survivor, but take the duplicate's value where the survivor has none.
    for (const f of FIELDS) {
      if (!fieldValue(survivorData.value, f) && fieldValue(duplicateData.value, f)) resolution.value[f] = "duplicate";
    }
  } finally {
    loading.value = false;
  }
});

const same = computed(() => survivor.value !== null && survivor.value.id === duplicate.value?.id);
const ready = computed(() => survivorData.value !== null && duplicateData.value !== null && !same.value);
const differing = computed(() => FIELDS.filter((f) => fieldValue(survivorData.value, f) !== fieldValue(duplicateData.value, f)));

function payload(): Record<string, "survivor" | "duplicate"> {
  const out: Record<string, "survivor" | "duplicate"> = {};
  for (const f of differing.value) out[f] = resolution.value[f] ?? "survivor";
  return out;
}

async function submit(): Promise<void> {
  if (!survivor.value || !duplicate.value) return;
  submitting.value = true;
  error.value = null;
  try {
    await mergeArtists({ survivor_id: survivor.value.id, duplicate_id: duplicate.value.id, field_resolution: payload() });
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
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4" role="dialog" aria-modal="true" aria-labelledby="merge-title" @keydown.esc="emit('close')">
    <form class="max-h-[90dvh] w-full max-w-2xl overflow-y-auto rounded-lg border border-line bg-surface p-6" @submit.prevent="submit">
      <h2 id="merge-title" class="text-lg font-semibold text-ink">{{ t("curation.merge.title") }}</h2>
      <p class="mt-1 text-pretty text-sm text-ink-muted">{{ t("curation.merge.help") }}</p>

      <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div v-for="side in (['survivor', 'duplicate'] as const)" :key="side">
          <p class="text-xs font-medium text-ink-muted">{{ t(`curation.merge.${side}`) }}</p>
          <p v-if="(side === 'survivor' ? survivor : duplicate)" class="mt-1 text-sm font-medium text-ink" :data-testid="`picked-${side}`">
            {{ pick((side === "survivor" ? survivor : duplicate)!.name)?.text }}
            <span class="text-ink-muted">· {{ (side === "survivor" ? survivor : duplicate)!.legacy_code }}</span>
          </p>
          <input
            v-model="term[side]"
            type="search"
            :placeholder="t('curation.merge.search')"
            class="mt-1 w-full rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
            @input="search(side)"
          />
          <ul v-if="suggestions[side].length" class="mt-1 rounded-md border border-line bg-surface py-1">
            <li v-for="a in suggestions[side]" :key="a.id">
              <button type="button" class="block w-full px-3 py-1.5 text-start text-sm text-ink hover:bg-neutral-soft" @click="choose(side, a)">
                {{ pick(a.name)?.text }} <span class="text-ink-muted">· {{ a.legacy_code }}</span>
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
            <span>{{ t("curation.merge.keep") }}: <bdi class="text-ink">{{ fieldValue(survivorData, f) || "—" }}</bdi></span>
          </label>
          <label class="mt-1 flex items-start gap-2">
            <input v-model="resolution[f]" type="radio" :name="`f-${f}`" value="duplicate" />
            <span>{{ t("curation.merge.take") }}: <bdi class="text-ink">{{ fieldValue(duplicateData, f) || "—" }}</bdi></span>
          </label>
        </div>
      </fieldset>

      <p v-if="error" class="mt-3 text-sm text-danger">{{ error }}</p>

      <div class="mt-6 flex justify-end gap-2">
        <button type="button" class="rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink hover:bg-neutral-soft" @click="emit('close')">{{ t("curation.merge.cancel") }}</button>
        <button type="submit" class="rounded-md bg-accent px-4 py-1.5 text-sm font-medium text-surface hover:bg-accent-strong disabled:cursor-not-allowed disabled:opacity-50" :disabled="!ready || submitting">
          {{ submitting ? t("curation.merge.merging") : t("curation.merge.submit") }}
        </button>
      </div>
    </form>
  </div>
</template>
