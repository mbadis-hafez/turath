<script setup lang="ts">
import { useI18n } from "vue-i18n";

import { useLocalePath } from "@/composables/useLocalePath";

const { t } = useI18n();
const { localePath } = useLocalePath();

/** Researchers register their material through the same form as everyone else, with their role already chosen. */
const researcherLink = localePath("submit", {}, { role: "researcher" });

const STEPS = ["collect", "digitize", "document", "access"] as const;
/** The labels visitors meet on records, with the look each one has where it appears. */
const LABELS = [
  { key: "verified", badge: "bg-info-soft text-info" },
  { key: "underVerification", badge: "bg-warn-soft text-warn" },
  { key: "restricted", badge: "border border-dashed border-ink-muted bg-surface text-ink-muted" },
  { key: "yearUncertain", badge: "border border-dashed border-ink-muted bg-surface text-ink-muted" },
] as const;
</script>

<template>
  <article>
    <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
      {{ t("methodology.crumbParent") }} <span aria-hidden="true">&rsaquo;</span> <span class="text-ink">{{ t("methodology.crumb") }}</span>
    </nav>

    <header class="mt-6 max-w-4xl">
      <p class="text-xs text-ink-muted">{{ t("methodology.eyebrow") }}</p>
      <h1 class="mt-3 text-balance font-display text-5xl font-semibold leading-tight text-ink sm:text-6xl">{{ t("methodology.title") }}</h1>
      <p class="mt-6 text-pretty text-xl leading-loose text-ink-muted">{{ t("methodology.intro") }}</p>
    </header>

    <ol class="mt-12 grid gap-x-10 gap-y-10 border-t-2 border-ink pt-10 sm:grid-cols-2 lg:grid-cols-4" data-testid="steps">
      <li v-for="(step, n) in STEPS" :key="step" data-testid="step">
        <span class="text-xs tabular-nums text-ink-muted">{{ String(n + 1).padStart(2, "0") }}</span>
        <h2 class="mt-3 font-display text-3xl font-semibold text-ink">{{ t(`methodology.steps.${step}.title`) }}</h2>
        <p class="mt-4 text-pretty text-lg leading-relaxed text-ink-muted">{{ t(`methodology.steps.${step}.body`) }}</p>
      </li>
    </ol>

    <div class="mt-12 grid gap-16 border-t border-line pt-12 lg:grid-cols-2">
      <section data-testid="labels">
        <p class="text-xs text-ink-muted">{{ t("methodology.labels.heading") }}</p>
        <ul class="mt-4 border-t-2 border-ink">
          <li v-for="label in LABELS" :key="label.key" class="flex items-start gap-5 border-b border-line py-5" data-testid="label-row">
            <span class="w-28 shrink-0"><span class="inline-block px-3 py-1 text-xs font-semibold" :class="label.badge" data-testid="label-badge">{{ t(`methodology.labels.${label.key}.name`) }}</span></span>
            <p class="text-pretty text-lg leading-relaxed text-ink">{{ t(`methodology.labels.${label.key}.body`) }}</p>
          </li>
        </ul>
      </section>

      <section data-testid="rights">
        <p class="text-xs text-ink-muted">{{ t("methodology.rights.heading") }}</p>
        <div class="mt-4 border-t-2 border-ink pt-6">
          <p class="text-pretty text-lg leading-loose text-ink">{{ t("methodology.rights.body") }}</p>
          <div class="mt-8 flex flex-wrap gap-4">
            <RouterLink :to="researcherLink" class="border border-ink px-8 py-4 text-lg font-semibold text-ink transition-colors hover:bg-ink hover:text-paper" data-testid="rights-researchers">
              {{ t("methodology.rights.researchers") }}
            </RouterLink>
            <!-- The rights policy page is not written yet; disabled rather than linked to nowhere. -->
            <button type="button" class="border border-ink px-8 py-4 text-lg font-semibold text-ink disabled:cursor-not-allowed disabled:opacity-50" disabled :title="t('methodology.rights.soon')" data-testid="rights-policy">
              {{ t("methodology.rights.policy") }}
            </button>
          </div>
        </div>
      </section>
    </div>
  </article>
</template>
