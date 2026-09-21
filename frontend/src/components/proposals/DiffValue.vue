<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps<{ value: unknown; tone?: "old" | "new" }>();
const { t } = useI18n();

const text = computed(() => {
  const v = props.value;
  if (v === null || v === undefined || v === "") return null;
  if (Array.isArray(v)) return v.join("، ");
  if (typeof v === "boolean") return v ? t("proposals.true") : t("proposals.false");
  return String(v);
});
</script>

<template>
  <bdi v-if="text" class="whitespace-pre-wrap" :class="tone === 'old' ? 'text-ink-muted line-through decoration-danger/50' : 'text-ink'">{{ text }}</bdi>
  <span v-else class="text-ink-faint">{{ t("proposals.empty") }}</span>
</template>
