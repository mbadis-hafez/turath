<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useLocalStorage } from "@vueuse/core";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { bulkArtworks, createArtwork, deleteArtwork, getArtworkCuration } from "@/api/artworkCuration";
import ConfirmDialog from "@/components/common/ConfirmDialog.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import ArtworkGridCard from "@/components/curation/ArtworkGridCard.vue";
import ArtworkListRow from "@/components/curation/ArtworkListRow.vue";
import ArtworkMergeModal from "@/components/curation/ArtworkMergeModal.vue";
import EntityPicker, { type PickerOption } from "@/components/curation/EntityPicker.vue";
import { searchArtistOptions, searchHolderOptions } from "@/components/curation/ArtworkPickers";
import ViewModeToggle, { type ArtworkViewMode } from "@/components/curation/ViewModeToggle.vue";
import { useAdminArtworks } from "@/composables/useAdminArtworks";
import { useArtworkForm } from "@/composables/useArtworkForm";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import { ADMIN_ARTWORK_SORTS, type AdminArtworkRow, type AdminArtworkSort, type ArtworkBulkResult, type ArtworkStatus } from "@/types/artworkCuration";

const { t } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();
const router = useRouter();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("artworks.manage"));

const {
  items, meta, loading, error, query, searchInput, retry,
  setSearch, setArtist, setHolder, setStatus, setMissingDimensions, setPipelineGap, setSort, setPage, clear,
} = useAdminArtworks();

const merging = ref(false);
const labels = ref<Record<string, string>>({});

const storedView = useLocalStorage<ArtworkViewMode>("admin-artworks-view", "grid");
const viewMode = computed<ArtworkViewMode>({
  get: () => (storedView.value === "list" ? "list" : "grid"),
  set: (value) => {
    storedView.value = value;
  },
});

function pickerModel(kind: "artist" | "holder") {
  return computed<PickerOption | null>({
    get: () => {
      const id = kind === "artist" ? query.value.artistId : query.value.holderId;
      return id ? { id, label: labels.value[`${kind}${id}`] ?? `#${id}` } : null;
    },
    set: (v) => {
      if (v) labels.value[`${kind}${v.id}`] = v.label;
      (kind === "artist" ? setArtist : setHolder)(v?.id ?? null);
    },
  });
}
const artistFilter = pickerModel("artist");
const holderFilter = pickerModel("holder");

function onMerged(): void {
  merging.value = false;
  void retry();
}

const STATUSES: ArtworkStatus[] = ["draft", "published", "hidden"];

const hasFilters = computed(() => Boolean(
  query.value.q || query.value.status || query.value.missingDimensions
  || query.value.pipelineGap || query.value.artistId || query.value.holderId,
));
const toggleClass = (on: boolean) => (on ? "border-danger bg-danger-soft text-danger" : "border-line bg-surface text-ink");
const field = "rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";

// ---- selection & bulk actions
const selected = ref<Set<number>>(new Set());
watch(items, () => (selected.value = new Set()));
const allSelected = computed(() => items.value.length > 0 && items.value.every((i) => selected.value.has(i.id)));
const someSelected = computed(() => selected.value.size > 0 && !allSelected.value);

function toggleOne(id: number): void {
  const next = new Set(selected.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selected.value = next;
}
function toggleAll(): void {
  selected.value = allSelected.value ? new Set() : new Set(items.value.map((i) => i.id));
}

const bulkBusy = ref(false);
const bulkResult = ref<ArtworkBulkResult | null>(null);
const bulkError = ref<string | null>(null);

async function changeSelectedStatus(status: ArtworkStatus): Promise<void> {
  bulkBusy.value = true;
  bulkError.value = null;
  bulkResult.value = null;
  try {
    bulkResult.value = (await bulkArtworks({ ids: [...selected.value], action: "set_status", status })).data;
    await retry();
  } catch (err) {
    bulkError.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    bulkBusy.value = false;
  }
}

function csvEscape(v: string | number): string {
  const s = String(v);
  return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
}
function exportSelected(): void {
  const rows = items.value.filter((i) => selected.value.has(i.id));
  const header = ["id", "code", "title_ar", "title_en", "artist", "year", "status"];
  const body = rows.map((r) => [
    r.id, r.legacy_ref ?? "", r.title.ar ?? "", r.title.en ?? "", r.artist ? pick(r.artist.name)?.text ?? "" : "", r.year ?? "", r.publication_status,
  ]);
  const csv = [header, ...body].map((row) => row.map(csvEscape).join(",")).join("\n");
  const url = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8;" }));
  const a = document.createElement("a");
  a.href = url;
  a.download = "artworks-export.csv";
  a.click();
  URL.revokeObjectURL(url);
}

// ---- delete (single row or bulk share one confirm dialog)
// deleteDialogOpen is a plain ref, independent of deletingRow/bulkDeleteTarget:
// ConfirmDialog's Action auto-closes the dialog on click (emitting its own
// "cancel" via update:open) before — or interleaved with — our @confirm
// handler, so clearing the pending-delete target from that cancel path would
// race confirmDelete's read of it. Only confirmDelete itself clears the target.
const deletingRow = ref<AdminArtworkRow | null>(null);
const bulkDeleteTarget = ref(false);
const deleteDialogOpen = ref(false);
const deleteBusy = ref(false);
const deleteError = ref<string | null>(null);
const deleteDialogTitle = computed(() =>
  bulkDeleteTarget.value ? t("curation.artworkRegistry.bulk.confirmDelete", { count: selected.value.size }) : t("curation.artworkRegistry.deleteTitle"));
const deleteDialogBody = computed(() =>
  deletingRow.value ? t("curation.artworkRegistry.deleteBody", { title: pick(deletingRow.value.title)?.text ?? "" }) : "");

function openRowDelete(row: AdminArtworkRow): void {
  deletingRow.value = row;
  bulkDeleteTarget.value = false;
  deleteError.value = null;
  deleteDialogOpen.value = true;
}
function openBulkDelete(): void {
  bulkDeleteTarget.value = true;
  deleteError.value = null;
  deleteDialogOpen.value = true;
}
function cancelDelete(): void {
  deleteDialogOpen.value = false;
  deleteError.value = null;
}
async function confirmDelete(): Promise<void> {
  deleteBusy.value = true;
  deleteError.value = null;
  deleteDialogOpen.value = true;
  try {
    if (bulkDeleteTarget.value) {
      bulkResult.value = (await bulkArtworks({ ids: [...selected.value], action: "delete" })).data;
      selected.value = new Set();
    } else if (deletingRow.value) {
      await deleteArtwork(deletingRow.value.id);
    }
    deleteDialogOpen.value = false;
    deletingRow.value = null;
    await retry();
  } catch (err) {
    deleteError.value = err instanceof Error && err.message ? err.message : t("curation.artworkRegistry.deleteError");
  } finally {
    deleteBusy.value = false;
  }
}

// ---- duplicate: clones the record's curation fields into a new draft
const duplicatingId = ref<number | null>(null);
const duplicateError = ref<string | null>(null);
async function duplicateArtwork(row: AdminArtworkRow): Promise<void> {
  if (duplicatingId.value !== null) return;
  duplicatingId.value = row.id;
  duplicateError.value = null;
  try {
    const { data: curation } = await getArtworkCuration(row.id);
    const { load, payload } = useArtworkForm();
    load(curation);
    const body = payload("create");
    delete body.legacy_ref; // avoid colliding with the source record's code
    const { data } = await createArtwork(body);
    await router.push(localePath("admin.artworks.show", { id: data.id }));
  } catch (err) {
    duplicateError.value = err instanceof Error ? err.message : t("curation.artworkRegistry.duplicateError");
  } finally {
    duplicatingId.value = null;
  }
}
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("curation.artworkRegistry.title") }}</h1>
          <p v-if="meta" class="mt-1 text-sm text-ink-muted">
            {{ t("curation.artworkRegistry.subtitle", { count: meta.total }) }}
            <template v-if="meta.candidate_count > 0"> · {{ t("curation.artworkRegistry.candidates", { count: meta.candidate_count }) }}</template>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <ViewModeToggle v-model="viewMode" />
          <button type="button" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft" data-testid="merge-open" @click="merging = true">{{ t("curation.artworkRegistry.merge") }}</button>
          <RouterLink :to="localePath('admin.artworks.new')" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper hover:bg-ink/85" data-testid="add-artwork">{{ t("curation.artworkRegistry.add") }}</RouterLink>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-2">
        <input
          :value="searchInput"
          type="search"
          :placeholder="t('curation.artworkRegistry.searchPlaceholder')"
          :class="[field, 'min-w-64 flex-1']"
          @input="setSearch(($event.target as HTMLInputElement).value)"
        />
        <select :value="query.status" :class="field" :aria-label="t('curation.artworkRegistry.status')" @change="setStatus(($event.target as HTMLSelectElement).value as ArtworkStatus | '')">
          <option value="">{{ t("curation.artworkRegistry.allStatuses") }}</option>
          <option v-for="s in STATUSES" :key="s" :value="s">{{ t(`curation.artworkRegistry.statuses.${s}`) }}</option>
        </select>
        <button type="button" class="rounded-md border px-3 py-2 text-sm font-medium" :class="toggleClass(query.missingDimensions)" :aria-pressed="query.missingDimensions" @click="setMissingDimensions(!query.missingDimensions)">
          {{ t("curation.artworkRegistry.missingDimensions") }}
        </button>
        <div class="w-48"><EntityPicker v-model="artistFilter" :search="searchArtistOptions" :placeholder="t('curation.artworkRegistry.artist')" /></div>
        <div class="w-48"><EntityPicker v-model="holderFilter" :search="searchHolderOptions" :placeholder="t('curation.artworkRegistry.holder')" /></div>
        <button type="button" class="rounded-md border px-3 py-2 text-sm font-medium" :class="toggleClass(query.pipelineGap)" :aria-pressed="query.pipelineGap" @click="setPipelineGap(!query.pipelineGap)">
          {{ t("curation.artworkRegistry.pipelineGap") }}
        </button>
        <select :value="query.sort" :class="field" :aria-label="t('curation.artworkRegistry.sort.label')" data-testid="sort-select" @change="setSort(($event.target as HTMLSelectElement).value as AdminArtworkSort)">
          <option v-for="s in ADMIN_ARTWORK_SORTS" :key="s" :value="s">{{ t(`curation.artworkRegistry.sort.${s}`) }}</option>
        </select>
      </div>

      <div class="mt-4 flex min-h-11 flex-wrap items-center gap-3 border-t border-line pt-4" data-testid="bulk-bar-row">
        <label v-if="items.length > 0" class="flex items-center gap-2 text-sm text-ink">
          <input type="checkbox" class="size-4 accent-ink" :checked="allSelected" :indeterminate="someSelected" :aria-label="t('curation.artworkRegistry.selectAll')" data-testid="select-all" @change="toggleAll" />
        </label>
        <div v-if="selected.size > 0" class="flex flex-wrap items-center gap-2" data-testid="bulk-bar">
          <span class="text-sm text-ink">{{ t("curation.artworkRegistry.selected", { count: selected.size }) }}</span>
          <select class="rounded-md border border-ink px-3 py-1.5 text-sm text-ink" :aria-label="t('curation.artworkRegistry.bulk.changeStatus')" :disabled="bulkBusy" data-testid="bulk-status" @change="(e) => { const v = (e.target as HTMLSelectElement).value; (e.target as HTMLSelectElement).value = ''; if (v) changeSelectedStatus(v as ArtworkStatus); }">
            <option value="">{{ t("curation.artworkRegistry.bulk.changeStatus") }}</option>
            <option v-for="s in STATUSES" :key="s" :value="s">{{ t(`curation.artworkRegistry.statuses.${s}`) }}</option>
          </select>
          <button type="button" class="rounded-md border border-line px-3 py-1.5 text-sm text-ink-muted" :title="t('curation.artworkRegistry.bulk.linkExhibitionUnavailable')" disabled data-testid="bulk-link-exhibition">
            {{ t("curation.artworkRegistry.bulk.linkExhibition") }}
          </button>
          <button type="button" class="rounded-md border border-ink px-3 py-1.5 text-sm text-ink hover:bg-neutral-soft" data-testid="bulk-export" @click="exportSelected">{{ t("curation.artworkRegistry.bulk.export") }}</button>
          <button type="button" class="rounded-md border border-danger px-3 py-1.5 text-sm text-danger hover:bg-danger-soft" :disabled="bulkBusy" data-testid="bulk-delete" @click="openBulkDelete">{{ t("curation.artworkRegistry.bulk.delete") }}</button>
          <button type="button" class="text-sm text-ink-muted hover:text-ink" data-testid="bulk-clear" @click="selected = new Set()">{{ t("curation.artworkRegistry.clearSelection") }}</button>
        </div>
      </div>
      <div v-if="bulkResult || bulkError || duplicateError" class="mt-2 text-sm" role="status">
        <p v-if="bulkError" class="text-danger">{{ bulkError }}</p>
        <p v-else-if="bulkResult" class="text-ink">{{ t("curation.artworkRegistry.bulk.done", { ok: bulkResult.succeeded.length, failed: bulkResult.failed.length }) }}</p>
        <p v-if="duplicateError" class="text-danger">{{ duplicateError }}</p>
      </div>

      <div class="mt-4">
        <ErrorState v-if="error" :error="error" @retry="retry" />
        <Spinner v-else-if="loading && items.length === 0" class="mx-auto my-12 block" />
        <EmptyState v-else-if="items.length === 0" :title="t('curation.artworkRegistry.empty')" :description="t('curation.artworkRegistry.emptyHelp')">
          <button v-if="hasFilters" type="button" class="mt-4 rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong" @click="clear">
            {{ t("curation.registry.clear") }}
          </button>
        </EmptyState>
        <template v-else>
          <ul v-if="viewMode === 'grid'" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <li v-for="a in items" :key="a.id">
              <ArtworkGridCard
                :artwork="a" :selected="selected.has(a.id)"
                @toggle="toggleOne" @duplicate="duplicateArtwork"
                @delete="openRowDelete"
              />
            </li>
          </ul>
          <div v-else class="overflow-x-auto border-t-2 border-ink">
            <table class="w-full text-start text-sm">
              <thead class="border-b border-ink text-xs text-ink-muted">
                <tr>
                  <th class="w-10 px-3 py-3"></th>
                  <th class="px-3 py-3"></th>
                  <th class="px-3 py-3 text-start font-medium">{{ t("curation.artworkRegistry.columns.title") }}</th>
                  <th class="px-3 py-3 text-start font-medium">{{ t("curation.artworkRegistry.columns.artist") }}</th>
                  <th class="px-3 py-3 text-start font-medium">{{ t("curation.artworkRegistry.columns.yearMedium") }}</th>
                  <th class="px-3 py-3 text-start font-medium">{{ t("curation.artworkRegistry.columns.owner") }}</th>
                  <th class="px-3 py-3 text-start font-medium">{{ t("curation.artworkRegistry.columns.images") }}</th>
                  <th class="px-3 py-3 text-start font-medium">{{ t("curation.artworkRegistry.columns.status") }}</th>
                  <th class="px-3 py-3 text-start font-medium">{{ t("curation.artworkRegistry.columns.actions") }}</th>
                </tr>
              </thead>
              <tbody>
                <ArtworkListRow
                  v-for="a in items" :key="a.id" :artwork="a" :selected="selected.has(a.id)"
                  @toggle="toggleOne" @duplicate="duplicateArtwork"
                  @delete="openRowDelete"
                />
              </tbody>
            </table>
          </div>
          <Pagination class="mt-6" :meta="meta" @change="setPage" />
        </template>
      </div>
      <ArtworkMergeModal v-if="merging" @close="merging = false" @merged="onMerged" />

      <ConfirmDialog
        :open="deleteDialogOpen"
        :title="deleteDialogTitle"
        :description="deleteDialogBody"
        :confirm-label="t('curation.artworkRegistry.actions.delete')"
        :cancel-label="t('draft.discardCancel')"
        :busy="deleteBusy"
        :error="deleteError"
        @confirm="confirmDelete"
        @cancel="cancelDelete"
      />
    </template>
  </section>
</template>
