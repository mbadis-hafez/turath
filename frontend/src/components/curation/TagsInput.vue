<script setup lang="ts">
import { ref } from "vue";
import { useI18n } from "vue-i18n";

defineProps<{ placeholder?: string; invalid?: boolean }>();
const tags = defineModel<string[]>({ required: true });
const { t } = useI18n();
const draft = ref("");

function commit(): void {
  const value = draft.value.trim().replace(/[,،]$/, "").trim();
  draft.value = "";
  if (value && !tags.value.includes(value)) tags.value = [...tags.value, value];
}
function remove(tag: string): void {
  tags.value = tags.value.filter((x) => x !== tag);
}
function onKey(event: KeyboardEvent): void {
  if (event.key === "Enter" || event.key === "," || event.key === "،") {
    event.preventDefault();
    commit();
  } else if (event.key === "Backspace" && draft.value === "" && tags.value.length > 0) {
    tags.value = tags.value.slice(0, -1);
  }
}
</script>

<template>
  <div class="mt-1 flex min-h-11 flex-wrap items-center gap-2 rounded-md border bg-surface px-3 py-2 focus-within:border-accent" :class="invalid ? 'border-danger' : 'border-line'">
    <span v-for="tag in tags" :key="tag" class="flex items-center gap-1 rounded-sm bg-neutral-soft px-2 py-0.5 text-sm text-ink" data-testid="tag">
      {{ tag }}
      <button type="button" class="text-ink-muted hover:text-danger" :aria-label="t('curation.profileForm.remove')" @click="remove(tag)">×</button>
    </span>
    <input v-model="draft" type="text" :placeholder="placeholder" class="min-w-32 flex-1 bg-transparent text-sm text-ink outline-none" data-testid="tag-input" @keydown="onKey" @blur="commit" />
  </div>
</template>
