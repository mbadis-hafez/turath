import {
  createRouter,
  createWebHistory,
  type Router,
  type RouterHistory,
  type RouteRecordRaw,
} from "vue-router";

import { applyLocale, getStoredLocale, isLocale, type AppLocale } from "@/i18n";
import { useAuthStore } from "@/stores/auth";
import { useDocumentTitle } from "@/composables/useDocumentTitle";

const routes: RouteRecordRaw[] = [
  {
    path: "/",
    redirect: () => `/${getStoredLocale()}`,
  },
  {
    path: "/:locale",
    name: "home",
    component: () => import("@/pages/HomePage.vue"),
    meta: { titleKey: "nav.home" },
  },
  {
    path: "/:locale/login",
    name: "login",
    component: () => import("@/pages/LoginPage.vue"),
    meta: { titleKey: "nav.login", guestOnly: true, bare: true },
  },
  {
    path: "/:locale/artists",
    name: "artists.index",
    component: () => import("@/pages/ArtistsPage.vue"),
    meta: { titleKey: "artists.title" },
  },
  {
    path: "/:locale/artists/:slug",
    name: "artists.show",
    component: () => import("@/pages/ArtistPage.vue"),
    meta: { titleFromData: true },
  },
  {
    path: "/:locale/artworks",
    name: "artworks.index",
    component: () => import("@/pages/ArtworksPage.vue"),
    meta: { titleKey: "artworks.title" },
  },
  {
    path: "/:locale/artworks/:id",
    name: "artworks.show",
    component: () => import("@/pages/ArtworkPage.vue"),
    meta: { titleFromData: true },
  },
  {
    path: "/:locale/archive",
    name: "archive.records",
    component: () => import("@/pages/ComingSoonPage.vue"),
    meta: { titleKey: "nav.archive" },
  },
  {
    path: "/:locale/dashboard",
    name: "dashboard",
    component: () => import("@/pages/DashboardPage.vue"),
    meta: { titleKey: "dashboard.title", requiresAuth: true },
  },
  {
    path: "/:locale/admin/activity",
    name: "admin.activity",
    component: () => import("@/pages/admin/ActivityPage.vue"),
    meta: {
      titleKey: "activity.title",
      requiresAuth: true,
      requiresPermission: "activity.view",
    },
  },
  {
    path: "/:locale/admin/artists",
    name: "admin.artists",
    component: () => import("@/pages/admin/ArtistsRegistryPage.vue"),
    meta: { titleKey: "curation.registry.title", requiresAuth: true, requiresPermission: "artists.manage" },
  },
  {
    path: "/:locale/admin/artists/new",
    name: "admin.artists.new",
    component: () => import("@/pages/admin/ArtistCreatePage.vue"),
    meta: { titleKey: "curation.create.title", requiresAuth: true, requiresPermission: "artists.manage" },
  },
  {
    path: "/:locale/admin/artists/:id(\\d+)",
    name: "admin.artists.show",
    component: () => import("@/pages/admin/ArtistCurationPage.vue"),
    meta: { titleKey: "curation.registry.title", requiresAuth: true, requiresPermission: "artists.manage" },
  },
  {
    path: "/:locale/admin/artworks",
    name: "admin.artworks",
    component: () => import("@/pages/admin/ArtworksRegistryPage.vue"),
    meta: { titleKey: "curation.artworkRegistry.title", requiresAuth: true, requiresPermission: "artworks.manage" },
  },
  {
    path: "/:locale/admin/imports",
    name: "admin.imports",
    component: () => import("@/pages/admin/ImportsPage.vue"),
    meta: {
      titleKey: "imports.title",
      requiresAuth: true,
      requiresPermission: "imports.manage",
    },
  },
  {
    path: "/:locale/admin/imports/:id",
    name: "admin.imports.show",
    component: () => import("@/pages/admin/ImportBatchPage.vue"),
    meta: {
      titleKey: "imports.review.title",
      requiresAuth: true,
      requiresPermission: "imports.manage",
    },
  },
  {
    path: "/:pathMatch(.*)*",
    name: "not-found",
    component: () => import("@/pages/NotFoundPage.vue"),
    meta: { titleKey: "errors.notFound" },
  },
];

export function currentRouteLocale(params: Record<string, unknown>): AppLocale {
  const value = params.locale;
  return isLocale(value) ? value : getStoredLocale();
}

export function createAppRouter(history: RouterHistory): Router {
  const router = createRouter({ history, routes });

  router.beforeEach(async (to) => {
    const localeParam = to.params.locale;

    // Unknown locale segment (e.g. /fr/...) → restart from the stored/default locale.
    if (typeof localeParam === "string" && !isLocale(localeParam)) {
      const rest = to.path.slice(`/${localeParam}`.length);
      return { path: `/${getStoredLocale()}${rest}` };
    }

    const locale = currentRouteLocale(to.params);
    applyLocale(locale);
    useDocumentTitle(
      typeof to.meta.titleKey === "string" ? to.meta.titleKey : null,
    );

    const auth = useAuthStore();
    if (!auth.initialized) {
      await auth.fetchUser();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return {
        name: "login",
        params: { locale },
        query: { redirect: to.fullPath },
      };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
      return { name: "dashboard", params: { locale } };
    }

    return true;
  });

  return router;
}

export const router = createAppRouter(createWebHistory("/"));
