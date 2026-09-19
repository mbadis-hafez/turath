<script setup lang="ts">
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { useAuthStore } from "@/stores/auth";
import { useLocalePath } from "@/composables/useLocalePath";
import type { AppLocale } from "@/i18n";

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { localePath } = useLocalePath();

const homeLink = computed(() => localePath("home"));
const activityLink = computed(() => localePath("admin.activity"));
const loginLink = computed(() => localePath("login"));

const otherLocale = computed<AppLocale>(() =>
  route.params.locale === "en" ? "ar" : "en",
);

/** Same route, other locale — keeps params (e.g. query string filters). */
const otherLocaleLink = computed(() => ({
  name: route.name ?? "home",
  params: { ...route.params, locale: otherLocale.value },
  query: route.query,
}));

async function logout(): Promise<void> {
  await auth.logout();
  await router.push(localePath("home"));
}
</script>

<template>
  <div class="flex min-h-dvh flex-col">
    <a
      href="#content"
      class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-accent focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-surface"
    >
      {{ t("common.skipToContent") }}
    </a>

    <header class="border-b border-line bg-surface">
      <div
        class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-4 sm:px-6"
      >
        <RouterLink
          :to="homeLink"
          class="text-lg font-semibold tracking-tight text-ink hover:text-accent-strong"
        >
          {{ $t("home.heading") }}
        </RouterLink>
        <nav aria-label="Main" class="flex items-center gap-1">
          <RouterLink
            :to="homeLink"
            class="rounded-md px-3 py-1.5 text-sm text-ink-muted hover:bg-neutral-soft hover:text-ink"
            active-class="!text-accent-strong font-medium"
          >
            {{ $t("nav.home") }}
          </RouterLink>
          <RouterLink
            v-if="auth.can('activity.view')"
            :to="activityLink"
            class="rounded-md px-3 py-1.5 text-sm text-ink-muted hover:bg-neutral-soft hover:text-ink"
            active-class="!text-accent-strong font-medium"
          >
            {{ $t("nav.activity") }}
          </RouterLink>
        </nav>
        <div class="ms-auto flex items-center gap-2">
          <RouterLink
            :to="otherLocaleLink"
            class="rounded-md border border-line px-3 py-1.5 text-sm text-ink hover:bg-neutral-soft"
            :lang="otherLocale"
          >
            {{ otherLocale === "en" ? "English" : "العربية" }}
          </RouterLink>
          <RouterLink
            v-if="!auth.isAuthenticated"
            :to="loginLink"
            class="rounded-md bg-accent px-3 py-1.5 text-sm font-medium text-surface hover:bg-accent-strong"
          >
            {{ $t("nav.login") }}
          </RouterLink>
          <button
            v-else
            type="button"
            class="rounded-md border border-line px-3 py-1.5 text-sm text-ink hover:bg-neutral-soft"
            @click="logout"
          >
            {{ $t("nav.logout") }}
          </button>
        </div>
      </div>
    </header>

    <main
      id="content"
      class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:px-6"
    >
      <slot />
    </main>

    <footer class="border-t border-line bg-surface">
      <div class="mx-auto max-w-5xl px-4 py-6 text-sm text-ink-muted sm:px-6">
        {{ $t("home.heading") }} — Bidayaat
      </div>
    </footer>
  </div>
</template>
