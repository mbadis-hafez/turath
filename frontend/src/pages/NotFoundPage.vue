<script setup lang="ts">
import { useI18n } from "vue-i18n";

import { useLocalePath } from "@/composables/useLocalePath";

const { t } = useI18n();
const { localePath } = useLocalePath();
</script>

<template>
  <section class="mx-auto max-w-xl py-16 text-center">
    <p class="text-6xl font-semibold text-line">404</p>
    <h1 class="mt-4 text-2xl font-semibold text-ink">
      {{ t("errors.notFound") }}
    </h1>
    <p class="mt-6">
      <RouterLink
        :to="localePath('home')"
        class="rounded-md bg-accent px-5 py-2.5 font-medium text-surface hover:bg-accent-strong"
      >
        {{ t("nav.home") }}
      </RouterLink>
    </p>
  </section>
</template>
