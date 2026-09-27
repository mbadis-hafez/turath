<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import type { RouteLocationRaw } from "vue-router";

import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import type { ContentOverview } from "@/types/editorDashboard";

const props = defineProps<{
  overview: ContentOverview;
}>();

const { t } = useI18n();
const auth = useAuthStore();
const { localePath } = useLocalePath();

type OverviewKey = keyof ContentOverview;

const REGISTRIES: [OverviewKey, string, string][] = [
  ["artists", "artists.manage", "admin.artists"],
  ["artworks", "artworks.manage", "admin.artworks"],
  ["events", "events.manage", "admin.events"],
  ["archive_items", "archive.manage", "admin.archive"],
];

interface OverviewRow {
  key: OverviewKey;
  total: number;
  incomplete: number;
  to: RouteLocationRaw | null;
}

const rows = computed<OverviewRow[]>(() =>
  REGISTRIES.map(([key, permission, routeName]) => ({
    key,
    total: props.overview[key].total,
    incomplete: props.overview[key].incomplete,
    to: auth.can(permission) ? localePath(routeName) : null,
  })),
);
</script>

<template>
  <section aria-labelledby="content-heading" class="rounded-lg border border-line bg-surface p-4">
    <h2 id="content-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("editorDashboard.content.title") }}
    </h2>
    <ul class="mt-3 divide-y divide-line" data-testid="content-overview">
      <li v-for="row in rows" :key="row.key" class="py-2.5 first:pt-0 last:pb-0">
        <component
          :is="row.to ? 'RouterLink' : 'div'"
          :to="row.to ?? undefined"
          class="flex items-baseline justify-between gap-2"
          :class="row.to ? 'group' : ''"
        >
          <span class="text-sm" :class="row.to ? 'text-ink group-hover:text-accent' : 'text-ink'">
            {{ t(`editorDashboard.content.entities.${row.key}`) }}
            <span v-if="row.incomplete > 0" class="ms-2 text-xs text-ink-muted">
              {{ t("editorDashboard.content.incomplete", { count: row.incomplete }) }}
            </span>
          </span>
          <span class="text-lg font-semibold tabular-nums text-ink">{{ row.total }}</span>
        </component>
      </li>
    </ul>
  </section>
</template>
