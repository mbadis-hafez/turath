<script setup lang="ts" generic="T">
import { useI18n } from "vue-i18n";

const props = defineProps<{
  modelValue: T[];
  create: () => T;
  addLabel: string;
}>();

const emit = defineEmits<{
  "update:modelValue": [items: T[]];
}>();

const { t } = useI18n();

function add(): void {
  emit("update:modelValue", [...props.modelValue, props.create()]);
}

function remove(index: number): void {
  emit("update:modelValue", props.modelValue.filter((_, i) => i !== index));
}
</script>

<template>
  <div class="space-y-3">
    <div v-for="(item, index) in modelValue" :key="index" class="rounded-md border border-line bg-surface p-3" data-testid="list-item">
      <slot :item="item" :index="index" />
      <button type="button" class="mt-2 text-xs font-medium text-danger hover:underline" @click="remove(index)">
        {{ t("curation.profileForm.remove") }}
      </button>
    </div>
    <button type="button" class="rounded-md border border-line px-3 py-1.5 text-xs font-medium text-ink hover:bg-neutral-soft" data-testid="list-add" @click="add">
      + {{ addLabel }}
    </button>
  </div>
</template>
