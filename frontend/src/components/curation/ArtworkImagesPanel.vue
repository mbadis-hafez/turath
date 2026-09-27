<script setup lang="ts">
import { ref } from "vue";
import { useI18n } from "vue-i18n";

import type { ArtworkImage, ImageRights } from "@/types/artworkCuration";

const props = defineProps<{ images: ArtworkImage[]; busy?: boolean; max?: number }>();
const emit = defineEmits<{
  upload: [files: File[], rights: ImageRights];
  final: [id: number];
  rights: [id: number, rights: ImageRights];
  remove: [id: number];
}>();

const { t } = useI18n();
const RIGHTS: ImageRights[] = ["unknown", "licensed", "public_domain", "all_rights_reserved"];
const newRights = ref<ImageRights>("unknown");
const dragOver = ref(false);

const main = () => props.images.find((i) => i.is_final) ?? props.images[0] ?? null;
const atCap = () => props.max !== undefined && props.images.length >= props.max;

function onFile(event: Event): void {
  const input = event.target as HTMLInputElement;
  const files = input.files ? Array.from(input.files) : [];
  if (files.length > 0) emit("upload", files, newRights.value);
  input.value = "";
}

function onDrop(event: DragEvent): void {
  dragOver.value = false;
  const files = event.dataTransfer?.files ? Array.from(event.dataTransfer.files).filter((f) => f.type.startsWith("image/")) : [];
  if (files.length > 0) emit("upload", files, newRights.value);
}
</script>

<template>
  <div class="rounded-lg border border-line bg-surface p-4">
    <div class="aspect-[4/3] overflow-hidden rounded-md bg-neutral-soft">
      <img v-if="main()" :src="main()!.url" :alt="main()!.filename ?? ''" class="size-full object-contain" data-testid="main-image" />
    </div>
    <p class="mt-3 text-pretty text-xs text-ink-muted" data-testid="image-caption">
      {{ images.some((i) => i.is_final) ? t("curation.artworkImages.finalCaption") : t("curation.artworkImages.noFinalCaption") }}
    </p>

    <ul v-if="images.length" class="mt-4 space-y-3" data-testid="image-list">
      <li v-for="i in images" :key="i.id" class="flex items-start gap-3 text-xs">
        <img :src="i.url" :alt="i.filename ?? ''" class="size-14 shrink-0 rounded-sm bg-neutral-soft object-cover" />
        <div class="min-w-0 flex-1 space-y-1">
          <p class="truncate text-ink" dir="ltr">{{ i.filename }}</p>
          <p class="tabular-nums text-ink-muted" dir="ltr">{{ i.width_px }}×{{ i.height_px }}</p>
          <select :value="i.rights_status" class="w-full rounded-sm border border-line bg-surface px-1.5 py-1 text-xs text-ink" :aria-label="t('curation.profileForm.portraitRights')" :disabled="busy" @change="emit('rights', i.id, ($event.target as HTMLSelectElement).value as ImageRights)">
            <option v-for="r in RIGHTS" :key="r" :value="r">{{ t(`curation.profileForm.rights.${r}`) }}</option>
          </select>
          <div class="flex gap-3">
            <span v-if="i.is_final" class="rounded-sm bg-success-soft px-1.5 py-0.5 font-medium text-success" data-testid="primary-badge">{{ t("curation.artworkImages.final") }}</span>
            <button v-else type="button" class="font-medium text-accent-strong hover:underline disabled:opacity-50" :disabled="busy" data-testid="make-final" @click="emit('final', i.id)">{{ t("curation.artworkImages.makeFinal") }}</button>
            <button type="button" class="text-ink-muted hover:text-danger disabled:opacity-50" :disabled="busy" @click="emit('remove', i.id)">{{ t("curation.artworkImages.remove") }}</button>
          </div>
        </div>
      </li>
    </ul>

    <p v-if="atCap()" class="mt-4 text-xs text-warn" data-testid="cap-reached">{{ t("curation.artworkImages.capReached", { max }) }}</p>
    <div v-else class="mt-4 space-y-2">
      <select v-model="newRights" class="w-full rounded-sm border border-line bg-surface px-1.5 py-1 text-xs text-ink" :aria-label="t('curation.profileForm.portraitRights')">
        <option v-for="r in RIGHTS" :key="r" :value="r">{{ t(`curation.profileForm.rights.${r}`) }}</option>
      </select>
      <label
        class="block cursor-pointer rounded-md border border-dashed px-3 py-3 text-center text-sm font-medium hover:bg-neutral-soft"
        :class="[busy ? 'opacity-50' : '', dragOver ? 'border-accent bg-accent-soft text-accent-strong' : 'border-ink text-ink']"
        data-testid="drop-zone"
        @dragover.prevent="dragOver = true"
        @dragleave.prevent="dragOver = false"
        @drop.prevent="onDrop"
      >
        {{ busy ? t("curation.artworkImages.uploading") : t("curation.artworkImages.upload") }}
        <span class="mt-0.5 block text-xs font-normal text-ink-muted">{{ t("curation.artworkImages.dropHint") }}</span>
        <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" data-testid="image-input" :disabled="busy" @change="onFile" />
      </label>
    </div>
  </div>
</template>
