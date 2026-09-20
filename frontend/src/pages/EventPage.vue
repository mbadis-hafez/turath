<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import { getEvent } from "@/api/events";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import PartialDateDisplay from "@/components/common/PartialDateDisplay.vue";
import { useDocumentTitle } from "@/composables/useDocumentTitle";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import NotFoundPage from "@/pages/NotFoundPage.vue";
import { ApiError } from "@/types/api";
import { PARTICIPANT_ROLES, type EventDetail, type EventParticipantRow } from "@/types/event";

const route = useRoute();
const { t } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();

const id = computed(() => Number.parseInt(String(route.params.id ?? ""), 10));
const event = ref<EventDetail | null>(null);
const loading = ref(false);
const error = ref<unknown>(null);
let controller: AbortController | null = null;

async function load(): Promise<void> {
  if (!Number.isFinite(id.value) || id.value <= 0) return;
  controller?.abort();
  const self = new AbortController();
  controller = self;
  loading.value = true;
  error.value = null;
  try {
    const response = await getEvent(id.value, self.signal);
    if (controller === self) event.value = response.data;
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    if (controller === self) { event.value = null; error.value = err; }
  } finally {
    if (controller === self) loading.value = false;
  }
}
watch(id, () => void load(), { immediate: true });
onBeforeUnmount(() => controller?.abort());

const notFound = computed(() => error.value instanceof ApiError && error.value.kind === "not_found");
useDocumentTitle(computed(() => (event.value ? pick(event.value.title)?.text ?? "" : "")));

/** Awardees of an award event are the headline; everyone else is grouped by role. */
const winners = computed(() => (event.value?.event_type === "award" ? event.value.participants.filter((p) => p.role === "awardee") : []));
const groups = computed(() => {
  const rows = event.value?.participants ?? [];
  const skipAwardees = winners.value.length > 0;
  return PARTICIPANT_ROLES
    .filter((r) => !(skipAwardees && r === "awardee"))
    .map((role) => ({ role, rows: rows.filter((p) => p.role === role) }))
    .filter((g) => g.rows.length > 0);
});

function rowLink(p: EventParticipantRow) {
  return p.kind === "artist" ? localePath("artists.show", { slug: p.entity.slug ?? "" }) : localePath("artworks.show", { id: p.entity.id });
}
const rowName = (p: EventParticipantRow) => (p.kind === "artist" ? p.entity.name : p.entity.title) ?? { ar: null, en: null };
</script>

<template>
  <NotFoundPage v-if="notFound" />
  <ErrorState v-else-if="error" :error="error" @retry="load" />
  <div v-else-if="loading && !event" class="animate-pulse" aria-hidden="true"><div class="h-8 w-1/2 rounded bg-neutral-soft" /><div class="mt-3 h-4 w-1/3 rounded bg-neutral-soft" /></div>

  <article v-else-if="event">
    <nav class="text-xs text-ink-muted"><RouterLink :to="localePath('timeline')" class="hover:text-ink">{{ t("events.public.backToTimeline") }}</RouterLink></nav>
    <header class="mt-3 border-b-2 border-ink pb-6">
      <span class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink-muted">{{ t(`events.types.${event.event_type}`) }}</span>
      <h1 class="mt-2 text-balance text-3xl font-semibold tracking-tight text-ink"><LocalizedText :text="event.title" /></h1>
      <dl class="mt-3 flex flex-wrap gap-x-8 gap-y-1 text-sm text-ink-muted">
        <div v-if="event.start"><dt class="sr-only">{{ t("events.public.when") }}</dt><dd><PartialDateDisplay :date="event.start" /><template v-if="event.end"> – <PartialDateDisplay :date="event.end" /></template></dd></div>
        <div v-if="event.venue_name || event.city"><dt class="sr-only">{{ t("events.public.where") }}</dt><dd>{{ [event.venue_name, event.city].filter(Boolean).join("، ") }}</dd></div>
        <div v-if="event.holder"><dt class="font-medium">{{ t("events.public.host") }}</dt><dd><LocalizedText :text="event.holder.name" /></dd></div>
      </dl>
      <ul v-if="event.themes.length" class="mt-3 flex flex-wrap gap-1"><li v-for="th in event.themes" :key="th.id" class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs text-ink-muted">{{ pick(th.label)?.text }}</li></ul>
    </header>

    <p v-if="event.description.ar || event.description.en" class="mt-6 max-w-3xl leading-relaxed text-ink"><LocalizedText :text="event.description" /></p>

    <section v-if="winners.length" class="mt-8" data-testid="winners">
      <h2 class="text-lg font-semibold text-ink">{{ t("events.public.winners") }}</h2>
      <ul class="mt-3 grid gap-3 sm:grid-cols-2">
        <li v-for="p in winners" :key="p.id" class="rounded-lg border border-accent bg-accent-soft p-4">
          <RouterLink :to="rowLink(p)" class="text-base font-semibold text-ink hover:underline"><LocalizedText :text="rowName(p)" /></RouterLink>
          <p v-if="p.note" class="mt-1 text-sm text-accent-strong">{{ p.note }}</p>
        </li>
      </ul>
    </section>

    <section v-for="g in groups" :key="g.role" class="mt-8" data-testid="participant-group">
      <h2 class="text-lg font-semibold text-ink">{{ t(`events.roles.${g.role}`) }}</h2>
      <ul class="mt-2 divide-y divide-line border-y border-line">
        <li v-for="p in g.rows" :key="p.id" class="py-3 text-sm"><RouterLink :to="rowLink(p)" class="font-medium text-ink hover:underline"><LocalizedText :text="rowName(p)" /></RouterLink><span v-if="p.note" class="text-ink-muted"> — {{ p.note }}</span></li>
      </ul>
    </section>

    <section v-if="event.archive_items.length" class="mt-8" data-testid="documents">
      <h2 class="text-lg font-semibold text-ink">{{ t("events.public.documents") }}</h2>
      <ul class="mt-2 divide-y divide-line border-y border-line"><li v-for="a in event.archive_items" :key="a.id" class="py-3 text-sm text-ink"><span class="me-2 rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs text-ink-muted">{{ t(`archive.types.${a.item_type}`) }}</span><LocalizedText :text="a.title" /></li></ul>
    </section>
  </article>
</template>
