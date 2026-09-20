<script setup lang="ts">
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import PartialDateDisplay from "@/components/common/PartialDateDisplay.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import type { PublicEventRef } from "@/types/event";

defineProps<{ events: PublicEventRef[] }>();
const { t } = useI18n();
const { localePath } = useLocalePath();
</script>

<template>
  <ul class="divide-y divide-line border-y border-line" data-testid="public-events">
    <li v-for="e in events" :key="`${e.id}-${e.role}`" class="flex flex-wrap items-start justify-between gap-3 py-4" data-testid="public-event">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <span class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink-muted">{{ t(`events.types.${e.event_type}`) }}</span>
          <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="e.role === 'awardee' ? 'bg-accent-soft text-accent-strong' : 'bg-neutral-soft text-ink-muted'" data-testid="event-role">{{ t(`events.roles.${e.role}`) }}</span>
        </div>
        <h3 class="mt-1 text-base font-semibold text-ink"><RouterLink :to="localePath('events.show', { id: e.id })" class="hover:underline"><LocalizedText :text="e.title" /></RouterLink></h3>
        <p v-if="e.note" class="text-sm text-ink-muted">{{ e.note }}</p>
        <p class="text-xs text-ink-muted">{{ [e.venue_name, e.city].filter(Boolean).join("، ") }}</p>
      </div>
      <p v-if="e.start" class="text-sm tabular-nums text-ink-muted"><PartialDateDisplay :date="e.start" /></p>
    </li>
  </ul>
</template>
