<script setup lang="ts">
import { onBeforeUnmount, ref } from "vue";
import { useI18n } from "vue-i18n";
import type { RouteLocationRaw } from "vue-router";

import { searchEntityMatch } from "@/api/archive";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import type { EntityMatch, EntityMatchCandidate } from "@/types/ocr";

const props = defineProps<{
  match: EntityMatch;
  canReview: boolean;
  archiveItemId: number;
}>();
const emit = defineEmits<{
  confirm: [matchId: number, candidate: EntityMatchCandidate];
  /** A record the reviewer found by searching. */
  link: [matchId: number, entityId: number | string];
  decide: [matchId: number, decision: "no-match" | "reset"];
}>();
const { t, te, locale } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

/** Searching for the record when the candidates don't include it. Finding one proves nothing: the reviewer links it. */
const searching = ref(false);
const query = ref("");
const results = ref<{ id: number | string; label: { ar: string; en: string }; detail: string | null }[] | null>(null);
const searchError = ref<string | null>(null);
let controller: AbortController | null = null;
async function runSearch(): Promise<void> {
  controller?.abort();
  const q = query.value.trim();
  if (q.length < 2) return;
  const self = new AbortController();
  controller = self;
  searchError.value = null;
  try {
    const response = await searchEntityMatch(props.archiveItemId, props.match.id, q, self.signal);
    if (controller === self) results.value = response.data;
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    searchError.value = err instanceof Error ? err.message : t("errors.generic");
  }
}
function openSearch(): void {
  searching.value = true;
  query.value = props.match.source_text;
  results.value = null;
}
onBeforeUnmount(() => controller?.abort());

/** A new record is made on its own create page, through its own review — never from here. */
const CREATE: Partial<Record<EntityMatch["entity_type"], { route: string; permission: string }>> = {
  artist: { route: "admin.artists.new", permission: "artists.manage" },
  artwork: { route: "admin.artworks.new", permission: "artworks.manage" },
  event: { route: "admin.events.new", permission: "events.manage" },
};
function createLink(): RouteLocationRaw | null {
  const create = CREATE[props.match.entity_type];
  return create && auth.can(create.permission) ? localePath(create.route, {}, { prefill: props.match.source_text }) : null;
}

const ROUTE_FOR_TYPE: Partial<Record<EntityMatch["entity_type"], string>> = {
  artist: "admin.artists.show",
  artwork: "admin.artworks.show",
  event: "admin.events.edit",
};
function recordLink(type: EntityMatch["entity_type"], id: number | string | null): RouteLocationRaw | null {
  const name = ROUTE_FOR_TYPE[type];
  return name && id !== null ? localePath(name, { id: Number(id) }) : null;
}
const labelOf = (label: { ar: string; en: string } | null): string => (label === null ? t("archive.ocr.match.missing") : locale.value === "ar" ? label.ar || label.en : label.en || label.ar);
const basisLabel = (basis: string): string => (te(`archive.ocr.match.basis.${basis}`) ? t(`archive.ocr.match.basis.${basis}`) : basis);
const STRENGTH_CLASS: Record<EntityMatchCandidate["strength"], string> = {
  high: "border-success text-success",
  medium: "border-warn text-warn",
  low: "border-line text-ink-muted",
};
</script>

<template>
  <div class="mt-2 rounded-md bg-neutral-soft p-2.5 text-xs" data-testid="entity-match">
    <p class="font-medium text-ink">{{ t(`archive.ocr.match.title.${match.entity_type}`) }}</p>

    <template v-if="match.status === 'confirmed' && match.confirmed">
      <p class="mt-1 text-ink" data-testid="entity-match-confirmed">
        {{ t("archive.ocr.match.confirmed") }}
        <RouterLink v-if="recordLink(match.entity_type, match.confirmed.id)" :to="recordLink(match.entity_type, match.confirmed.id)!" class="font-medium underline">
          {{ labelOf(match.confirmed.label) }}
        </RouterLink>
        <span v-else class="font-medium">{{ labelOf(match.confirmed.label) }}</span>
      </p>
    </template>
    <p v-else-if="match.status === 'no_match'" class="mt-1 text-ink-muted" data-testid="entity-match-none">{{ t("archive.ocr.match.noneRight") }}</p>

    <template v-else>
      <p class="mt-0.5 text-ink-muted">{{ t("archive.ocr.match.scoreNote") }}</p>
      <p v-if="match.candidates.length === 0" class="mt-1 text-ink-muted" data-testid="entity-match-empty">{{ t("archive.ocr.match.noCandidates") }}</p>
      <ul v-else class="mt-1.5 space-y-1.5">
        <li v-for="c in match.candidates" :key="`${c.id ?? c.key}`" class="flex flex-wrap items-center gap-2" data-testid="entity-match-candidate">
          <RouterLink v-if="recordLink(match.entity_type, c.id)" :to="recordLink(match.entity_type, c.id)!" class="font-medium text-ink underline" dir="auto">{{ labelOf(c.label) }}</RouterLink>
          <span v-else class="font-medium text-ink" dir="auto">{{ labelOf(c.label) }}</span>
          <span v-if="c.detail" class="text-ink-muted">{{ c.detail }}</span>
          <span class="rounded-sm border px-1" :class="STRENGTH_CLASS[c.strength]" data-testid="entity-match-strength">
            {{ t(`archive.ocr.match.strength.${c.strength}`) }} · {{ Math.round(c.score * 100) }}%
          </span>
          <span class="text-ink-muted" data-testid="entity-match-basis">{{ c.basis.map(basisLabel).join("، ") }}</span>
          <button
            v-if="canReview && !c.missing"
            type="button"
            class="ms-auto rounded-sm border border-ink px-2 py-0.5"
            data-testid="entity-match-confirm"
            @click="emit('confirm', match.id, c)"
          >
            {{ t("archive.ocr.match.thisOne") }}
          </button>
        </li>
      </ul>
    </template>

    <div v-if="canReview" class="mt-2 flex flex-wrap gap-3">
      <button
        v-if="match.status !== 'confirmed' && match.entity_type !== 'place' && !searching"
        type="button"
        class="text-ink underline hover:text-accent"
        data-testid="entity-match-search-open"
        @click="openSearch"
      >
        {{ t("archive.ocr.match.findAnother") }}
      </button>
      <RouterLink
        v-if="match.status !== 'confirmed' && createLink()"
        :to="createLink()!"
        target="_blank"
        class="text-ink underline hover:text-accent"
        data-testid="entity-match-create"
      >
        {{ t(`archive.ocr.match.create.${match.entity_type}`) }}
      </RouterLink>
      <button
        v-if="match.status === 'pending'"
        type="button"
        class="text-ink-muted underline hover:text-ink"
        data-testid="entity-match-no-match"
        @click="emit('decide', match.id, 'no-match')"
      >
        {{ t("archive.ocr.match.noneOfThese") }}
      </button>
      <button v-else type="button" class="text-ink-muted underline hover:text-ink" data-testid="entity-match-reset" @click="emit('decide', match.id, 'reset')">
        {{ t("archive.ocr.match.undo") }}
      </button>
    </div>

    <div v-if="searching && match.status !== 'confirmed'" class="mt-2 rounded-md border border-line bg-surface p-2" data-testid="entity-match-search">
      <form class="flex items-center gap-2" @submit.prevent="runSearch">
        <input v-model="query" type="search" dir="auto" class="min-w-0 flex-1 rounded-md border border-line px-2 py-1 text-xs" :placeholder="t('archive.ocr.match.searchPlaceholder')" data-testid="entity-match-search-input" />
        <button type="submit" class="rounded-md border border-ink px-2 py-1 text-xs font-medium text-ink">{{ t("archive.ocr.match.search") }}</button>
        <button type="button" class="text-xs text-ink-muted hover:text-ink" @click="searching = false">{{ t("archive.ocr.cancel") }}</button>
      </form>
      <p v-if="searchError" class="mt-1 text-danger" role="alert">{{ searchError }}</p>
      <p v-else-if="results !== null && results.length === 0" class="mt-1 text-ink-muted" data-testid="entity-match-search-empty">{{ t("archive.ocr.match.searchEmpty") }}</p>
      <ul v-else-if="results" class="mt-1.5 space-y-1">
        <li v-for="r in results" :key="String(r.id)" class="flex flex-wrap items-center gap-2" data-testid="entity-match-search-result">
          <RouterLink v-if="recordLink(match.entity_type, r.id)" :to="recordLink(match.entity_type, r.id)!" target="_blank" class="font-medium text-ink underline" dir="auto">{{ labelOf(r.label) }}</RouterLink>
          <span v-else class="font-medium text-ink" dir="auto">{{ labelOf(r.label) }}</span>
          <span v-if="r.detail" class="text-ink-muted">{{ r.detail }}</span>
          <button type="button" class="ms-auto rounded-sm border border-ink px-2 py-0.5" data-testid="entity-match-link" @click="emit('link', match.id, r.id)">
            {{ t("archive.ocr.match.link") }}
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
