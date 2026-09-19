<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { VerifiedStatus } from "@/types/artist";

const props = defineProps<{
  status: VerifiedStatus;
}>();

const { t } = useI18n();

const label = computed(() =>
  props.status === "verified" ? t("artists.verified") : t("artists.disputed"),
);

const isVerified = computed(() => props.status === "verified");
</script>

<template>
  <span
    v-if="status !== 'unverified'"
    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
    :class="
      isVerified
        ? 'bg-accent-soft text-accent-strong'
        : 'bg-warn-soft text-warn'
    "
    :title="t('artists.verifiedHelp')"
  >
    <span aria-hidden="true">{{ isVerified ? "✓" : "!" }}</span>
    {{ label }}
  </span>
</template>
