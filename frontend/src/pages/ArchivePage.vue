<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useArchiveList } from "@/composables/useArchiveList";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import { ARCHIVE_ITEM_TYPES, type ArchiveItemType } from "@/types/archive";

const { t } = useI18n();
const { pick } = useLocalized();
const auth = useAuthStore();

const { items, meta, loading, error, query, searchInput, retry, setSearch, setType, setIncludeUnpublished, clearArtist, setPage, clear } = useArchiveList();

const canManage = computed(() => auth.can("archive.manage"));
const hasFilters = computed(() => Object.values(query.value).some((v) => v && v !== 1));
const field = "rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
const STATUS_CLASS = { draft: "bg-warn-soft text-warn", published: "bg-success-soft text-success", hidden: "bg-neutral-soft text-ink-muted" } as const;

const yearOf = (i: (typeof items.value)[number]): string | null =>
  i.content?.display ?? (i.content?.year_from ? String(i.content.year_from) : null);
</script>

<template>
  <section>
    <div class="border-b-2 border-ink pb-6">
      <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("archive.title") }}</h1>
      <p v-if="meta" class="mt-1 text-sm text-ink-muted">{{ t("archive.subtitle", { count: meta.total }) }}</p>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-2">
      <input :value="searchInput" type="search" :placeholder="t('archive.searchPlaceholder')" :class="[field, 'min-w-64 flex-1']" @input="setSearch(($event.target as HTMLInputElement).value)" />
      <select :value="query.type" :class="field" :aria-label="t('archive.type')" @change="setType(($event.target as HTMLSelectElement).value as ArchiveItemType | '')">
        <option value="">{{ t("archive.allTypes") }}</option>
        <option v-for="ty in ARCHIVE_ITEM_TYPES" :key="ty" :value="ty">{{ t(`archive.types.${ty}`) }}</option>
      </select>
      <button v-if="canManage" type="button" class="rounded-md border px-3 py-2 text-sm font-medium" :class="query.includeUnpublished ? 'border-accent bg-accent-soft text-accent-strong' : 'border-line bg-surface text-ink'" :aria-pressed="query.includeUnpublished" data-testid="include-unpublished" @click="setIncludeUnpublished(!query.includeUnpublished)">
        {{ t("archive.includeUnpublished") }}
      </button>
    </div>

    <p v-if="query.artistId" class="mt-4 flex items-center gap-3 text-sm text-ink-muted" data-testid="artist-filter">
      {{ t("archive.artistFilter") }}
      <button type="button" class="text-accent-strong hover:underline" data-testid="clear-artist" @click="clearArtist">{{ t("archive.clearArtist") }}</button>
    </p>

    <div class="mt-6">
      <ErrorState v-if="error" :error="error" @retry="retry" />
      <Spinner v-else-if="loading && items.length === 0" class="mx-auto my-12 block" />
      <EmptyState v-else-if="items.length === 0" :title="t('archive.empty')" :description="t('archive.emptyHelp')">
        <button v-if="hasFilters" type="button" class="mt-4 rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong" @click="clear">{{ t("archive.clear") }}</button>
      </EmptyState>
      <template v-else>
        <ul class="divide-y divide-line border-y border-line" data-testid="archive-list">
          <li v-for="i in items" :key="i.id" class="flex flex-wrap items-start justify-between gap-4 py-4" data-testid="archive-row">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink-muted">{{ t(`archive.types.${i.item_type}`) }}</span>
                <span v-if="canManage" class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="STATUS_CLASS[i.publication_status]">{{ t(`archive.statuses.${i.publication_status}`) }}</span>
                <span v-if="i.restricted" class="rounded-sm bg-warn-soft px-1.5 py-0.5 text-xs font-medium text-warn" data-testid="restricted">{{ t("archive.restricted") }}</span>
              </div>
              <h2 class="mt-1 text-lg font-semibold text-ink"><LocalizedText :text="i.title" /></h2>
              <p class="text-sm text-ink-muted">{{ pick({ ar: i.title.en, en: i.title.ar })?.text }}</p>
              <p v-if="i.links?.some((l) => l.artist)" class="mt-1 text-xs text-ink-muted">
                <template v-for="(l, n) in i.links.filter((x) => x.artist)" :key="n"><template v-if="n > 0">، </template><LocalizedText :text="l.artist!.name" /></template>
              </p>
            </div>
            <div class="text-end text-xs tabular-nums text-ink-muted">
              <p v-if="yearOf(i)">{{ yearOf(i) }}</p>
              <p v-if="i.legacy_ref" dir="ltr">{{ i.legacy_ref }}</p>
            </div>
          </li>
        </ul>
        <Pagination class="mt-6" :meta="meta" @change="setPage" />
      </template>
    </div>
  </section>
</template>
