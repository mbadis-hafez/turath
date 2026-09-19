<script setup lang="ts">
export interface TabItem {
  key: string;
  label: string;
}

const props = defineProps<{
  tabs: TabItem[];
  activeKey: string;
}>();

const emit = defineEmits<{
  "update:activeKey": [key: string];
}>();

function activate(key: string): void {
  if (key !== props.activeKey) emit("update:activeKey", key);
}

function focusTab(key: string): void {
  document.getElementById(`tab-${key}`)?.focus();
}

function onKeydown(event: KeyboardEvent, index: number): void {
  const count = props.tabs.length;
  if (count === 0) return;

  let next = -1;
  if (event.key === "ArrowRight" || event.key === "ArrowDown") {
    next = (index + 1) % count;
  } else if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
    next = (index - 1 + count) % count;
  } else if (event.key === "Home") {
    next = 0;
  } else if (event.key === "End") {
    next = count - 1;
  }

  if (next === -1) return;
  event.preventDefault();
  const tab = props.tabs[next];
  activate(tab.key);
  focusTab(tab.key);
}
</script>

<template>
  <div>
    <div role="tablist" class="flex gap-1 border-b border-line">
      <button
        v-for="(tab, index) in tabs"
        :id="`tab-${tab.key}`"
        :key="tab.key"
        type="button"
        role="tab"
        class="-mb-px rounded-t-md border-b-2 px-4 py-2 text-sm font-medium"
        :class="
          tab.key === activeKey
            ? 'border-accent text-accent-strong'
            : 'border-transparent text-ink-muted hover:border-line hover:text-ink'
        "
        :aria-selected="tab.key === activeKey"
        :aria-controls="`panel-${tab.key}`"
        :tabindex="tab.key === activeKey ? 0 : -1"
        @click="activate(tab.key)"
        @keydown="onKeydown($event, index)"
      >
        {{ tab.label }}
      </button>
    </div>
    <div
      :id="`panel-${activeKey}`"
      role="tabpanel"
      class="py-6"
      :aria-labelledby="`tab-${activeKey}`"
    >
      <slot :name="activeKey" />
    </div>
  </div>
</template>
