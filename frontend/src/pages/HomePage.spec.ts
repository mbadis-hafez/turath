import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import HomePage from "@/pages/HomePage.vue";
import { mountWithPlugins } from "@/test/utils";
import type { HomeOverview } from "@/types/home";

const api = vi.hoisted(() => ({ getHomeOverview: vi.fn() }));
vi.mock("@/api/home", () => api);

const overview: HomeOverview = {
  stats: { materials: 12, artists: 3, artworks: 20, sources: 4 },
  updated_at: null,
  popular_searches: [{ term: { ar: "أحمد المغلوث", en: "Ahmad Almaghlout" } }, { term: { ar: "يوسف جاها", en: null } }],
  themes: [], archive_feature: null, recent_archive_items: [], artists: [],
  places: [{ name: { ar: "الرياض", en: "Riyadh" }, materials_count: 9 }],
};

let router: Router;

async function mountHome(locale: "en" | "ar" = "en") {
  setActivePinia(createPinia());
  await router.push(`/${locale}`);
  const wrapper = mountWithPlugins(HomePage, { locale, router });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.getHomeOverview.mockReset().mockResolvedValue({ data: overview });
  const stub = { template: "<div />" };
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale", name: "home", component: HomePage },
      { path: "/:locale/search", name: "search", component: stub },
      { path: "/:locale/archive", name: "archive.records", component: stub },
      { path: "/:locale/timeline", name: "timeline", component: stub },
      { path: "/:locale/artists", name: "artists.index", component: stub },
      { path: "/:locale/artists/:slug", name: "artists.show", component: stub },
      { path: "/:locale/submit", name: "submit", component: stub },
      { path: "/:locale/about/methodology", name: "methodology", component: stub },
      { path: "/:locale/login", name: "login", component: stub },
    ],
  });
});

describe("HomePage search", () => {
  it("searches everything, not just the archive, for what the visitor typed", async () => {
    const wrapper = await mountHome();

    await wrapper.get("input[type=text]").setValue("  المغلوث ");
    await wrapper.get("form[role=search]").trigger("submit");
    await flushPromises();

    expect(router.currentRoute.value.name).toBe("search");
    expect(router.currentRoute.value.query).toEqual({ q: "المغلوث" });
  });

  it("opens the archive when the box is empty rather than an empty search", async () => {
    const wrapper = await mountHome();

    await wrapper.get("input[type=text]").setValue("   ");
    await wrapper.get("form[role=search]").trigger("submit");
    await flushPromises();

    expect(router.currentRoute.value.name).toBe("archive.records");
    expect(router.currentRoute.value.query).toEqual({});
  });

  it("runs a popular search in the visitor's language across everything", async () => {
    const wrapper = await mountHome("en");
    const chips = wrapper.findAll("button").filter((b) => b.text() === "Ahmad Almaghlout");
    expect(chips).toHaveLength(1);

    await chips[0].trigger("click");
    await flushPromises();

    expect(router.currentRoute.value.path).toBe("/en/search");
    expect(router.currentRoute.value.query.q).toBe("Ahmad Almaghlout");
  });

  it("falls back to the other language for a popular search that has only one", async () => {
    const wrapper = await mountHome("en");

    await wrapper.findAll("button").find((b) => b.text() === "يوسف جاها")!.trigger("click");
    await flushPromises();

    expect(router.currentRoute.value.query.q).toBe("يوسف جاها");
  });
});

describe("HomePage links", () => {
  it("sends a place to the archive filtered by that place, which is what the count describes", async () => {
    const wrapper = await mountHome("en");
    const link = wrapper.findAll("a").find((a) => a.text().includes("Riyadh"))!;

    expect(decodeURIComponent(link.attributes("href")!)).toBe("/en/archive?place=Riyadh");
  });

  it("sends the contribute buttons to the registration form and the methodology page", async () => {
    const wrapper = await mountHome("en");
    const hrefOf = (text: string) => wrapper.findAll("a").find((a) => a.text() === text)?.attributes("href");

    expect(hrefOf("Submit material")).toBe("/en/submit");
    expect(hrefOf("How we document sources")).toBe("/en/about/methodology");
  });
});
