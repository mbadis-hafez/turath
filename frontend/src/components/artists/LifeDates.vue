<script setup lang="ts">
import PartialDateDisplay from "@/components/common/PartialDateDisplay.vue";
import type { PartialDate } from "@/types/artist";

defineProps<{
  date: PartialDate;
  label?: string;
}>();
</script>

<template>
  <PartialDateDisplay :date="date" :label="label" />
</template>
