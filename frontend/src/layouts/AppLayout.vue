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
const artistsLink = computed(() => localePath("artists.index"));
const artworksLink = computed(() => localePath("artworks.index"));
const archiveLink = computed(() => localePath(auth.can("archive.manage") ? "admin.archive" : "archive.records"));
const activityLink = computed(() => localePath("admin.activity"));
const importsLink = computed(() => localePath("admin.imports"));
const dashboardLink = computed(() => localePath("dashboard"));
const registryLink = computed(() => localePath("admin.artists"));
const artworkRegistryLink = computed(() => localePath("admin.artworks"));
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

    <header class="border-b-2 border-ink bg-paper">
      <div
        class="mx-auto grid max-w-[90rem] grid-cols-[1fr_auto_1fr] items-center px-6 py-4 sm:px-12"
      >
        <RouterLink
          :to="homeLink"
          class="flex w-fit items-baseline gap-2 text-ink hover:text-accent-strong"
        >
          <img src="/logo.png" :alt="$t('home.heading')" class="h-12 w-auto" width="113" height="48" />
        </RouterLink>
        <nav
          aria-label="Main"
          class="hidden items-center gap-7 text-sm font-medium md:flex"
        >
          <!-- Contributors navigate to working backend pages. -->
          <template v-if="auth.isAuthenticated">
            <RouterLink
              :to="artistsLink"
              class="text-ink transition-colors hover:text-accent"
              active-class="!text-accent underline decoration-accent decoration-2 underline-offset-8"
              >{{ $t("nav.artists") }}</RouterLink
            >
            <RouterLink
              :to="artworksLink"
              class="text-ink transition-colors hover:text-accent"
              active-class="!text-accent underline decoration-accent decoration-2 underline-offset-8"
              >{{ $t("nav.artworks") }}</RouterLink
            >
            <RouterLink
              :to="archiveLink"
              class="text-ink transition-colors hover:text-accent"
              active-class="!text-accent underline decoration-accent decoration-2 underline-offset-8"
              >{{ $t("nav.archive") }}</RouterLink
            >
            <RouterLink
              :to="dashboardLink"
              class="text-ink transition-colors hover:text-accent"
              active-class="!text-accent underline decoration-accent decoration-2 underline-offset-8"
              >{{ $t("nav.dashboard") }}</RouterLink
            >
            <RouterLink
              v-if="auth.can('activity.view')"
              :to="activityLink"
              class="text-ink-muted transition-colors hover:text-ink"
              active-class="!text-accent underline decoration-accent decoration-2 underline-offset-8"
            >
              {{ $t("nav.activity") }}
            </RouterLink>
            <RouterLink
              v-if="auth.can('artists.manage')"
              :to="registryLink"
              class="text-ink-muted transition-colors hover:text-ink"
              active-class="!text-accent underline decoration-accent decoration-2 underline-offset-8"
            >
              {{ $t("nav.registry") }}
            </RouterLink>
            <RouterLink
              v-if="auth.can('artworks.manage')"
              :to="artworkRegistryLink"
              class="text-ink-muted transition-colors hover:text-ink"
              active-class="!text-accent underline decoration-accent decoration-2 underline-offset-8"
            >
              {{ $t("nav.artworkRegistry") }}
            </RouterLink>
            <RouterLink
              v-if="auth.can('imports.manage')"
              :to="importsLink"
              class="text-ink-muted transition-colors hover:text-ink"
              active-class="!text-accent underline decoration-accent decoration-2 underline-offset-8"
            >
              {{ $t("nav.imports") }}
            </RouterLink>
          </template>
          <!-- Visitors browse the landing-page sections. -->
          <template v-else>
            <RouterLink
              :to="artistsLink"
              class="text-ink transition-colors hover:text-accent"
              active-class="!text-accent-strong font-medium"
              >{{ $t("nav.artists") }}</RouterLink
            >
            <a
              href="#works"
              class="text-ink transition-colors hover:text-accent"
              >{{ $t("nav.artworks") }}</a
            >
            <a
              href="#materials"
              class="text-accent underline decoration-accent decoration-2 underline-offset-8"
              aria-current="page"
              >{{ $t("nav.archive") }}</a
            >
            <a
              href="#events"
              class="text-ink transition-colors hover:text-accent"
              >{{ $t("nav.events") }}</a
            >
            <a
              href="#themes"
              class="text-ink transition-colors hover:text-accent"
              >{{ $t("nav.themes") }}</a
            >
          </template>
        </nav>
        <div class="flex items-center justify-self-end gap-3">
          <RouterLink
            :to="otherLocaleLink"
            class="border border-ink px-3 py-1.5 text-sm font-medium text-ink transition-colors hover:bg-ink hover:text-paper"
            :lang="otherLocale"
          >
            {{ otherLocale === "en" ? "EN" : "عربي" }}
          </RouterLink>
          <RouterLink
            v-if="!auth.isAuthenticated"
            :to="loginLink"
            class="bg-ink px-5 py-1.5 text-sm font-semibold text-paper transition-colors hover:bg-ink/85"
          >
            {{ $t("nav.login") }}
          </RouterLink>
          <button
            v-else
            type="button"
            class="border border-ink px-5 py-1.5 text-sm font-semibold text-ink transition-colors hover:bg-ink hover:text-paper"
            @click="logout"
          >
            {{ $t("nav.logout") }}
          </button>
        </div>
      </div>
    </header>

    <main
      id="content"
      class="mx-auto w-full max-w-[90rem] flex-1 px-6 py-8 sm:px-12"
    >
      <slot />
    </main>

    <footer class="mt-16 border-t-2 border-ink bg-paper">
      <div
        class="mx-auto flex max-w-[90rem] flex-wrap items-start justify-between gap-10 px-6 py-10 sm:px-12"
      >
        <img src="/logo.png" :alt="$t('home.heading')" class="h-16 w-auto" width="151" height="64" />
        <nav
          aria-label="Footer"
          class="flex flex-wrap gap-11 text-[13.5px] text-ink-muted"
        >
          <div class="flex flex-col gap-2.25">
            <h2
              class="text-[10.5px] tracking-widest text-ink-faint uppercase font-latin"
            >
              {{ $t("home.footer.browse") }}
            </h2>
            <a href="#artists" class="transition-colors hover:text-ink">{{
              $t("home.footer.artists")
            }}</a>
            <a href="#works" class="transition-colors hover:text-ink">{{
              $t("home.footer.artworks")
            }}</a>
            <a href="#archive" class="transition-colors hover:text-ink">{{
              $t("home.footer.archive")
            }}</a>
          </div>
          <div class="flex flex-col gap-2.25">
            <h2
              class="text-[10.5px] tracking-widest text-ink-faint uppercase font-latin"
            >
              {{ $t("home.footer.about") }}
            </h2>
            <a href="#" class="transition-colors hover:text-ink" @click.prevent>{{
              $t("home.footer.methodology")
            }}</a>
            <a href="#" class="transition-colors hover:text-ink" @click.prevent>{{
              $t("home.footer.rights")
            }}</a>
            <a href="#" class="transition-colors hover:text-ink" @click.prevent>{{
              $t("home.footer.partners")
            }}</a>
          </div>
          <div class="flex flex-col gap-2.25">
            <h2
              class="text-[10.5px] tracking-widest text-ink-faint uppercase font-latin"
            >
              {{ $t("home.footer.contact") }}
            </h2>
            <a href="#" class="transition-colors hover:text-ink" @click.prevent>{{
              $t("home.footer.researchers")
            }}</a>
            <a href="#" class="transition-colors hover:text-ink" @click.prevent>{{
              $t("home.footer.owners")
            }}</a>
            <a href="#" class="transition-colors hover:text-ink" @click.prevent>{{
              $t("home.footer.institutions")
            }}</a>
          </div>
        </nav>
      </div>
    </footer>
  </div>
</template>
