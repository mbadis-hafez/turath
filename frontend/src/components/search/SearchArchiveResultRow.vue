<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import HighlightedText from "@/components/search/HighlightedText.vue";
import PartialDateDisplay from "@/components/common/PartialDateDisplay.vue";
import { useLocalized } from "@/composables/useLocalized";
import type { ArchiveItem } from "@/types/archive";

const props = defineProps<{
  item: ArchiveItem;
  highlight: string;
}>();

const { t } = useI18n();
const { pick } = useLocalized();

const description = computed(
  () => pick(props.item.description ?? { ar: null, en: null })?.text ?? null,
);
const title = computed(() => pick(props.item.title)?.text ?? "");
</script>

<template>
  <article
    class="flex gap-4 border-b border-line py-5"
    data-testid="result-archive"
  >
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2 text-xs">
        <span
          class="bg-ink px-2 py-0.5 font-medium text-paper"
          data-testid="type-badge"
          >{{ t(`archive.types.${item.item_type}`) }}</span
        >
        <span
          v-if="item.restricted"
          class="border border-dashed border-ink-muted px-2 py-0.5 text-ink-muted"
          data-testid="restricted-badge"
        >
          {{ t("search.restricted") }}
        </span>
        <span
          v-if="item.content"
          class="ms-auto tabular-nums text-ink-muted font-latin"
        >
          <PartialDateDisplay :date="item.content" />
        </span>
      </div>
      <h3 class="mt-2 text-lg font-semibold text-ink">
        <HighlightedText :text="title" :term="highlight" />
      </h3>
      <p
        v-if="description"
        class="mt-1 line-clamp-2 leading-relaxed text-pretty text-ink-muted"
      >
        <HighlightedText :text="description" :term="highlight" />
      </p>
    </div>
  </article>
</template>
