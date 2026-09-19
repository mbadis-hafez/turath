<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import {
  ACTIVITY_EVENTS,
  type ActivityCauser,
  type ActivityEvent,
} from "@/types/activity";

export interface SubjectTypeOption {
  value: string;
  label: string;
}

defineProps<{
  subjectType: string;
  causerId: string;
  event: string;
  dateFrom: string;
  dateTo: string;
  causers: ActivityCauser[];
  subjectTypes: SubjectTypeOption[];
}>();

const emit = defineEmits<{
  "update:subjectType": [value: string];
  "update:causerId": [value: string];
  "update:event": [value: string];
  "update:dateFrom": [value: string];
  "update:dateTo": [value: string];
}>();

const { t } = useI18n();

const eventOptions = computed(() =>
  ACTIVITY_EVENTS.map((event: ActivityEvent) => ({
    value: event,
    label: t(`activity.event.${event}`),
  })),
);

function selectedValue(target: EventTarget | null): string {
  return (target as HTMLSelectElement | HTMLInputElement).value;
}
</script>

<template>
  <form
    class="grid grid-cols-1 gap-3 rounded-lg border border-line bg-surface p-4 sm:grid-cols-2 lg:grid-cols-5"
    @submit.prevent
  >
    <label class="flex flex-col gap-1 text-sm">
      <span class="font-medium text-ink-muted">{{
        t("activity.filterByType")
      }}</span>
      <select
        class="rounded-md border border-line bg-paper px-2 py-1.5 text-ink"
        :value="subjectType"
        @change="emit('update:subjectType', selectedValue($event.target))"
      >
        <option value="">{{ t("activity.allTypes") }}</option>
        <option
          v-for="option in subjectTypes"
          :key="option.value"
          :value="option.value"
        >
          {{ option.label }}
        </option>
      </select>
    </label>

    <label class="flex flex-col gap-1 text-sm">
      <span class="font-medium text-ink-muted">{{
        t("activity.filterByUser")
      }}</span>
      <select
        class="rounded-md border border-line bg-paper px-2 py-1.5 text-ink"
        :value="causerId"
        @change="emit('update:causerId', selectedValue($event.target))"
      >
        <option value="">{{ t("activity.allUsers") }}</option>
        <option
          v-for="causer in causers"
          :key="causer.id"
          :value="String(causer.id)"
        >
          {{ causer.name }}
        </option>
      </select>
    </label>

    <label class="flex flex-col gap-1 text-sm">
      <span class="font-medium text-ink-muted">{{
        t("activity.filterByEvent")
      }}</span>
      <select
        class="rounded-md border border-line bg-paper px-2 py-1.5 text-ink"
        :value="event"
        @change="emit('update:event', selectedValue($event.target))"
      >
        <option value="">{{ t("activity.allEvents") }}</option>
        <option
          v-for="option in eventOptions"
          :key="option.value"
          :value="option.value"
        >
          {{ option.label }}
        </option>
      </select>
    </label>

    <label class="flex flex-col gap-1 text-sm">
      <span class="font-medium text-ink-muted">{{
        t("activity.dateFrom")
      }}</span>
      <input
        type="date"
        class="rounded-md border border-line bg-paper px-2 py-1.5 text-ink"
        :value="dateFrom"
        @change="emit('update:dateFrom', selectedValue($event.target))"
      />
    </label>

    <label class="flex flex-col gap-1 text-sm">
      <span class="font-medium text-ink-muted">{{ t("activity.dateTo") }}</span>
      <input
        type="date"
        class="rounded-md border border-line bg-paper px-2 py-1.5 text-ink"
        :value="dateTo"
        @change="emit('update:dateTo', selectedValue($event.target))"
      />
    </label>
  </form>
</template>
