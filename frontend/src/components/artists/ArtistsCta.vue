<script setup lang="ts">
import { useI18n } from "vue-i18n";

import { useLocalePath } from "@/composables/useLocalePath";

const { t } = useI18n();
const { localePath } = useLocalePath();
</script>

<template>
  <div
    class="mt-10 flex flex-wrap items-start justify-between gap-6 border border-ink p-6 sm:p-7"
  >
    <div class="min-w-[280px] flex-1">
      <h2
        class="font-display text-[22px] font-bold leading-[1.35] text-balance text-ink"
      >
        {{ t("artists.cta.title") }}
      </h2>
      <p
        class="mt-2.5 max-w-[62ch] text-[14.5px] leading-[1.85] text-pretty text-ink-muted"
      >
        {{ t("artists.cta.body") }}
      </p>
    </div>
    <RouterLink
      :to="localePath('submit')"
      class="bg-ink px-6 py-3.5 text-[14.5px] font-semibold text-paper transition-colors hover:bg-accent"
      data-testid="cta-submit"
    >
      {{ t("artists.cta.button") }}
    </RouterLink>
  </div>
</template>
