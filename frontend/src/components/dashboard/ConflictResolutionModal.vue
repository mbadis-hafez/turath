<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";

import { getRecordCompleteness, resolveSourceConflict } from "@/api/dashboard";
import { useLocalized } from "@/composables/useLocalized";
import type { DashboardRecord, OpenConflict } from "@/types/completeness";

const props = defineProps<{
  record: DashboardRecord;
}>();

const emit = defineEmits<{
  close: [];
  resolved: [];
}>();

const { t, te } = useI18n();
const { pick } = useLocalized();

const conflicts = ref<OpenConflict[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const submitting = ref(false);
/** conflict id → chosen source id */
const choice = ref<Record<string, string>>({});
const note = ref<Record<string, string>>({});

onMounted(async () => {
  try {
    const response = await getRecordCompleteness(props.record.entity_type, props.record.id);
    conflicts.value = response.data.open_conflicts;
  } catch (err) {
    error.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    loading.value = false;
  }
});

function fieldName(conflict: OpenConflict): string {
  return te(`dashboard.field.${conflict.field_key}`)
    ? t(`dashboard.field.${conflict.field_key}`)
    : (pick(conflict.label)?.text ?? conflict.field_key);
}

function display(value: unknown): string {
  return typeof value === "object" ? JSON.stringify(value) : String(value);
}

async function submit(): Promise<void> {
  submitting.value = true;
  error.value = null;
  try {
    for (const conflict of conflicts.value) {
      const sourceId = choice.value[conflict.id];
      if (!sourceId) continue;
      await resolveSourceConflict(conflict.id, {
        resolved_source_id: sourceId,
        resolution_note: note.value[conflict.id] || undefined,
      });
    }
    emit("resolved");
  } catch (err) {
    error.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="conflict-title"
    @keydown.esc="emit('close')"
  >
    <form
      class="max-h-[90dvh] w-full max-w-xl overflow-y-auto rounded-lg border border-line bg-surface p-6"
      @submit.prevent="submit"
    >
      <h2 id="conflict-title" class="text-lg font-semibold text-ink">
        {{ t("dashboard.conflict.title") }}
      </h2>
      <p class="mt-1 text-sm text-ink-muted">{{ t("dashboard.conflict.help") }}</p>

      <p v-if="loading" class="mt-4 text-sm text-ink-muted">{{ t("dashboard.conflict.loading") }}</p>

      <fieldset v-for="conflict in conflicts" :key="conflict.id" class="mt-5 space-y-2">
        <legend class="text-sm font-medium text-ink">{{ fieldName(conflict) }}</legend>
        <label
          v-for="citation in conflict.citations"
          :key="citation.id"
          class="flex cursor-pointer items-start gap-2 rounded-md border border-line p-2 text-sm"
        >
          <input
            v-model="choice[conflict.id]"
            type="radio"
            class="mt-1"
            :name="`conflict-${conflict.id}`"
            :value="citation.source?.id"
            :disabled="!citation.source"
          />
          <span>
            <span class="font-medium text-ink">
              {{ (citation.source && pick(citation.source.title)?.text) || t("dashboard.conflict.noSource") }}
            </span>
            <span v-if="citation.source?.reference_note" class="text-ink-muted"> · {{ citation.source.reference_note }}</span>
            <span class="block text-ink-muted">
              {{ t("dashboard.conflict.claims") }} <bdi class="tabular-nums text-ink">{{ display(citation.claimed_value) }}</bdi>
            </span>
          </span>
        </label>
        <label class="block">
          <span class="text-xs text-ink-muted">{{ t("dashboard.conflict.note") }}</span>
          <input
            v-model="note[conflict.id]"
            type="text"
            class="mt-1 w-full rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
          />
        </label>
      </fieldset>

      <p v-if="error" class="mt-3 text-sm text-danger">{{ error }}</p>

      <div class="mt-6 flex justify-end gap-2">
        <button
          type="button"
          class="rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink hover:bg-neutral-soft"
          @click="emit('close')"
        >
          {{ t("dashboard.conflict.cancel") }}
        </button>
        <button
          type="submit"
          class="rounded-md bg-accent px-4 py-1.5 text-sm font-medium text-surface hover:bg-accent-strong disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="submitting || conflicts.every((c) => !choice[c.id])"
        >
          {{ t("dashboard.conflict.submit") }}
        </button>
      </div>
    </form>
  </div>
</template>
