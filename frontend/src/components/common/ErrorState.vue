<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { ApiError } from "@/types/api";

const props = withDefaults(
  defineProps<{
    error: unknown;
  }>(),
  {},
);

const emit = defineEmits<{
  retry: [];
}>();

const { t } = useI18n();

const title = computed(() => {
  if (props.error instanceof ApiError) {
    switch (props.error.kind) {
      case "forbidden":
        return t("errors.forbidden");
      case "network":
        return t("errors.network");
      case "throttled":
        return t("errors.throttled");
      case "not_found":
        return t("errors.notFound");
      default:
        return t("errors.generic");
    }
  }
  return t("errors.generic");
});

const isForbidden = computed(
  () => props.error instanceof ApiError && props.error.kind === "forbidden",
);
</script>

<template>
  <div
    class="rounded-lg border border-line bg-surface px-6 py-10 text-center"
    role="alert"
  >
    <p
      class="text-lg font-medium"
      :class="isForbidden ? 'text-warn' : 'text-danger'"
    >
      {{ title }}
    </p>
    <p
      v-if="error instanceof ApiError && error.message"
      class="mt-1 text-sm text-ink-muted"
    >
      {{ error.message }}
    </p>
    <button
      v-if="!isForbidden"
      type="button"
      class="mt-4 rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong"
      @click="emit('retry')"
    >
      {{ $t("common.retry") }}
    </button>
  </div>
</template>
