import { beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import { createMemoryHistory, createRouter, type Router } from "vue-router";
import { createPinia, setActivePinia } from "pinia";
import { useAuthStore } from "@/stores/auth";

import ArtworkPage from "@/pages/ArtworkPage.vue";
import { i18n, applyLocale } from "@/i18n";
import type { Artwork } from "@/types/artwork";

vi.mock("@/api/artworks", () => ({ getArtwork: vi.fn() }));

import { getArtwork } from "@/api/artworks";

function makeArtwork(): Artwork {
  return {
    id: 42,
    legacy_ref: null,
    title: { ar: "لوحة", en: "Untitled composition" },
    is_untitled: false,
    artist: { id: 7, slug: "inji-efflatoun", name: { ar: "إنجي أفلاطون", en: "Inji Efflatoun" } },
    attribution_certainty: "confirmed",
    category: "painting",
    medium: { ar: null, en: null },
    creation: null,
    dimensions: { height_cm: null, width_cm: null, depth_cm: null, raw: null },
    frame_dimensions: null,
    weight_kg: null,
    signed: "unknown",
    edition: null,
    holder: null,
    holder_inventory_no: null,
    notes: { ar: null, en: null },
    publication_status: "published",
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
  };
}

let router: Router;

async function mountPage(path: string, locale: "ar" | "en" = "ar", permissions: string[] | null = null) {
  const pinia = createPinia();
  setActivePinia(pinia);
  if (permissions !== null) {
    useAuthStore().$patch({ user: { id: 3, name: "Nora", email: "n@x", roles: [], permissions } as never, initialized: true });
  }
  applyLocale(locale);
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/artworks/:id", name: "artworks.show", component: ArtworkPage },
      { path: "/:locale/artists/:slug", name: "artists.show", component: { template: "<div />" } },
      { path: "/:locale/login", name: "login", component: { template: "<div />" } },
      { path: "/:locale/suggest/:type/:id", name: "suggest", component: { template: "<div />" } },
    ],
  });
  await router.push(path);
  await router.isReady();

  const wrapper = mount(ArtworkPage, {
    global: { plugins: [pinia, i18n, router] },
  });
  await flushPromises();
  return wrapper;
}

describe("ArtworkPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(getArtwork).mockResolvedValue({ data: makeArtwork() });
  });

  it("sends a signed-out visitor to sign in before correcting, and a contributor straight to the suggestion form", async () => {
    const visitor = await mountPage("/en/artworks/42", "en");
    const href = visitor.get("[data-testid=send-correction]").attributes("href")!;
    expect(href).toContain("/en/login");
    expect(decodeURIComponent(href)).toContain("redirect=/en/suggest/artworks/42");

    const contributor = await mountPage("/en/artworks/42", "en", ["proposals.submit"]);
    expect(contributor.get("[data-testid=send-correction]").attributes("href")).toBe("/en/suggest/artworks/42");
  });

  it("hides the correction button from a signed-in user who cannot suggest", async () => {
    const reader = await mountPage("/en/artworks/42", "en", []);
    expect(reader.find("[data-testid=send-correction]").exists()).toBe(false);
    expect(reader.get("[data-testid=correction-box]").text()).toContain("Know more about this artwork?");
  });
});
