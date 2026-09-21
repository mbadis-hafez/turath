<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalized } from "@/composables/useLocalized";
import type { ArchiveItem } from "@/types/archive";

const props = defineProps<{ item: ArchiveItem; layout: "grid" | "list" }>();
const { t } = useI18n();
const { pick } = useLocalized();

const year = computed(() => (props.item.content?.year_from ? String(props.item.content.year_from) : null));

/** "Artist · place", built only from what the API says is public for this item. */
const byline = computed(() => {
  const names = (props.item.artists ?? []).map((a) => pick(a.name)?.text).filter(Boolean);
  const place = props.item.place ? pick(props.item.place)?.text : null;
  return [names.join("، "), place].filter(Boolean).join(" · ");
});
</script>

<template>
  <article :class="layout === 'list' ? 'flex gap-5 border-b border-line py-5' : ''" data-testid="archive-card" :data-layout="layout">
    <div class="relative bg-neutral-soft" :class="layout === 'list' ? 'h-28 w-40 shrink-0' : 'aspect-[4/3]'">
      <span v-if="item.restricted" class="absolute end-3 top-3 border border-dashed border-ink-muted bg-surface px-2 py-0.5 text-xs text-ink-muted" data-testid="restricted-badge">{{ t("archive.browse.restricted") }}</span>
    </div>
    <div class="min-w-0 flex-1">
      <p class="mt-3 flex items-center justify-between text-xs text-ink-muted" :class="layout === 'list' ? '!mt-0' : ''">
        <span class="bg-neutral-soft px-2 py-1 font-semibold uppercase tracking-wide text-ink" data-testid="card-type">{{ item.item_type }}</span>
        <span class="tabular-nums" data-testid="card-year">{{ year ?? t("archive.browse.unavailable") }}</span>
      </p>
      <h3 class="mt-2 text-balance text-xl font-semibold leading-snug text-ink"><LocalizedText :text="item.title" /></h3>
      <p v-if="byline" class="mt-1 text-sm text-ink-muted" data-testid="card-byline">{{ byline }}</p>
    </div>
  </article>
</template>
