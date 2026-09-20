<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue";
import { useI18n } from "vue-i18n";

import type { PortraitRights } from "@/types/artistCuration";

const props = defineProps<{
  /** Server URL of the saved portrait, if any. */
  url: string | null;
  busy?: boolean;
}>();

const emit = defineEmits<{
  select: [file: File];
  remove: [];
}>();

const rights = defineModel<PortraitRights>("rights", { default: "unknown" });
const { t } = useI18n();

const RIGHTS: PortraitRights[] = ["unknown", "licensed", "public_domain", "all_rights_reserved"];
const staged = ref<string | null>(null);
const preview = computed(() => staged.value ?? props.url);

function onChange(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!file) return;
  if (staged.value) URL.revokeObjectURL(staged.value);
  staged.value = URL.createObjectURL(file);
  emit("select", file);
}

function clear(): void {
  if (staged.value) URL.revokeObjectURL(staged.value);
  staged.value = null;
  emit("remove");
}

onBeforeUnmount(() => {
  if (staged.value) URL.revokeObjectURL(staged.value);
});
</script>

<template>
  <div>
    <div class="aspect-[4/3] overflow-hidden rounded-md bg-neutral-soft">
      <img v-if="preview" :src="preview" alt="" class="size-full object-cover" data-testid="portrait-preview" />
    </div>
    <p v-if="!preview" class="mt-2 text-xs text-ink-muted">{{ t("curation.profileForm.noPortrait") }}</p>

    <div class="mt-3 flex flex-wrap items-center gap-2">
      <label class="cursor-pointer rounded-md border border-line px-3 py-1.5 text-xs font-medium text-ink hover:bg-neutral-soft" :class="busy ? 'pointer-events-none opacity-50' : ''">
        {{ preview ? t("curation.profileForm.replaceImage") : t("curation.profileForm.uploadImage") }}
        <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" data-testid="portrait-input" @change="onChange" />
      </label>
      <button v-if="preview" type="button" class="text-xs font-medium text-danger hover:underline" :disabled="busy" @click="clear">
        {{ t("curation.profileForm.removeImage") }}
      </button>
    </div>

    <label class="mt-3 block text-xs text-ink-muted">
      {{ t("curation.profileForm.portraitRights") }}
      <select v-model="rights" class="mt-1 w-full rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none">
        <option v-for="r in RIGHTS" :key="r" :value="r">{{ t(`curation.profileForm.rights.${r}`) }}</option>
      </select>
    </label>
    <p class="mt-1 text-pretty text-xs text-ink-muted">{{ t("curation.profileForm.portraitPublicHint") }}</p>
  </div>
</template>
