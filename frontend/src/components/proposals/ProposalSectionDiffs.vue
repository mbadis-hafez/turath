<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import FieldDiffTable from "@/components/proposals/FieldDiffTable.vue";
import type { Bilingual } from "@/types/artist";
import type { CollectionDiff, DiffField, SectionDiff } from "@/types/proposalDiff";

const props = defineProps<{ sections: SectionDiff[] }>();

const { t } = useI18n();

const SECTION_KEYS = ["fields", "curation", "educations", "activities", "social_links", "pipeline", "participants"] as const;

const visibleSections = computed(() =>
  props.sections.filter((s) => s.fields.length > 0 || s.collections.some((c) => hasRows(c))),
);

function hasRows(collection: CollectionDiff): boolean {
  return collection.added.length > 0 || collection.removed.length > 0 || collection.changed.length > 0;
}

function sectionTitle(key: string): string {
  return (SECTION_KEYS as readonly string[]).includes(key) ? t(`proposals.diffSections.${key}`) : key;
}

const labelsOf = (fields: DiffField[]) =>
  Object.fromEntries(fields.map((f) => [f.field, f.label])) as Record<string, Bilingual>;

const rowsOf = (fields: DiffField[]) => fields.map((f) => ({ field: f.field, before: f.old, after: f.new }));
</script>

<template>
  <div class="space-y-5" data-testid="section-diffs">
    <section v-for="section in visibleSections" :key="section.key" data-testid="section-diff">
      <h3 class="text-sm font-semibold text-ink">{{ sectionTitle(section.key) }}</h3>

      <FieldDiffTable v-if="section.fields.length" class="mt-2" :rows="rowsOf(section.fields)" :labels="labelsOf(section.fields)" />

      <div v-for="collection in section.collections.filter(hasRows)" :key="collection.key" class="mt-3">
        <p class="text-xs font-medium text-ink-muted">{{ collection.key }}</p>
        <div class="mt-1 space-y-3">
          <div v-if="collection.added.length" data-testid="collection-added">
            <p class="text-xs font-medium text-success">{{ t("proposals.diff.added") }}</p>
            <div v-for="(row, n) in collection.added" :key="`a-${n}`" class="mt-1">
              <p class="text-xs text-ink">{{ row.label }}</p>
              <FieldDiffTable class="mt-1" :rows="rowsOf(row.fields)" :labels="labelsOf(row.fields)" />
            </div>
          </div>
          <div v-if="collection.removed.length" data-testid="collection-removed">
            <p class="text-xs font-medium text-danger">{{ t("proposals.diff.removed") }}</p>
            <div v-for="(row, n) in collection.removed" :key="`r-${n}`" class="mt-1">
              <p class="text-xs text-ink">{{ row.label }}</p>
              <FieldDiffTable class="mt-1" :rows="rowsOf(row.fields)" :labels="labelsOf(row.fields)" />
            </div>
          </div>
          <div v-if="collection.changed.length" data-testid="collection-changed">
            <p class="text-xs font-medium text-warn">{{ t("proposals.diff.changed") }}</p>
            <div v-for="(row, n) in collection.changed" :key="`c-${n}`" class="mt-1">
              <p class="text-xs text-ink">{{ row.label }}</p>
              <FieldDiffTable class="mt-1" :rows="rowsOf(row.fields)" :labels="labelsOf(row.fields)" />
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
