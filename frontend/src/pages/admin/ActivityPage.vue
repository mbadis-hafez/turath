<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { fetchActivity } from "@/api/activity";
import ActivityFilterBar, {
  type SubjectTypeOption,
} from "@/components/activity/ActivityFilterBar.vue";
import ActivityTimeline from "@/components/activity/ActivityTimeline.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Pagination from "@/components/common/Pagination.vue";
import { useAuthStore } from "@/stores/auth";
import { ApiError, type PaginatedResponse } from "@/types/api";
import type {
  ActivityCauser,
  ActivityEntry,
  ActivityEvent,
} from "@/types/activity";

const { t, te } = useI18n();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

/** Static 403 payload used when the logged-in user lacks activity.view. */
const forbiddenError = new ApiError("forbidden", "Forbidden", { status: 403 });

const loading = ref(false);
const error = ref<unknown>(null);
const feed = ref<PaginatedResponse<ActivityEntry> | null>(null);
/** Causers collected from fetched pages this session — powers the user filter. */
const seenCausers = ref(new Map<number, ActivityCauser>());

let abortController: AbortController | null = null;

// ---------------------------------------------------------------------------
// Filter + page state, mirrored from the URL query string.
// ---------------------------------------------------------------------------
const filters = computed(() => ({
  subject_type:
    typeof route.query.subject_type === "string"
      ? route.query.subject_type
      : "",
  causer_id:
    typeof route.query.causer_id === "string" ? route.query.causer_id : "",
  event: typeof route.query.event === "string" ? route.query.event : "",
  date_from:
    typeof route.query.date_from === "string" ? route.query.date_from : "",
  date_to: typeof route.query.date_to === "string" ? route.query.date_to : "",
  page:
    Number.parseInt(
      typeof route.query.page === "string" ? route.query.page : "1",
      10,
    ) || 1,
}));

const KNOWN_SUBJECT_TYPES = ["Artist", "Artwork"];

const subjectTypeOptions = computed<SubjectTypeOption[]>(() => {
  const seen = new Set<string>(KNOWN_SUBJECT_TYPES);
  for (const entry of feed.value?.data ?? []) seen.add(entry.subject_type);
  return [...seen].map((value) => {
    const key = `activity.subjectType.${value.toLowerCase()}`;
    return { value, label: te(key) ? t(key) : value };
  });
});

const causerOptions = computed<ActivityCauser[]>(() =>
  [...seenCausers.value.values()].sort((a, b) => a.name.localeCompare(b.name)),
);

/** Writes filter updates back to the query string; any change resets page to 1. */
function applyFilter(patch: Record<string, string>): void {
  const query: Record<string, string> = {};
  for (const [key, value] of Object.entries({ ...filters.value, ...patch })) {
    if (key === "page") continue;
    if (value !== "" && value !== null && value !== undefined)
      query[key] = String(value);
  }
  router.push({ query });
}

function goToPage(page: number): void {
  const query = { ...route.query };
  if (page <= 1) delete query.page;
  else query.page = String(page);
  router.push({ query }).then(() => {
    document
      .getElementById("activity-results")
      ?.scrollIntoView({ block: "start" });
  });
}

// ---------------------------------------------------------------------------
// Data loading
// ---------------------------------------------------------------------------
async function load(): Promise<void> {
  if (!auth.can("activity.view")) return;
  abortController?.abort();
  abortController = new AbortController();
  loading.value = true;
  error.value = null;
  try {
    const { page, ...rest } = filters.value;
    const result = await fetchActivity(
      {
        subject_type: rest.subject_type || undefined,
        causer_id: rest.causer_id ? Number(rest.causer_id) : undefined,
        event: (rest.event || undefined) as ActivityEvent | undefined,
        date_from: rest.date_from || undefined,
        date_to: rest.date_to || undefined,
        page,
      },
      abortController.signal,
    );
    feed.value = result;
    const next = new Map(seenCausers.value);
    for (const entry of result.data) {
      if (entry.causer) next.set(entry.causer.id, entry.causer);
    }
    seenCausers.value = next;
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    error.value = err;
  } finally {
    loading.value = false;
  }
}

watch(
  () => route.query,
  () => {
    void load();
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  abortController?.abort();
});

const canView = computed(() => auth.can("activity.view"));
</script>

<template>
  <section>
    <h1 class="text-3xl font-semibold tracking-tight text-ink">
      {{ t("activity.title") }}
    </h1>

    <ErrorState v-if="!canView" :error="forbiddenError" class="mt-8" />
    <template v-else>
      <ActivityFilterBar
        class="mt-6"
        :subject-type="filters.subject_type"
        :causer-id="filters.causer_id"
        :event="filters.event"
        :date-from="filters.date_from"
        :date-to="filters.date_to"
        :causers="causerOptions"
        :subject-types="subjectTypeOptions"
        @update:subject-type="(v) => applyFilter({ subject_type: v })"
        @update:causer-id="(v) => applyFilter({ causer_id: v })"
        @update:event="(v) => applyFilter({ event: v })"
        @update:date-from="(v) => applyFilter({ date_from: v })"
        @update:date-to="(v) => applyFilter({ date_to: v })"
      />

      <div id="activity-results" class="mt-8">
        <ErrorState v-if="error" :error="error" @retry="load" />
        <template v-else>
          <ActivityTimeline :entries="feed?.data ?? []" :loading="loading" />
          <Pagination
            v-if="feed"
            class="mt-8"
            :meta="feed.meta"
            @change="goToPage"
          />
        </template>
      </div>
    </template>
  </section>
</template>
