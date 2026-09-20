<script setup lang="ts">
import { onBeforeUnmount, ref } from "vue";
import { useI18n } from "vue-i18n";

export interface PickerOption {
  id: number;
  label: string;
}

const props = defineProps<{
  search: (q: string) => Promise<PickerOption[]>;
  placeholder?: string;
  clearLabel?: string;
}>();

const picked = defineModel<PickerOption | null>({ default: null });
const { t } = useI18n();

const term = ref("");
const options = ref<PickerOption[]>([]);
let timer: ReturnType<typeof setTimeout> | null = null;
let seq = 0;

function onInput(): void {
  if (timer) clearTimeout(timer);
  timer = setTimeout(async () => {
    const mine = ++seq;
    const value = term.value.trim();
    const found = value ? await props.search(value).catch(() => []) : [];
    if (mine === seq) options.value = found.slice(0, 8);
  }, 250);
}

function choose(option: PickerOption): void {
  picked.value = option;
  options.value = [];
  term.value = "";
}

onBeforeUnmount(() => timer && clearTimeout(timer));
</script>

<template>
  <div>
    <p v-if="picked" class="mt-1 flex items-center justify-between gap-2 rounded-md border border-line bg-neutral-soft px-3 py-2 text-sm text-ink" data-testid="picked">
      <span>{{ picked.label }}</span>
      <button type="button" class="text-xs text-ink-muted hover:text-danger" @click="picked = null">{{ clearLabel ?? t("common.clear") }}</button>
    </p>
    <template v-else>
      <input
        v-model="term"
        type="search"
        :placeholder="placeholder"
        class="mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none"
        data-testid="picker-input"
        @input="onInput"
      />
      <ul v-if="options.length" class="mt-1 rounded-md border border-line bg-surface py-1" data-testid="picker-options">
        <li v-for="o in options" :key="o.id">
          <button type="button" class="block w-full px-3 py-1.5 text-start text-sm text-ink hover:bg-neutral-soft" @click="choose(o)">{{ o.label }}</button>
        </li>
      </ul>
    </template>
  </div>
</template>
