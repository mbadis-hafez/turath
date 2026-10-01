import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtworksPage from "@/pages/ArtworksPage.vue";
import { mountWithPlugins } from "@/test/utils";
import type { ArtworkListItem } from "@/types/artwork";

const api = vi.hoisted(() => ({ listArtworks: vi.fn() }));
vi.mock("@/api/artworks", () => api);
const artistsApi = vi.hoisted(() => ({ listArtists: vi.fn() }));
vi.mock("@/api/artists", () => artistsApi);

function artwork(id: number, patch: Partial<ArtworkListItem> = {}): ArtworkListItem {
  return {
    id, legacy_ref: null, title: { ar: `عمل ${id}`, en: `Work ${id}` }, is_untitled: false,
    artist: { id: 1, slug: "ahmad", name: { ar: "أحمد المغلوث", en: "Ahmad Almaghlout" } },
    attribution_certainty: "confirmed", category: "painting", medium: { ar: "زيت على قماش", en: "Oil on canvas" },
    creation: { display: "1988", year_from: 1988, year_to: 1988, calendar: "gregorian", certainty: "exact" },
    dimensions: { height_cm: null, width_cm: null, depth_cm: null, raw: null },
    image_url: null,
    ...patch,
  };
}

const page = (data: ArtworkListItem[], total = data.length) => ({
  data, links: [], meta: { current_page: 1, last_page: 1, per_page: 24, total, from: null, to: null },
});

let router: Router;

async function mountAt(path = "/en/artworks") {
  const pinia = createPinia();
  setActivePinia(pinia);
  await router.push(path);
  const wrapper = mountWithPlugins(ArtworksPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.listArtworks.mockReset().mockResolvedValue(page([artwork(1), artwork(2, { creation: { display: "1980s", year_from: 1980, year_to: 1989, calendar: "gregorian", certainty: "circa" } })], 8));
  artistsApi.listArtists.mockReset().mockResolvedValue({ data: [], links: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0, from: null, to: null } });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/artworks", name: "artworks.records", component: ArtworksPage },
      { path: "/:locale/artworks/:id", name: "artworks.show", component: { template: "<div />" } },
    ],
  });
});

describe("ArtworksPage", () => {
  it("shows the hero title, intro and the total count", async () => {
    const wrapper = await mountAt();

    expect(wrapper.get("h1").text()).toBe("Artworks");
    expect(wrapper.get("[data-testid=summary]").text()).toContain("8");
    expect(wrapper.get("[data-testid=results]").text()).toContain("Work 1");
  });

  it("renders a card per artwork", async () => {
    const wrapper = await mountAt();
    expect(wrapper.findAll("[data-testid=results] > a")).toHaveLength(2);
  });

  it("filters by category through the sidebar", async () => {
    const wrapper = await mountAt();

    await wrapper.findAll("[data-testid=facet-option]")[0].trigger("change");
    await flushPromises();

    expect(router.currentRoute.value.query.category).toBe("painting");
    expect(api.listArtworks.mock.calls.at(-1)?.[0]).toMatchObject({ category: "painting" });
  });

  it("clears all filters", async () => {
    const wrapper = await mountAt("/en/artworks?category=painting&q=oud");
    await flushPromises();

    await wrapper.get("[data-testid=clear-filters]").trigger("click");
    await flushPromises();

    expect(router.currentRoute.value.query).toEqual({});
  });

  it("changes sort order", async () => {
    const wrapper = await mountAt();

    await wrapper.get("[data-testid=sort]").setValue("title_ar");
    await flushPromises();

    expect(router.currentRoute.value.query.sort).toBe("title_ar");
  });

  it("shows an empty state with a clear-filters action when nothing matches", async () => {
    api.listArtworks.mockResolvedValue(page([], 0));
    const wrapper = await mountAt("/en/artworks?q=nothing");

    expect(wrapper.text()).toContain("No matching artworks");
    await wrapper.get("button").trigger("click");
    await flushPromises();
    expect(router.currentRoute.value.query).toEqual({});
  });
});
