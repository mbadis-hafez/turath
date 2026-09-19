<script setup lang="ts">
import { useI18n } from "vue-i18n";

import { useLocalePath } from "@/composables/useLocalePath";

const { t } = useI18n();
const { localePath } = useLocalePath();
</script>

<template>
  <section class="mx-auto max-w-2xl py-12 text-center">
    <h1 class="text-4xl font-semibold tracking-tight text-ink">
      {{ t("home.heading") }}
    </h1>
    <p class="mt-6 text-lg leading-relaxed text-ink-muted">
      {{ t("home.intro") }}
    </p>
    <p class="mt-10">
      <RouterLink
        :to="localePath('login')"
        class="rounded-md bg-accent px-5 py-2.5 font-medium text-surface hover:bg-accent-strong"
      >
        {{ t("home.loginLink") }}
      </RouterLink>
    </p>
  </section>
</template>
