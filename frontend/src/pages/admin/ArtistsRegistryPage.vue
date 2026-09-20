<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";

import { listThemes } from "@/api/artistCuration";
import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import MergeToolModal from "@/components/curation/MergeToolModal.vue";
import { useAdminArtists } from "@/composables/useAdminArtists";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type { OwnerType, Theme } from "@/types/artistCuration";
import { SEVERITY_BADGE_CLASS } from "@/utils/severity";

const { t } = useI18n();
const { localePath } = useLocalePath();
const { pick } = useLocalized();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("artists.manage"));

const {
  items, meta, loading, error, query, searchInput, cityInput, retry,
  setSearch, setCity, setUnverified, setOwnerType, setTheme, setPriority, setPage, clear,
} = useAdminArtists();

const OWNER_TYPES: OwnerType[] = ["artist", "heir_or_estate", "gallery", "institution", "other"];
const themes = ref<Theme[]>([]);
const merging = ref(false);

const STATUS_CLASS = {
  unverified: "bg-danger-soft text-danger",
  verified: "bg-success-soft text-success",
  disputed: "bg-warn-soft text-warn",
} as const;

const hasFilters = computed(() => Object.values(query.value).some((v) => v && v !== 1));

onMounted(async () => {
  try {
    themes.value = (await listThemes()).data;
  } catch {
    themes.value = [];
  }
});

function onMerged(): void {
  merging.value = false;
  void retry();
}

const toggle = (on: boolean) =>
  on ? "border-danger bg-danger-soft text-danger" : "border-line bg-surface text-ink";
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("curation.registry.title") }}</h1>
          <p v-if="meta" class="mt-1 text-sm text-ink-muted">{{ t("curation.registry.subtitle", { count: meta.total }) }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft" @click="merging = true">
            {{ t("curation.registry.mergeTool") }}
          </button>
          <RouterLink :to="localePath('admin.artists.new')" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper hover:bg-ink/85" data-testid="add-artist">
            {{ t("curation.registry.addArtist") }}
          </RouterLink>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-2">
        <input
          :value="searchInput"
          type="search"
          :placeholder="t('curation.registry.searchPlaceholder')"
          class="min-w-64 flex-1 rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none"
          @input="setSearch(($event.target as HTMLInputElement).value)"
        />
        <button type="button" class="rounded-md border px-3 py-2 text-sm font-medium" :class="toggle(query.unverified)" :aria-pressed="query.unverified" @click="setUnverified(!query.unverified)">
          {{ t("curation.registry.unverifiedOnly") }}
        </button>
        <input
          :value="cityInput"
          type="text"
          :placeholder="t('curation.registry.city')"
          class="w-32 rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none"
          @input="setCity(($event.target as HTMLInputElement).value)"
        />
        <select :value="query.ownerType" class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none" :aria-label="t('curation.registry.ownerType')" @change="setOwnerType(($event.target as HTMLSelectElement).value as OwnerType | '')">
          <option value="">{{ t("curation.registry.allOwnerTypes") }}</option>
          <option v-for="o in OWNER_TYPES" :key="o" :value="o">{{ t(`curation.ownerTypes.${o}`) }}</option>
        </select>
        <select :value="query.themeId ?? ''" class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none" :aria-label="t('curation.registry.theme')" @change="setTheme(Number(($event.target as HTMLSelectElement).value) || null)">
          <option value="">{{ t("curation.registry.allThemes") }}</option>
          <option v-for="th in themes" :key="th.id" :value="th.id">{{ pick(th.label)?.text }}</option>
        </select>
        <button type="button" class="rounded-md border px-3 py-2 text-sm font-medium" :class="query.priority ? 'border-accent bg-accent-soft text-accent-strong' : 'border-line bg-surface text-ink'" :aria-pressed="query.priority" @click="setPriority(!query.priority)">
          {{ t("curation.registry.priority") }}
        </button>
      </div>

      <div class="mt-6">
        <ErrorState v-if="error" :error="error" @retry="retry" />
        <Spinner v-else-if="loading && items.length === 0" class="mx-auto my-12 block" />
        <EmptyState v-else-if="items.length === 0" :title="t('curation.registry.empty')" :description="t('curation.registry.emptyHelp')">
          <button v-if="hasFilters" type="button" class="mt-4 rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong" @click="clear">
            {{ t("curation.registry.clear") }}
          </button>
        </EmptyState>
        <template v-else>
          <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
              <thead class="border-b border-line text-xs text-ink-muted">
                <tr>
                  <th class="px-3 py-2 text-start font-medium">{{ t("curation.registry.columns.name") }}</th>
                  <th class="px-3 py-2 text-start font-medium">{{ t("curation.registry.columns.status") }}</th>
                  <th class="px-3 py-2 text-start font-medium">{{ t("curation.registry.columns.cityOwner") }}</th>
                  <th class="px-3 py-2 text-start font-medium">{{ t("curation.registry.columns.materials") }}</th>
                  <th class="px-3 py-2 text-start font-medium">{{ t("curation.registry.columns.themes") }}</th>
                  <th class="px-3 py-2 text-start font-medium">{{ t("curation.registry.columns.gaps") }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="a in items" :key="a.id" class="border-b border-line align-top hover:bg-neutral-soft/50" data-testid="artist-row">
                  <td class="px-3 py-3">
                    <RouterLink :to="localePath('admin.artists.show', { id: a.id })" class="block text-base font-semibold text-ink hover:underline">
                      <LocalizedText :text="a.name" />
                    </RouterLink>
                    <span class="text-xs text-ink-muted">{{ pick({ ar: a.name.en, en: a.name.ar })?.text }} <template v-if="a.legacy_code">· {{ a.legacy_code }}</template></span>
                  </td>
                  <td class="px-3 py-3">
                    <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="STATUS_CLASS[a.verified_status]">{{ t(`curation.status.${a.verified_status}`) }}</span>
                  </td>
                  <td class="px-3 py-3 text-ink-muted">
                    <LocalizedText v-if="a.city.ar || a.city.en" :text="a.city" />
                    <template v-else>—</template>
                    <span v-if="a.owner_type" class="block text-xs">{{ t(`curation.ownerTypes.${a.owner_type}`) }}</span>
                  </td>
                  <td class="px-3 py-3 tabular-nums text-ink-muted">{{ t("curation.registry.materials", { count: a.linked_material_count }) }}</td>
                  <td class="px-3 py-3">
                    <ul class="flex flex-wrap gap-1">
                      <li v-for="th in a.themes" :key="th.id" class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs text-ink-muted">{{ pick(th.label)?.text }}</li>
                    </ul>
                  </td>
                  <td class="px-3 py-3">
                    <span class="rounded-sm px-1.5 py-0.5 text-xs font-medium tabular-nums" :class="SEVERITY_BADGE_CLASS[a.severity]">
                      {{ a.gap_count > 0 ? t("curation.registry.gaps", { count: a.gap_count }) : t("curation.registry.noGaps") }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <Pagination class="mt-6" :meta="meta" @change="setPage" />
        </template>
      </div>

      <MergeToolModal v-if="merging" @close="merging = false" @merged="onMerged" />
    </template>
  </section>
</template>
