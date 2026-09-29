<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { bulkArchive } from "@/api/archive";
import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalized } from "@/composables/useLocalized";
import type { ArchiveEditLink } from "@/types/archive";
import type { Bilingual } from "@/types/artist";

interface DeletableItem {
  id: number;
  legacy_ref: string | null;
  title: Bilingual;
  links?: ArchiveEditLink[];
}

const props = defineProps<{
  open: boolean;
  item: DeletableItem;
}>();

const emit = defineEmits<{
  deleted: [];
  cancel: [];
  "hide-draft": [];
}>();

const { t } = useI18n();
const { pick } = useLocalized();

const busy = ref(false);
const error = ref<string | null>(null);
const confirmation = ref("");

const expected = computed(() => props.item.legacy_ref ?? String(props.item.id));
const canDelete = computed(() => confirmation.value === expected.value);
const links = computed(() => props.item.links ?? []);

watch(
  () => props.open,
  (open) => {
    if (open) {
      confirmation.value = "";
      error.value = null;
      busy.value = false;
    }
  },
);

async function confirmDelete(): Promise<void> {
  if (!canDelete.value) return;
  busy.value = true;
  error.value = null;
  try {
    const result = (await bulkArchive({ ids: [props.item.id], action: "delete" })).data;
    const failure = result.failed.find((f) => f.id === props.item.id);
    if (failure) {
      error.value = failure.message;
      return;
    }
    emit("deleted");
  } catch (err) {
    error.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    busy.value = false;
  }
}

function hideAsDraft(): void {
  emit("hide-draft");
}
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    :aria-labelledby="'delete-title-' + item.id"
    @keydown.esc="emit('cancel')"
  >
    <div class="w-full max-w-lg rounded-lg border border-line bg-surface p-6 shadow-lg">
      <h2 :id="'delete-title-' + item.id" class="text-balance text-xl font-semibold text-danger">{{ t("archive.deleteModal.title") }}</h2>

      <p class="mt-2 text-pretty text-sm text-ink-muted">
        {{ t("archive.deleteModal.explanation", { title: pick(item.title)?.text ?? "" }) }}
      </p>

      <div v-if="links.length" class="mt-4 rounded-md border border-warn bg-warn-soft p-3 text-sm">
        <p class="font-medium text-ink">{{ t("archive.deleteModal.linkedRecords") }}</p>
        <ul class="mt-1 list-disc ps-5 text-ink-muted">
          <li v-for="link in links" :key="`${link.kind}-${link.entity_id}`">
            <span class="text-ink-muted">{{ t(`archive.edit.linkRoles.${link.role}`) }} · {{ t(`archive.edit.linkKinds.${link.kind}`) }}</span>
            <LocalizedText :text="link.label" />
          </li>
        </ul>
      </div>

      <label class="mt-5 block text-sm text-ink-muted">
        {{ t("archive.deleteModal.typeToConfirm", { ref: expected }) }}
        <input
          v-model="confirmation"
          type="text"
          dir="ltr"
          autocomplete="off"
          class="mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none"
          data-testid="delete-confirm-input"
          @keydown.enter.prevent="confirmDelete"
        />
      </label>

      <p v-if="error" class="mt-4 text-sm text-danger" role="alert" data-testid="delete-error">{{ error }}</p>

      <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <button
          type="button"
          class="text-start text-sm text-ink-muted underline hover:text-ink"
          data-testid="hide-draft-button"
          @click="hideAsDraft"
        >
          {{ t("archive.deleteModal.hideDraftLabel") }}
        </button>
        <div class="flex flex-wrap justify-end gap-2">
          <button
            type="button"
            class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft"
            data-testid="delete-cancel-button"
            @click="emit('cancel')"
          >
            {{ t("archive.deleteModal.cancelLabel") }}
          </button>
          <button
            type="button"
            class="rounded-md bg-danger px-4 py-2 text-sm font-semibold text-surface hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="!canDelete || busy"
            data-testid="delete-confirm-button"
            @click="confirmDelete"
          >
            {{ busy ? t("common.loading") : t("archive.deleteModal.confirmLabel") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
