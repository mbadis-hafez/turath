<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { listAdminArtworks } from "@/api/artworkCuration";
import { listThemes } from "@/api/artistCuration";
import { createEvent, getEvent, publishEvent, syncEventParticipants, syncEventThemes, updateEvent } from "@/api/events";
import DraftStatusBanner from "@/components/curation/DraftStatusBanner.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Spinner from "@/components/common/Spinner.vue";
import { labelOf, searchArtistOptions, searchHolderOptions } from "@/components/curation/ArtworkPickers";
import EntityPicker, { type PickerOption } from "@/components/curation/EntityPicker.vue";
import FuzzyDateField from "@/components/curation/FuzzyDateField.vue";
import ListEditor from "@/components/curation/ListEditor.vue";
import { useEventForm, newParticipant } from "@/composables/useEventForm";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useRecordDraft } from "@/composables/useRecordDraft";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type { Theme } from "@/types/artistCuration";
import type { EventDraftPayload } from "@/types/proposal";
import { EVENT_TYPES, PARTICIPANT_ROLES, type EventDetail, type EventStatus, type EventType, type ParticipantRole } from "@/types/event";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("events.manage"));
const id = computed(() => (route.params.id ? Number(route.params.id) : null));
const isNew = computed(() => id.value === null);

const { form, holder, participants, themeIds, start, load: loadForm, payload, participantsPayload, checklist: liveChecklist } = useEventForm();

// Editors (and admins, who also hold proposals.submit) work through the draft
// pipeline on existing events: saves upsert a draft proposal instead of
// touching the live record. New events are created directly.
const draftMode = computed(() => !isNew.value && auth.can("proposals.submit"));
const {
  status: draftStatus, reviewNote: draftReviewNote, blocker: draftBlocker, pendingSubmit,
  draftPayload, dirtySections, hasContent, init: initDraft, saveSection, submit: submitDraftForReview,
  resetLocally: resetDraftLocally,
} = useRecordDraft();
const draftLocked = computed(() => draftMode.value && (draftStatus.value === "pending" || draftBlocker.value !== null));

const event = ref<EventDetail | null>(null);
const themes = ref<Theme[]>([]);
const loading = ref(false);
const loadError = ref<unknown>(null);
let originalEnd = "";

/** Mirrors useFuzzyDate.load against the exposed state (the composable keeps the helpers private). */
function applyFuzzyToStart(content: { display?: string | null; year_from?: number | null; year_to?: number | null; certainty?: string | null } | null, note: string | null): void {
  const display = content?.display ?? "";
  Object.assign(start, { date: "", year: "", text: "", from: "", to: "", note: note ?? "" });
  start.certainty = content?.certainty === "range" ? "range" : "circa";
  if (/^\d{4}-\d{2}-\d{2}$/.test(display)) {
    start.mode = "exact";
    start.date = display;
  } else if (content?.certainty === "exact" && content.year_from !== null && content.year_from !== undefined && content.year_from === content.year_to && /^\d{4}$/.test(display)) {
    start.mode = "year";
    start.year = display;
  } else if (content?.year_from) {
    start.mode = "approx";
    start.text = display;
    start.from = String(content.year_from);
    start.to = content.year_to ? String(content.year_to) : "";
  } else {
    start.mode = "exact";
  }
}

/** Draft sections win over live data when initializing the form models. */
function applyDraftToForm(): void {
  const p = draftPayload.value as EventDraftPayload;
  if (p.fields) {
    const f = p.fields;
    if (f.event_type) form.type = f.event_type as EventType;
    if (f.title) {
      form.titleAr = f.title.ar ?? "";
      form.titleEn = f.title.en ?? "";
    }
    if (f.description) {
      form.descriptionAr = f.description.ar ?? "";
      form.descriptionEn = f.description.en ?? "";
    }
    if (f.venue_name !== undefined) form.venue = f.venue_name ?? "";
    if (f.city !== undefined) form.city = f.city ?? "";
    if (f.holder_id !== undefined) {
      holder.value = f.holder_id !== null && f.holder_id === (event.value?.holder?.id ?? null)
        ? { id: f.holder_id, label: labelOf(event.value!.holder!.name) }
        : f.holder_id === null ? null : { id: f.holder_id, label: `#${f.holder_id}` };
    }
    if ("start" in f) applyFuzzyToStart(f.start ?? null, f.date_note ?? null);
    if ("end" in f) form.endDate = /^\d{4}-\d{2}-\d{2}$/.test(f.end?.display ?? "") ? f.end!.display! : "";
  }
  if (p.participants) {
    participants.value = p.participants.map((pp) => {
      const live = event.value?.participants.find((lp) => lp.id === pp.id || (lp.kind === pp.type && lp.entity.id === pp.participant_id));
      return {
        ...(pp.id ? { id: pp.id } : {}),
        kind: pp.type,
        role: pp.role as ParticipantRole,
        note: pp.note ?? "",
        entity: { id: pp.participant_id, label: live ? labelOf((live.kind === "artist" ? live.entity.name : live.entity.title) ?? { ar: null, en: null }) : `#${pp.participant_id}` },
      };
    });
  }
}

async function loadEvent(): Promise<void> {
  if (id.value === null) return;
  loading.value = event.value === null;
  loadError.value = null;
  try {
    event.value = (await getEvent(id.value)).data;
    loadForm(event.value);
    originalEnd = form.endDate;
    if (draftMode.value) {
      try {
        await initDraft("events", id.value);
      } catch {
        // A failed draft lookup must not block the page; the live record still loads.
        return;
      }
      applyDraftToForm();
    }
  } catch (err) {
    loadError.value = err;
  } finally {
    loading.value = false;
  }
}
watch(id, () => void loadEvent(), { immediate: true });
void listThemes().then((r) => (themes.value = r.data)).catch(() => (themes.value = []));

const checklist = computed(() => {
  if (isNew.value || !event.value?.completeness) return liveChecklist();
  const missing = new Set([...event.value.completeness.blocking, ...event.value.completeness.minor]);
  return ["title", "event_type", "date", "venue_name", "city", "holder", "description", "participants"].map((key) => ({ key, met: !missing.has(key) }));
});
const CORE = ["title", "event_type", "date", "venue_name", "city"];
const coreMet = computed(() => checklist.value.filter((c) => CORE.includes(c.key)).every((c) => c.met));
const metCount = computed(() => checklist.value.filter((c) => c.met).length);
const status = computed<EventStatus>(() => event.value?.publication_status ?? "draft");

const busy = ref(false);
const saved = ref(false);
const error = ref<string | null>(null);
const createdId = ref<number | null>(null);
const fail = (err: unknown) => (error.value = err instanceof Error ? err.message : t("errors.generic"));

async function saveRelations(eventId: number): Promise<void> {
  await syncEventParticipants(eventId, participantsPayload());
  await syncEventThemes(eventId, themeIds.value);
}

async function save(): Promise<void> {
  busy.value = true;
  saved.value = false;
  error.value = null;
  if (isNew.value) {
    let newId: number;
    try {
      newId = (await createEvent(payload("create"))).data.id;
      createdId.value = newId;
    } catch (err) {
      fail(err);
      busy.value = false;
      return;
    }
    let failed = false;
    try {
      await saveRelations(newId);
    } catch {
      failed = true;
    }
    busy.value = false;
    if (!failed) await router.push(localePath("admin.events.edit", { id: newId }));
    return;
  }
  try {
    if (draftMode.value) {
      // Nothing changes live: fields and participants merge into the draft payload.
      // Theme tagging stays direct, like the other relations endpoints.
      await saveSection("fields", payload("update", originalEnd));
      await saveSection("participants", participantsPayload());
      await syncEventThemes(id.value!, themeIds.value);
    } else {
      await updateEvent(id.value!, payload("update", originalEnd));
      await saveRelations(id.value!);
      await loadEvent();
    }
    saved.value = true;
  } catch (err) {
    fail(err);
  } finally {
    busy.value = false;
  }
}

const draftNotice = ref<string | null>(null);

async function sendForReview(): Promise<void> {
  draftNotice.value = null;
  error.value = null;
  try {
    await submitDraftForReview();
    draftNotice.value = t("draft.submitSuccess");
  } catch (err) {
    // A submit refusal (e.g. "nothing in this draft differs from the
    // current record") has a specific, actionable reason; showing only the
    // generic fallback here hid it.
    error.value = err instanceof Error ? err.message : t("draft.submitError");
  }
}

function discardDraft(): void {
  draftNotice.value = null;
  error.value = null;
  resetDraftLocally();
  saved.value = false;
  if (event.value) {
    loadForm(event.value);
    originalEnd = form.endDate;
  }
}

async function publish(): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    await updateEvent(id.value!, payload("update", originalEnd));
    await saveRelations(id.value!);
    await publishEvent(id.value!);
    await loadEvent();
  } catch (err) {
    fail(err);
  } finally {
    busy.value = false;
  }
}

const searchArtworks = async (q: string): Promise<PickerOption[]> =>
  (await listAdminArtworks({ q })).data.map((a) => ({ id: a.id, label: labelOf(a.title) }));

function toggleTheme(themeId: number): void {
  themeIds.value = themeIds.value.includes(themeId) ? themeIds.value.filter((x) => x !== themeId) : [...themeIds.value, themeId];
}

onBeforeUnmount(() => (loadError.value = null));
const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
const missingKeys = computed(() => new Set(checklist.value.filter((c) => !c.met).map((c) => c.key)));
const missing = (key: string) => (missingKeys.value.has(key) ? "!border-danger" : "");
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <ErrorState v-else-if="loadError" :error="loadError" @retry="loadEvent" />
    <Spinner v-else-if="loading" class="mx-auto my-12 block" />

    <template v-else>
      <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
        <RouterLink :to="localePath('admin.events')" class="hover:text-ink">{{ t("events.edit.back") }}</RouterLink>
      </nav>

      <div class="mt-3 flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="status === 'published' ? 'bg-success-soft text-success' : 'bg-warn-soft text-warn'" data-testid="status-badge">{{ t(`events.statuses.${status}`) }}</span>
          <h1 class="mt-2 text-balance text-3xl font-semibold tracking-tight text-ink">{{ isNew ? t("events.edit.addTitle") : t("events.edit.editTitle") }}</h1>
          <p v-if="event" class="mt-1 text-sm text-ink-muted"><LocalizedText :text="event.title" /></p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft disabled:opacity-50" :disabled="busy || createdId !== null || draftLocked" data-testid="save" @click="save">{{ busy ? t("curation.detail.saving") : t("events.edit.saveDraft") }}</button>
          <button v-if="!isNew" type="button" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted" :disabled="!coreMet || busy || status === 'published'" :title="coreMet ? undefined : t('events.edit.publishBlocked')" data-testid="publish" @click="publish">{{ status === "published" ? t("events.edit.published") : t("events.edit.publish") }}</button>
        </div>
      </div>
      <DraftStatusBanner
        v-if="draftMode"
        :status="draftStatus"
        :blocker="draftBlocker"
        :can-submit="dirtySections.size > 0 || hasContent"
        :submitting="pendingSubmit"
        :review-note="draftReviewNote"
        @submit="sendForReview"
        @discard="discardDraft"
      />
      <p v-if="saved" class="mt-2 text-sm text-success" data-testid="saved-feedback">{{ draftMode ? t("draft.sectionSaved") : t("curation.detail.saved") }}</p>
      <p v-if="draftNotice" class="mt-2 text-sm text-success" data-testid="draft-notice">{{ draftNotice }}</p>
      <p v-if="error" class="mt-2 text-sm text-danger" role="alert">{{ error }}</p>
      <p v-if="createdId" class="mt-2 text-sm text-warn" data-testid="partial-failure">
        {{ t("events.edit.partialFailure") }}
        <RouterLink :to="localePath('admin.events.edit', { id: createdId })" class="font-medium underline">{{ t("archive.edit.openItem") }}</RouterLink>
      </p>

      <div class="mt-8 grid gap-10 lg:grid-cols-[22rem_1fr]">
        <aside class="space-y-6">
          <section class="rounded-lg border p-4" :class="coreMet ? 'border-line bg-surface' : 'border-danger bg-danger-soft'">
            <h2 class="text-base font-semibold" :class="coreMet ? 'text-ink' : 'text-danger'">{{ t("events.edit.checklistTitle") }}</h2>
            <ul class="mt-3 space-y-2" data-testid="checklist">
              <li v-for="c in checklist" :key="c.key" class="flex items-center gap-2 text-sm" :class="c.met ? 'text-ink-muted' : 'text-ink'">
                <input type="checkbox" class="size-4" :checked="c.met" disabled :aria-label="t(`events.edit.checklist.${c.key}`)" />
                <span>{{ t(`events.edit.checklist.${c.key}`) }}</span>
              </li>
            </ul>
            <p class="mt-3 text-xs tabular-nums text-ink-muted">{{ t("events.edit.completion", { met: metCount, total: checklist.length }) }}</p>
          </section>

          <section class="rounded-lg border border-line bg-surface p-4">
            <h2 class="text-xs font-semibold text-ink-muted">{{ t("events.edit.themes") }}</h2>
            <p v-if="themes.length === 0" class="mt-2 text-sm text-ink-muted">{{ t("events.edit.noThemes") }}</p>
            <ul v-else class="mt-3 space-y-2" data-testid="themes">
              <li v-for="th in themes" :key="th.id">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-ink"><input type="checkbox" class="size-4 accent-ink" :checked="themeIds.includes(th.id)" @change="toggleTheme(th.id)" />{{ pick(th.label)?.text }}</label>
              </li>
            </ul>
          </section>

          <section v-if="event" class="rounded-lg border border-line bg-surface p-4">
            <h2 class="text-xs font-semibold text-ink-muted">{{ t("events.edit.archiveItems") }}</h2>
            <p v-if="event.archive_items.length === 0" class="mt-2 text-sm text-ink-muted">{{ t("events.edit.noArchiveItems") }}</p>
            <ul v-else class="mt-3 space-y-2" data-testid="archive-items">
              <li v-for="a in event.archive_items" :key="a.id" class="text-sm text-ink"><RouterLink :to="localePath('admin.archive.edit', { id: a.id })" class="hover:underline"><LocalizedText :text="a.title" /></RouterLink></li>
            </ul>
          </section>
        </aside>

        <div class="space-y-10">
          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("events.edit.identification") }}</h2>
            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
              <label class="text-xs text-ink-muted sm:col-span-2">{{ t("events.edit.eventType") }}<select v-model="form.type" :class="input" data-testid="event-type"><option v-for="ty in EVENT_TYPES" :key="ty" :value="ty">{{ t(`events.types.${ty}`) }}</option></select></label>
              <label class="text-xs text-ink-muted">{{ t("events.edit.titleAr") }}<input v-model="form.titleAr" type="text" dir="rtl" :class="[input, missing('title')]" data-testid="title-ar" /></label>
              <label class="text-xs text-ink-muted">{{ t("events.edit.titleEn") }}<input v-model="form.titleEn" type="text" dir="ltr" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("events.edit.descriptionAr") }}<textarea v-model="form.descriptionAr" rows="4" dir="rtl" :class="input" /></label>
              <label class="text-xs text-ink-muted">{{ t("events.edit.descriptionEn") }}<textarea v-model="form.descriptionEn" rows="4" dir="ltr" :class="input" /></label>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("events.edit.when") }}</h2>
            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
              <FuzzyDateField v-model="start" class="sm:col-span-2" :legend="t('events.edit.startDate')" :missing="missingKeys.has('date')" />
              <label class="text-xs text-ink-muted">{{ t("events.edit.endDate") }}<input v-model="form.endDate" type="date" dir="ltr" :class="input" data-testid="end-date" /></label>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("events.edit.where") }}</h2>
            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
              <label class="text-xs text-ink-muted">{{ t("events.edit.venue") }}<input v-model="form.venue" type="text" :class="[input, missing('venue_name')]" data-testid="venue" /></label>
              <label class="text-xs text-ink-muted">{{ t("events.edit.city") }}<input v-model="form.city" type="text" :class="[input, missing('city')]" data-testid="city" /></label>
              <div class="text-xs text-ink-muted sm:col-span-2">{{ t("events.edit.holder") }}<EntityPicker v-model="holder" :search="searchHolderOptions" :placeholder="t('events.edit.searchHolder')" /></div>
            </div>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("events.edit.participantsSection") }}</h2>
            <div class="mt-4">
              <ListEditor v-model="participants" :create="newParticipant" :add-label="t('events.edit.addParticipant')">
                <template #default="{ item }">
                  <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <label class="text-xs text-ink-muted">{{ t("events.edit.kind") }}<select v-model="item.kind" :class="input" @change="item.entity = null"><option value="artist">{{ t("events.edit.kinds.artist") }}</option><option value="artwork">{{ t("events.edit.kinds.artwork") }}</option></select></label>
                    <div class="text-xs text-ink-muted">{{ t("events.edit.record") }}<EntityPicker :key="item.kind" v-model="item.entity" :search="item.kind === 'artist' ? searchArtistOptions : searchArtworks" :placeholder="t('events.edit.searchRecord')" /></div>
                    <label class="text-xs text-ink-muted">{{ t("events.edit.role") }}<select v-model="item.role" :class="input" data-testid="participant-role"><option v-for="r in PARTICIPANT_ROLES" :key="r" :value="r">{{ t(`events.roles.${r}`) }}</option></select></label>
                    <label class="text-xs text-ink-muted">{{ t("events.edit.note") }}<input v-model="item.note" type="text" :class="input" /></label>
                  </div>
                </template>
              </ListEditor>
            </div>
          </section>
        </div>
      </div>
    </template>
  </section>
</template>
