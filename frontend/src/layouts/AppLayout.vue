<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter, type RouteLocationRaw } from "vue-router";
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
const timelineLink = computed(() => localePath("timeline"));
const proposalsLink = computed(() => localePath("proposals"));
const submitLink = computed(() => localePath("submit"));
const methodologyLink = computed(() => localePath("methodology"));
const researcherLink = computed(() => localePath("submit", {}, { role: "researcher" }));
const materialsLink = computed(() => localePath("admin.materials"));
const canReviewProposals = computed(() =>
  ["artists.manage", "artworks.manage", "archive.manage", "events.manage"].some((p) => auth.can(p)),
);
const showProposals = computed(() => canReviewProposals.value || auth.can("proposals.submit"));
const registryLink = computed(() => localePath("admin.artists"));
const artworkRegistryLink = computed(() => localePath("admin.artworks"));
const eventsRegistryLink = computed(() => localePath("admin.events"));
const loginLink = computed(() => localePath("login"));

interface NavLink {
  key: string;
  label: string;
  to: RouteLocationRaw;
  /** Secondary links are the working tools of signed-in staff; they read lighter than the public ones. */
  muted?: boolean;
  /** A link to a section of another page never counts as "the current page". */
  section?: boolean;
}

const themesLink = computed(() => ({ ...localePath("home"), hash: "#themes" }));

const navLinks = computed<NavLink[]>(() => {
  const links: NavLink[] = [
    { key: "artists", label: t("nav.artists"), to: artistsLink.value },
    { key: "artworks", label: t("nav.artworks"), to: artworksLink.value },
    { key: "archive", label: t("nav.archive"), to: archiveLink.value },
    { key: "events", label: t("nav.events"), to: timelineLink.value },
    { key: "themes", label: t("nav.themes"), to: themesLink.value, section: true },
  ];
  if (!auth.isAuthenticated) return links;

  const staff: [boolean, string, string, RouteLocationRaw][] = [
    [true, "dashboard", t("nav.dashboard"), dashboardLink.value],
    [showProposals.value, "proposals", canReviewProposals.value ? t("proposals.queueTitle") : t("proposals.mineTitle"), proposalsLink.value],
    [auth.can("activity.view"), "activity", t("nav.activity"), activityLink.value],
    [auth.can("artists.manage"), "registry", t("nav.registry"), registryLink.value],
    [auth.can("artworks.manage"), "artworkRegistry", t("nav.artworkRegistry"), artworkRegistryLink.value],
    [auth.can("events.manage"), "eventsRegistry", t("events.nav"), eventsRegistryLink.value],
    [auth.can("materials.review"), "materials", t("submissions.title"), materialsLink.value],
    [auth.can("imports.manage"), "imports", t("nav.imports"), importsLink.value],
  ];
  return [...links, ...staff.filter(([show]) => show).map(([, key, label, to], n) => ({ key, label, to, muted: n > 0 }))];
});

/** The current page, or anything beneath it (an artist's page keeps "Artists" lit). */
function isCurrent(link: NavLink): boolean {
  if (link.section) return false;
  const target = router.resolve(link.to).path;
  return route.path === target || route.path.startsWith(`${target}/`);
}
const linkClass = (link: NavLink): string =>
  isCurrent(link)
    ? "text-accent underline decoration-accent decoration-2 underline-offset-8"
    : `${link.muted ? "text-ink-muted hover:text-ink" : "text-ink hover:text-accent"} transition-colors`;

const menuOpen = ref(false);
watch(() => route.fullPath, () => (menuOpen.value = false));
const closeOnEscape = (e: KeyboardEvent) => e.key === "Escape" && (menuOpen.value = false);
onMounted(() => window.addEventListener("keydown", closeOnEscape));
onBeforeUnmount(() => window.removeEventListener("keydown", closeOnEscape));

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
        <nav aria-label="Main" class="hidden items-center gap-7 text-sm font-medium md:flex" data-testid="main-nav">
          <RouterLink
            v-for="link in navLinks"
            :key="link.key"
            :to="link.to"
            :class="linkClass(link)"
            :aria-current="isCurrent(link) ? 'page' : undefined"
            :data-testid="`nav-${link.key}`"
            >{{ link.label }}</RouterLink
          >
        </nav>
        <div class="flex items-center justify-self-end gap-3">
          <button
            type="button"
            class="border border-ink px-3 py-1.5 text-sm font-medium text-ink md:hidden"
            :aria-expanded="menuOpen"
            aria-controls="mobile-menu"
            :aria-label="menuOpen ? $t('nav.closeMenu') : $t('nav.openMenu')"
            data-testid="menu-toggle"
            @click="menuOpen = !menuOpen"
          >
            {{ $t("nav.menu") }}
          </button>
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
      <nav v-if="menuOpen" id="mobile-menu" aria-label="Main" class="border-t border-line px-6 py-4 md:hidden" data-testid="mobile-menu">
        <ul class="flex flex-col">
          <li v-for="link in navLinks" :key="link.key" class="border-b border-line last:border-b-0">
            <RouterLink :to="link.to" class="block py-3 text-base font-medium" :class="linkClass(link)" :aria-current="isCurrent(link) ? 'page' : undefined">{{ link.label }}</RouterLink>
          </li>
        </ul>
      </nav>
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
            <RouterLink :to="methodologyLink" class="transition-colors hover:text-ink">{{
              $t("home.footer.methodology")
            }}</RouterLink>
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
            <RouterLink :to="researcherLink" class="transition-colors hover:text-ink">{{
              $t("home.footer.researchers")
            }}</RouterLink>
            <RouterLink :to="submitLink" class="transition-colors hover:text-ink">{{
              $t("home.footer.owners")
            }}</RouterLink>
            <a href="#" class="transition-colors hover:text-ink" @click.prevent>{{
              $t("home.footer.institutions")
            }}</a>
          </div>
        </nav>
      </div>
    </footer>
  </div>
</template>
