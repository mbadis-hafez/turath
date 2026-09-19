<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import type { PaginationMeta } from "@/types/api";

const props = defineProps<{
  meta: PaginationMeta | null;
}>();

const emit = defineEmits<{
  change: [page: number];
}>();

const { t } = useI18n();

const pages = computed<number[]>(() => {
  if (!props.meta) return [];
  const { current_page, last_page } = props.meta;
  const window = 2;
  const start = Math.max(1, current_page - window);
  const end = Math.min(last_page, current_page + window);
  const result: number[] = [];
  for (let i = start; i <= end; i += 1) result.push(i);
  return result;
});

const hasPrevious = computed(() => (props.meta?.current_page ?? 1) > 1);
const hasNext = computed(
  () => props.meta !== null && props.meta.current_page < props.meta.last_page,
);

function go(page: number): void {
  if (
    !props.meta ||
    page < 1 ||
    page > props.meta.last_page ||
    page === props.meta.current_page
  ) {
    return;
  }
  emit("change", page);
}
</script>

<template>
  <nav
    v-if="meta && meta.last_page > 1"
    class="flex items-center justify-center gap-1"
    :aria-label="t('common.pagination')"
  >
    <button
      type="button"
      class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink disabled:cursor-not-allowed disabled:opacity-40"
      :disabled="!hasPrevious"
      @click="go(meta.current_page - 1)"
    >
      {{ t("common.previous") }}
    </button>
    <button
      v-for="page in pages"
      :key="page"
      type="button"
      class="rounded-md border px-3 py-1.5 text-sm"
      :class="
        page === meta.current_page
          ? 'border-accent bg-accent font-medium text-surface'
          : 'border-line bg-surface text-ink'
      "
      :aria-current="page === meta.current_page ? 'page' : undefined"
      @click="go(page)"
    >
      {{ page }}
    </button>
    <button
      type="button"
      class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink disabled:cursor-not-allowed disabled:opacity-40"
      :disabled="!hasNext"
      @click="go(meta.current_page + 1)"
    >
      {{ t("common.next") }}
    </button>
  </nav>
</template>
