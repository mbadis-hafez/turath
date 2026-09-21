<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { listRevisions, rollbackRevision } from "@/api/proposals";
import ErrorState from "@/components/common/ErrorState.vue";
import Spinner from "@/components/common/Spinner.vue";
import FieldDiffTable from "@/components/proposals/FieldDiffTable.vue";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type { RecordType, Revision } from "@/types/proposal";
import { formatDateTime } from "@/utils/format";
import type { AppLocale } from "@/i18n";

const props = defineProps<{ type: RecordType; recordId: number; managePermission: string }>();
const emit = defineEmits<{ rolledBack: [] }>();

const { t, locale } = useI18n();
const auth = useAuthStore();

const canRollback = computed(() => auth.can(props.managePermission));
const revisions = ref<Revision[]>([]);
const loading = ref(false);
const error = ref<unknown>(null);
const expanded = ref<string | null>(null);
const busyId = ref<string | null>(null);
const actionError = ref<string | null>(null);
/** Set when the API reports the rollback would drop a published record below its publish bar (D75). */
const confirmingId = ref<string | null>(null);
const blocking = ref<string[]>([]);
let controller: AbortController | null = null;

async function load(): Promise<void> {
  controller?.abort();
  const self = new AbortController();
  controller = self;
  loading.value = true;
  error.value = null;
  try {
    const response = await listRevisions(props.type, props.recordId, self.signal);
    if (controller === self) revisions.value = response.data;
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    if (controller === self) { revisions.value = []; error.value = err; }
  } finally {
    if (controller === self) loading.value = false;
  }
}
watch(() => [props.type, props.recordId], () => void load(), { immediate: true });
onBeforeUnmount(() => controller?.abort());

async function rollback(revision: Revision, confirmUnpublish: boolean): Promise<void> {
  busyId.value = revision.id;
  actionError.value = null;
  try {
    await rollbackRevision(props.type, props.recordId, revision.id, confirmUnpublish);
    confirmingId.value = null;
    blocking.value = [];
    await load();
    emit("rolledBack");
  } catch (err) {
    if (err instanceof ApiError && err.status === 409) {
      confirmingId.value = revision.id;
      blocking.value = (err.body as { blocking?: string[] } | null)?.blocking ?? [];
    } else {
      actionError.value = err instanceof Error ? err.message : t("errors.generic");
    }
  } finally {
    busyId.value = null;
  }
}

const rowsOf = (r: Revision) =>
  Object.entries(r.field_diffs).map(([field, d]) => ({ field, before: d.old, after: d.new }));
const isAudit = (r: Revision) => r.source === "audit";
const hasDiff = (r: Revision) => Object.keys(r.field_diffs).length > 0;
const CONTACT_PARTS = ["created", "updated", "deleted"] as const;
const contactsSummary = (r: Revision): string =>
  CONTACT_PARTS.filter((key) => (r.contacts_changed?.[key] ?? 0) > 0)
    .map((key) => t(`proposals.contactParts.${key}`, { n: r.contacts_changed?.[key] ?? 0 }))
    .join(" · ");
const SOURCE_CLASS: Record<string, string> = {
  direct_edit: "bg-neutral-soft text-ink-muted",
  approved_proposal: "bg-info-soft text-info",
  rollback: "bg-warn-soft text-warn",
  audit: "bg-sand text-sand-ink",
};
</script>

<template>
  <section>
    <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("proposals.history") }}</h2>
    <ErrorState v-if="error" :error="error" @retry="load" />
    <Spinner v-else-if="loading && revisions.length === 0" class="mx-auto my-8 block" />
    <p v-else-if="revisions.length === 0" class="py-4 text-sm text-ink-muted">{{ t("proposals.noHistory") }}</p>
    <ol v-else class="divide-y divide-line" data-testid="revision-history">
      <li v-for="r in revisions" :key="r.id" class="py-3" data-testid="revision">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex flex-wrap items-center gap-2 text-sm">
            <span v-if="!isAudit(r)" class="tabular-nums font-semibold text-ink" data-testid="revision-number">#{{ r.revision_number }}</span>
            <span v-else class="font-medium text-ink" data-testid="audit-subject">{{ r.subject_label ?? r.description }}</span>
            <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="SOURCE_CLASS[r.source]" data-testid="revision-source">{{ t(`proposals.sources.${r.source}`) }}</span>
            <span v-if="isAudit(r) && r.event" class="rounded-sm bg-line px-1.5 py-0.5 text-xs text-ink-muted" data-testid="audit-event">{{ t(`proposals.auditEvents.${r.event}`) }}</span>
            <span v-if="r.reverted_by_revision_id" class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs text-ink-muted" data-testid="reverted-badge">{{ t("proposals.reverted") }}</span>
            <span class="text-xs text-ink-muted">{{ r.applied_by?.name ?? "—" }} · {{ formatDateTime(r.applied_at, locale as AppLocale) }}</span>
          </div>
          <div class="flex items-center gap-3 text-xs">
            <button v-if="hasDiff(r)" type="button" class="font-medium text-accent-strong hover:underline" data-testid="toggle-diff" @click="expanded = expanded === r.id ? null : r.id">
              {{ expanded === r.id ? t("proposals.hideDiff") : t("proposals.viewDiff") }}
            </button>
            <button
              v-if="canRollback && !isAudit(r) && !r.reverted_by_revision_id"
              type="button"
              class="font-medium text-danger hover:underline disabled:opacity-50"
              :disabled="busyId === r.id"
              data-testid="rollback"
              @click="rollback(r, false)"
            >
              {{ t("proposals.rollback") }}
            </button>
          </div>
        </div>

        <p v-if="r.contacts_changed" class="mt-1 text-xs text-ink-muted" data-testid="contacts-summary">{{ contactsSummary(r) }}</p>

        <div v-if="confirmingId === r.id" class="mt-2 rounded-md border border-danger bg-danger-soft p-3 text-sm text-danger" role="alert" data-testid="unpublish-warning">
          <p class="font-semibold">{{ t("proposals.unpublishTitle") }}</p>
          <p class="mt-1 text-pretty">{{ t("proposals.unpublishBody", { fields: blocking.join("، ") }) }}</p>
          <div class="mt-2 flex gap-2">
            <button type="button" class="rounded-md bg-danger px-3 py-1.5 text-xs font-semibold text-surface" data-testid="confirm-unpublish" @click="rollback(r, true)">{{ t("proposals.unpublishConfirm") }}</button>
            <button type="button" class="text-xs text-ink-muted hover:text-ink" @click="confirmingId = null">{{ t("curation.merge.cancel") }}</button>
          </div>
        </div>

        <FieldDiffTable v-if="expanded === r.id" class="mt-2" :rows="rowsOf(r)" :labels="r.field_labels" />
      </li>
    </ol>
    <p v-if="actionError" class="mt-2 text-sm text-danger" role="alert">{{ actionError }}</p>
  </section>
</template>
