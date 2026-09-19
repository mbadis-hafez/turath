<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import { useLocalePath } from "@/composables/useLocalePath";

const route = useRoute();
const { t } = useI18n();
const { localePath } = useLocalePath();

const titleKey = computed(() =>
  typeof route.meta.titleKey === "string" ? route.meta.titleKey : "nav.home",
);
</script>

<template>
  <section class="mx-auto max-w-2xl py-24 text-center">
    <h1
      class="text-4xl font-bold text-balance font-display text-ink sm:text-5xl"
    >
      {{ t(titleKey) }}
    </h1>
    <p class="mt-5 text-lg leading-relaxed text-pretty text-ink-muted">
      {{ t("common.comingSoon") }}
    </p>
    <p class="mt-10">
      <RouterLink
        :to="localePath('artists.index')"
        class="bg-ink px-6 py-3 font-semibold text-paper transition-colors hover:bg-accent"
      >
        {{ t("nav.artists") }}
      </RouterLink>
    </p>
  </section>
</template>
