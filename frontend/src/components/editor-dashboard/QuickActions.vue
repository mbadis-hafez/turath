<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import type { RouteLocationRaw } from "vue-router";

import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";

const { t } = useI18n();
const auth = useAuthStore();
const { localePath } = useLocalePath();

interface QuickAction {
  key: string;
  to: RouteLocationRaw;
}

const actions = computed<QuickAction[]>(() => {
  const all: [boolean, string, RouteLocationRaw][] = [
    [auth.can("artists.manage"), "newArtist", localePath("admin.artists.new")],
    [auth.can("artworks.manage"), "newArtwork", localePath("admin.artworks.new")],
    [auth.can("events.manage"), "newEvent", localePath("admin.events.new")],
    [auth.can("archive.manage"), "uploadArchive", localePath("admin.archive.new")],
  ];
  return all.filter(([show]) => show).map(([, key, to]) => ({ key, to }));
});
</script>

<template>
  <section
    v-if="actions.length > 0"
    aria-labelledby="quick-actions-heading"
    class="rounded-lg border border-line bg-surface p-4"
  >
    <h2 id="quick-actions-heading" class="border-b-2 border-ink pb-2 text-sm font-semibold text-ink">
      {{ t("editorDashboard.quickActions.title") }}
    </h2>
    <ul class="mt-3 space-y-2">
      <li v-for="action in actions" :key="action.key">
        <RouterLink
          :to="action.to"
          class="flex items-center gap-2 rounded-md border border-line px-3 py-2 text-sm font-medium text-ink transition-colors hover:border-accent hover:text-accent"
          :data-testid="`quick-${action.key}`"
        >
          <span aria-hidden="true" class="text-accent">+</span>
          {{ t(`editorDashboard.quickActions.${action.key}`) }}
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
