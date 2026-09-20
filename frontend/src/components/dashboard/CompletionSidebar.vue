<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";

import { acknowledgeReviewItem, listReviewQueue } from "@/api/dashboard";
import type {
  DashboardEntityType,
  DashboardStats,
  ReviewQueueItem,
} from "@/types/completeness";

defineProps<{
  stats: DashboardStats | null;
}>();

const { t, te } = useI18n();

const TYPES: DashboardEntityType[] = ["artist", "artwork", "archive_item"];

const queue = ref<ReviewQueueItem[]>([]);

async function loadQueue(): Promise<void> {
  try {
    queue.value = (await listReviewQueue()).data;
  } catch {
    queue.value = [];
  }
}

async function acknowledge(id: string): Promise<void> {
  await acknowledgeReviewItem(id);
  queue.value = queue.value.filter((item) => item.id !== id);
}

function noteFor(key: string | null): string | null {
  if (!key) return null;
  return te(`dashboard.field.${key}`) ? t(`dashboard.field.${key}`) : key;
}

onMounted(() => void loadQueue());
</script>

<template>
  <aside class="space-y-6">
    <section class="rounded-lg border border-line bg-surface p-4">
      <h2 class="text-sm font-semibold text-ink">{{ t("dashboard.sidebar.byType") }}</h2>
      <ul class="mt-3 space-y-4">
        <template v-for="type in TYPES" :key="type">
          <li v-if="stats?.by_entity_type?.[type]?.of">
            <div class="flex items-baseline justify-between text-sm">
              <span class="text-ink">{{ t(`dashboard.entity.${type}`) }}</span>
              <span class="tabular-nums text-ink-muted">{{ stats.by_entity_type[type]!.pct }}%</span>
            </div>
            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-neutral-soft">
              <div
                class="h-full rounded-full bg-accent"
                :style="{ width: `${stats.by_entity_type[type]!.pct}%` }"
              />
            </div>
            <p class="mt-1 text-xs text-ink-muted">
              {{
                t("dashboard.sidebar.typeCount", {
                  count: stats.by_entity_type[type]!.count,
                  of: stats.by_entity_type[type]!.of,
                })
              }}
              <template v-if="noteFor(stats.by_entity_type[type]!.note_key)">
                · {{ noteFor(stats.by_entity_type[type]!.note_key) }}
              </template>
            </p>
          </li>
        </template>
      </ul>
    </section>

    <section class="rounded-lg border border-line bg-surface p-4">
      <h2 class="text-sm font-semibold text-ink">{{ t("dashboard.sidebar.reviewQueue") }}</h2>
      <p v-if="queue.length === 0" class="mt-2 text-sm text-ink-muted">
        {{ t("dashboard.sidebar.reviewEmpty") }}
      </p>
      <ul v-else class="mt-3 space-y-3" data-testid="review-queue">
        <li v-for="item in queue" :key="item.id" class="text-sm">
          <p class="font-medium text-ink">{{ t(`dashboard.review.${item.review_type}`) }}</p>
          <p v-if="item.note" class="text-xs text-ink-muted">{{ item.note }}</p>
          <button
            type="button"
            class="mt-1 text-xs font-medium text-accent hover:underline"
            @click="acknowledge(item.id)"
          >
            {{ t("dashboard.sidebar.acknowledge") }}
          </button>
        </li>
      </ul>
    </section>

    <section class="rounded-lg border border-line bg-sand p-4">
      <h2 class="text-sm font-semibold text-sand-ink">{{ t("dashboard.sidebar.whyTitle") }}</h2>
      <p class="mt-2 text-pretty text-sm text-sand-ink">{{ t("dashboard.sidebar.whyBody") }}</p>
    </section>
  </aside>
</template>
