<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import { useLocalized } from "@/composables/useLocalized";
import type { ArchiveListQuery } from "@/composables/useArchiveList";
import type { AccessFacet, ArchiveFacets, ArchiveItemType } from "@/types/archive";

const props = defineProps<{ facets: ArchiveFacets | null; query: ArchiveListQuery; canManage: boolean }>();
const emit = defineEmits<{
  type: [value: ArchiveItemType];
  place: [value: string];
  theme: [value: number];
  access: [value: AccessFacet];
  unpublished: [value: boolean];
  clear: [];
}>();

const { t } = useI18n();
const { pick } = useLocalized();

interface Option { key: string | number; label: string; count: number; checked: boolean; toggle: () => void }
interface Group { id: string; title: string; options: Option[] }

/**
 * A ticked option that no longer appears in the counts (nothing else matches it now)
 * stays listed with a zero, so it can always be unticked.
 */
function withSelected<T extends string | number>(
  values: { value: T; count: number; label?: { ar: string | null; en: string | null } }[] | undefined,
  selected: T[],
  label: (v: T, l?: { ar: string | null; en: string | null }) => string,
  toggle: (v: T) => void,
): Option[] {
  const rows = [...(values ?? [])];
  for (const s of selected) if (!rows.some((r) => r.value === s)) rows.push({ value: s, count: 0 });
  return rows.map((r) => ({ key: r.value, label: label(r.value, r.label), count: r.count, checked: selected.includes(r.value), toggle: () => toggle(r.value) }));
}

const groups = computed<Group[]>(() => [
  {
    id: "type", title: t("archive.browse.groups.type"),
    options: withSelected(props.facets?.item_type, props.query.types as string[], (v) => t(`archive.types.${v}`), (v) => emit("type", v as ArchiveItemType)),
  },
  {
    id: "place", title: t("archive.browse.groups.place"),
    options: withSelected(props.facets?.place, props.query.places, (v) => v, (v) => emit("place", v)),
  },
  {
    id: "theme", title: t("archive.browse.groups.theme"),
    options: withSelected(props.facets?.theme_id, props.query.themeIds, (v, l) => (l ? pick(l)?.text ?? `#${v}` : `#${v}`), (v) => emit("theme", v)),
  },
  {
    id: "access", title: t("archive.browse.groups.access"),
    options: withSelected(props.facets?.access, props.query.access, (v) => t(`archive.browse.access.${v}`), (v) => emit("access", v as AccessFacet)),
  },
].filter((g) => g.options.length > 0));

const active = computed(() => props.query.types.length + props.query.places.length + props.query.themeIds.length + props.query.access.length > 0 || props.query.artistId !== null);
</script>

<template>
  <aside data-testid="facets" :aria-label="t('archive.browse.filter')">
    <div class="flex items-baseline justify-between border-b-2 border-ink pb-3">
      <h2 class="text-xs font-semibold text-ink-muted">{{ t("archive.browse.filter") }}</h2>
      <button v-if="active" type="button" class="text-xs text-accent-strong hover:underline" data-testid="clear-filters" @click="emit('clear')">{{ t("archive.browse.clear") }}</button>
    </div>

    <fieldset v-for="g in groups" :key="g.id" class="border-b border-line py-5" :data-testid="`facet-${g.id}`">
      <legend class="mb-3 text-base font-semibold text-ink">{{ g.title }}</legend>
      <ul class="space-y-3">
        <li v-for="o in g.options" :key="o.key">
          <label class="flex cursor-pointer items-center justify-between gap-3 text-ink" :class="o.count === 0 && !o.checked ? 'opacity-50' : ''">
            <span class="flex items-center gap-3">
              <input type="checkbox" class="size-4 accent-ink" :checked="o.checked" data-testid="facet-option" @change="o.toggle()" />
              {{ o.label }}
            </span>
            <span class="text-xs tabular-nums text-ink-muted" data-testid="facet-count">{{ o.count }}</span>
          </label>
        </li>
      </ul>
    </fieldset>

    <label v-if="canManage" class="mt-5 flex cursor-pointer items-center gap-3 text-sm text-ink">
      <input type="checkbox" class="size-4 accent-ink" :checked="query.includeUnpublished" data-testid="include-unpublished" @change="emit('unpublished', ($event.target as HTMLInputElement).checked)" />
      {{ t("archive.includeUnpublished") }}
    </label>
  </aside>
</template>
