<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import { bulkArchive } from "@/api/archive";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import ArchiveTypeIcon from "@/components/curation/ArchiveTypeIcon.vue";
import { searchArtistOptions } from "@/components/curation/ArtworkPickers";
import EntityPicker, { type PickerOption } from "@/components/curation/EntityPicker.vue";
import { RIGHTS, ROW_STATUSES, useAdminArchive } from "@/composables/useAdminArchive";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import { ARCHIVE_ITEM_TYPES, type AdminArchiveRow, type ArchiveItemType, type ArchiveRowStatus, type BulkResult, type RightsStatus } from "@/types/archive";

const { t } = useI18n();
const { pick } = useLocalized();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("archive.manage"));

const {
  items, meta, loading, error, query, searchInput, retry,
  setSearch, setType, setStatus, setRights, setYears, setMine, setPage, clear,
} = useAdminArchive();

const field = "rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
const hasFilters = computed(() => Object.values(query.value).some((v) => v && v !== 1));

// ---- selection & bulk actions
const selected = ref<Set<number>>(new Set());
watch(items, () => (selected.value = new Set()));
const allSelected = computed(() => items.value.length > 0 && items.value.every((i) => selected.value.has(i.id)));
const someSelected = computed(() => selected.value.size > 0 && !allSelected.value);

function toggle(id: number): void {
  const next = new Set(selected.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selected.value = next;
}
function toggleAll(): void {
  selected.value = allSelected.value ? new Set() : new Set(items.value.map((i) => i.id));
}

const bulkBusy = ref(false);
const bulkResult = ref<BulkResult | null>(null);
const bulkError = ref<string | null>(null);
const assigning = ref(false);
const assignArtist = ref<PickerOption | null>(null);
const confirmingDelete = ref(false);

async function runBulk(payload: Omit<Parameters<typeof bulkArchive>[0], "ids">): Promise<void> {
  bulkBusy.value = true;
  bulkError.value = null;
  bulkResult.value = null;
  try {
    bulkResult.value = (await bulkArchive({ ids: [...selected.value], ...payload })).data;
    assigning.value = false;
    assignArtist.value = null;
    confirmingDelete.value = false;
    await retry();
  } catch (err) {
    bulkError.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    bulkBusy.value = false;
  }
}

const rowStatus = (r: AdminArchiveRow): ArchiveRowStatus =>
  r.under_review ? "under_review" : r.publication_status === "published" ? "published" : r.incomplete ? "incomplete" : r.publication_status;
const STATUS_CLASS: Record<ArchiveRowStatus, string> = {
  incomplete: "bg-danger-soft text-danger", published: "bg-info-soft text-info", under_review: "bg-warn-soft text-warn",
  draft: "border border-line bg-surface text-ink-muted", hidden: "bg-neutral-soft text-ink-muted",
};

function applyYears(from: string, to: string): void {
  const n = (v: string) => (Number.parseInt(v, 10) > 0 ? Number.parseInt(v, 10) : null);
  setYears(n(from), n(to));
}
const yearFromInput = ref(query.value.yearFrom ? String(query.value.yearFrom) : "");
const yearToInput = ref(query.value.yearTo ? String(query.value.yearTo) : "");
watch(query, (q) => {
  yearFromInput.value = q.yearFrom ? String(q.yearFrom) : "";
  yearToInput.value = q.yearTo ? String(q.yearTo) : "";
});
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("archive.admin.title") }}</h1>
          <p v-if="meta" class="mt-1 text-sm tabular-nums text-ink-muted" data-testid="summary">
            {{ t("archive.admin.summary", { total: meta.total_all, mine: meta.mine_count, incomplete: meta.incomplete_count }) }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <RouterLink :to="localePath('admin.imports')" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft">{{ t("archive.admin.uploadBatch") }}</RouterLink>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-2">
        <input :value="searchInput" type="search" :placeholder="t('archive.admin.searchPlaceholder')" :class="[field, 'min-w-64 flex-1']" @input="setSearch(($event.target as HTMLInputElement).value)" />
        <select :value="query.type" :class="field" :aria-label="t('archive.type')" @change="setType(($event.target as HTMLSelectElement).value as ArchiveItemType | '')">
          <option value="">{{ t("archive.allTypes") }}</option>
          <option v-for="ty in ARCHIVE_ITEM_TYPES" :key="ty" :value="ty">{{ t(`archive.types.${ty}`) }}</option>
        </select>
        <select :value="query.status" :class="field" :aria-label="t('archive.admin.status')" @change="setStatus(($event.target as HTMLSelectElement).value as ArchiveRowStatus | '')">
          <option value="">{{ t("archive.admin.allStatuses") }}</option>
          <option v-for="s in ROW_STATUSES" :key="s" :value="s">{{ t(`archive.admin.rowStatuses.${s}`) }}</option>
        </select>
        <select :value="query.rights" :class="field" :aria-label="t('archive.admin.rights')" @change="setRights(($event.target as HTMLSelectElement).value as RightsStatus | '')">
          <option value="">{{ t("archive.admin.allRights") }}</option>
          <option v-for="r in RIGHTS" :key="r" :value="r">{{ t(`archive.admin.rightsOptions.${r}`) }}</option>
        </select>
        <span class="flex items-center gap-1" role="group" :aria-label="t('archive.admin.period')">
          <input v-model="yearFromInput" type="number" min="1000" max="2100" inputmode="numeric" :placeholder="t('archive.admin.yearFrom')" :class="[field, 'w-24']" data-testid="year-from" @change="applyYears(yearFromInput, yearToInput)" />
          <span class="text-ink-muted" aria-hidden="true">–</span>
          <input v-model="yearToInput" type="number" min="1000" max="2100" inputmode="numeric" :placeholder="t('archive.admin.yearTo')" :class="[field, 'w-24']" @change="applyYears(yearFromInput, yearToInput)" />
        </span>
        <button type="button" class="rounded-md border px-3 py-2 text-sm font-medium" :class="query.mine ? 'border-ink bg-ink text-paper' : 'border-line bg-surface text-ink'" :aria-pressed="query.mine" data-testid="mine-toggle" @click="setMine(!query.mine)">
          {{ t("archive.admin.mine") }}
        </button>
      </div>

      <div class="mt-4 flex min-h-11 flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
        <p v-if="selected.size === 0" class="text-xs tabular-nums text-ink-muted">
          <template v-if="meta">{{ t("archive.admin.showing", { from: (meta.current_page - 1) * meta.per_page + (items.length ? 1 : 0), to: (meta.current_page - 1) * meta.per_page + items.length, total: meta.total }) }}</template>
        </p>
        <div v-else class="flex flex-wrap items-center gap-2" data-testid="bulk-bar">
          <span class="text-sm text-ink">{{ t("archive.admin.selected", { count: selected.size }) }}</span>
          <select class="rounded-md border border-ink px-3 py-1.5 text-sm text-ink" :aria-label="t('archive.admin.changeStatus')" :disabled="bulkBusy" data-testid="bulk-status" @change="(e) => { const v = (e.target as HTMLSelectElement).value; (e.target as HTMLSelectElement).value = ''; if (v) runBulk({ action: 'set_status', status: v as 'draft' | 'published' | 'hidden' }); }">
            <option value="">{{ t("archive.admin.changeStatus") }}</option>
            <option v-for="s in ['draft', 'published', 'hidden']" :key="s" :value="s">{{ t(`archive.statuses.${s}`) }}</option>
          </select>
          <button type="button" class="rounded-md border border-ink px-3 py-1.5 text-sm text-ink hover:bg-neutral-soft" :disabled="bulkBusy" data-testid="bulk-assign" @click="assigning = !assigning">{{ t("archive.admin.assignArtist") }}</button>
          <button v-if="!confirmingDelete" type="button" class="rounded-md border border-danger px-3 py-1.5 text-sm text-danger hover:bg-danger-soft" :disabled="bulkBusy" data-testid="bulk-delete" @click="confirmingDelete = true">{{ t("archive.admin.delete") }}</button>
          <span v-else class="flex items-center gap-2 text-sm">
            <span class="text-danger">{{ t("archive.admin.confirmDelete", { count: selected.size }) }}</span>
            <button type="button" class="rounded-md bg-danger px-3 py-1.5 text-sm text-surface" :disabled="bulkBusy" data-testid="bulk-delete-confirm" @click="runBulk({ action: 'delete' })">{{ t("archive.admin.delete") }}</button>
            <button type="button" class="text-ink-muted hover:text-ink" @click="confirmingDelete = false">{{ t("curation.merge.cancel") }}</button>
          </span>
        </div>
        <div v-if="assigning && selected.size > 0" class="flex w-full items-center gap-2" data-testid="assign-panel">
          <div class="w-64"><EntityPicker v-model="assignArtist" :search="searchArtistOptions" :placeholder="t('curation.artworkDetail.searchArtist')" /></div>
          <button type="button" class="mt-1 rounded-md bg-ink px-3 py-1.5 text-sm text-paper disabled:opacity-50" :disabled="!assignArtist || bulkBusy" data-testid="assign-apply" @click="runBulk({ action: 'link_artist', artist_id: assignArtist!.id })">{{ t("archive.admin.apply") }}</button>
        </div>
      </div>
      <div v-if="bulkResult || bulkError" class="mt-2 text-sm" role="status">
        <p v-if="bulkError" class="text-danger">{{ bulkError }}</p>
        <template v-else-if="bulkResult">
          <p class="text-ink">{{ t("archive.admin.bulkDone", { ok: bulkResult.succeeded.length, failed: bulkResult.failed.length }) }}</p>
          <ul v-if="bulkResult.failed.length" class="mt-1 list-disc ps-5 text-danger" data-testid="bulk-failures">
            <li v-for="f in bulkResult.failed" :key="f.id">#{{ f.id }} — {{ f.message }}</li>
          </ul>
        </template>
      </div>

      <div class="mt-4">
        <ErrorState v-if="error" :error="error" @retry="retry" />
        <Spinner v-else-if="loading && items.length === 0" class="mx-auto my-12 block" />
        <EmptyState v-else-if="items.length === 0" :title="t('archive.empty')" :description="t('archive.emptyHelp')">
          <button v-if="hasFilters" type="button" class="mt-4 rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong" @click="clear">{{ t("archive.clear") }}</button>
        </EmptyState>
        <template v-else>
          <div class="overflow-x-auto border-t-2 border-ink">
            <table class="w-full text-start text-sm">
              <thead class="border-b border-ink text-xs text-ink-muted">
                <tr>
                  <th class="w-10 px-3 py-3"><input type="checkbox" class="size-4 accent-ink" :checked="allSelected" :indeterminate="someSelected" :aria-label="t('archive.admin.selectAll')" data-testid="select-all" @change="toggleAll" /></th>
                  <th class="px-3 py-3 text-start font-medium">{{ t("archive.admin.columns.title") }}</th>
                  <th class="px-3 py-3 text-start font-medium">{{ t("archive.admin.columns.date") }}</th>
                  <th class="px-3 py-3 text-start font-medium">{{ t("archive.admin.columns.source") }}</th>
                  <th class="px-3 py-3 text-start font-medium">{{ t("archive.admin.columns.status") }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="i in items" :key="i.id" class="border-b border-line align-middle hover:bg-neutral-soft/50" data-testid="archive-row">
                  <td class="px-3 py-4"><input type="checkbox" class="size-4 accent-ink" :checked="selected.has(i.id)" :aria-label="pick(i.title)?.text" data-testid="row-select" @change="toggle(i.id)" /></td>
                  <td class="px-3 py-4">
                    <div class="flex items-center gap-4">
                      <span class="flex size-14 shrink-0 items-center justify-center rounded-sm bg-neutral-soft text-ink-muted"><ArchiveTypeIcon :type="i.item_type" /></span>
                      <div class="min-w-0">
                        <p class="text-base font-semibold text-ink"><LocalizedText :text="i.title" /></p>
                        <p class="text-xs text-ink-muted"><bdi dir="ltr">{{ i.legacy_ref }}</bdi> · {{ t(`archive.types.${i.item_type}`) }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-3 py-4 tabular-nums text-ink-muted">{{ i.date ?? "—" }}</td>
                  <td class="px-3 py-4 text-ink-muted">
                    <LocalizedText v-if="i.source.name.ar || i.source.name.en" :text="i.source.name" /><template v-else>—</template>
                    · {{ t(`archive.admin.rightsOptions.${i.source.rights_status}`) }}
                  </td>
                  <td class="px-3 py-4">
                    <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="STATUS_CLASS[rowStatus(i)]" data-testid="row-status">{{ t(`archive.admin.rowStatuses.${rowStatus(i)}`) }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <Pagination :meta="meta" @change="setPage" />
            <p v-if="meta" class="text-xs text-ink-muted">{{ t("archive.admin.perPage", { count: meta.per_page }) }}</p>
          </div>
        </template>
      </div>
    </template>
  </section>
</template>
