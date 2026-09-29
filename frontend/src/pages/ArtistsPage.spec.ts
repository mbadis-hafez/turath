import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  RouterLink,
  createMemoryHistory,
  createRouter,
  type Router,
} from "vue-router";

import ArtistsPage from "@/pages/ArtistsPage.vue";
import { mountWithPlugins } from "@/test/utils";
import type {
  ArtistFacets,
  ArtistLetters,
  ArtistListItem,
} from "@/types/artist";

const api = vi.hoisted(() => ({ listArtists: vi.fn() }));
vi.mock("@/api/artists", () => api);

const artist = (
  id: number,
  nameAr: string,
  nameEn: string,
  patch: Partial<ArtistListItem> = {},
): ArtistListItem => ({
  id,
  slug: `artist-${id}`,
  name: { ar: nameAr, en: nameEn },
  city: { ar: "القاهرة", en: "Cairo" },
  birth: null,
  death: null,
  living_status: "unknown",
  verified_status: "verified",
  portrait_url: null,
  materials_count: 1,
  ...patch,
});

const facets: ArtistFacets = {
  city: [{ value: "القاهرة", count: 2 }],
  theme_id: [{ value: 3, count: 2, label: { ar: "تجريد", en: "Abstraction" } }],
  item_type: [{ value: "image", count: 2 }],
};

const letters: ArtistLetters = { ar: ["أ", "ب"], en: ["A", "B"] };

const pageResponse = (
  data: ArtistListItem[],
  total = data.length,
  withFacets = true,
  currentPage = 1,
  lastPage = 1,
) => ({
  data,
  links: [],
  meta: {
    current_page: currentPage,
    last_page: lastPage,
    per_page: 100,
    total,
    from: 1,
    to: data.length,
    ...(withFacets ? { facets, letters, materials_total: total } : {}),
  },
});

let router: Router;
let pinia: ReturnType<typeof createPinia>;

async function mountAt(path = "/ar/artists") {
  await router.push(path);
  const wrapper = mountWithPlugins(ArtistsPage, {
    locale: "ar",
    router,
    pinia,
  });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  pinia = createPinia();
  setActivePinia(pinia);
  api.listArtists
    .mockReset()
    .mockResolvedValue(
      pageResponse([artist(1, "أحمد", "Ahmad"), artist(2, "بدر", "Badr")]),
    );
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: "/:locale/artists",
        name: "artists.index",
        component: ArtistsPage,
      },
      { path: "/:locale/artists/:slug", name: "artists.show", component: {} },
      { path: "/:locale/submit", name: "submit", component: {} },
      { path: "/:locale", name: "home", component: {} },
    ],
  });
});

afterEach(() => vi.useRealTimers());

describe("ArtistsPage", () => {
  it("groups artists by letter in alphabetical sort", async () => {
    const wrapper = await mountAt();

    const groups = wrapper.findAll('[data-testid="letter-group"]');
    expect(groups).toHaveLength(2);
    expect(groups[0].text()).toContain("أ");
    expect(groups[0].text()).toContain("أحمد");
    expect(groups[1].text()).toContain("ب");
    expect(groups[1].text()).toContain("بدر");
  });

  it("shows a flat grid and hides the letter bar in most-materials sort", async () => {
    const wrapper = await mountAt("/ar/artists?sort=materials");

    expect(wrapper.find('[data-testid="letter-bar"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="flat-grid"]').exists()).toBe(true);
    expect(wrapper.findAll('[data-testid="letter-group"]')).toHaveLength(0);
  });

  it("passes URL filters to the API", async () => {
    await mountAt("/ar/artists?city=القاهرة&type=image&theme=3");

    expect(api.listArtists.mock.lastCall![0]).toMatchObject({
      city: "القاهرة",
      item_type: "image",
      theme_id: 3,
      page: 1,
      include_facets: 1,
    });
  });

  it("appends the next page on load more", async () => {
    api.listArtists
      .mockResolvedValueOnce(
        pageResponse([artist(1, "أحمد", "Ahmad")], 2, true, 1, 2),
      )
      .mockResolvedValueOnce(
        pageResponse([artist(2, "بدر", "Badr")], 2, false, 2, 2),
      );

    const wrapper = await mountAt();
    expect(wrapper.findAll('[data-testid="letter-group"]')).toHaveLength(1);

    await wrapper.find('[data-testid="load-more"]').trigger("click");
    await flushPromises();

    expect(wrapper.findAll('[data-testid="letter-group"]')).toHaveLength(2);
  });

  it("shows an empty state with a clear-filters action", async () => {
    api.listArtists.mockResolvedValue(pageResponse([], 0));
    const wrapper = await mountAt("/ar/artists?city=nowhere");

    expect(wrapper.find('[data-testid="skeleton"]').exists()).toBe(false);
    expect(wrapper.text()).toContain("لا توجد نتائج مطابقة");

    await wrapper.find('[data-testid="clear-filters"]').trigger("click");
    await flushPromises();

    expect(router.currentRoute.value.query).toEqual({});
  });

  it("surfaces errors and retries", async () => {
    api.listArtists.mockRejectedValueOnce(new Error("boom"));
    const wrapper = await mountAt();

    expect(wrapper.find('[role="alert"]').exists()).toBe(true);

    api.listArtists.mockResolvedValueOnce(
      pageResponse([artist(1, "أحمد", "Ahmad")]),
    );
    await wrapper.find('[role="alert"] button').trigger("click");
    await flushPromises();

    expect(wrapper.find('[data-testid="letter-group"]').exists()).toBe(true);
  });

  it("links the CTA to the submit route", async () => {
    const wrapper = await mountAt();
    const links = wrapper.findAllComponents(RouterLink).filter((c) => {
      const to = c.props("to");
      return typeof to === "object" && "name" in to && to.name === "submit";
    });
    expect(links).toHaveLength(1);
    expect(links[0].props("to")).toMatchObject({
      name: "submit",
      params: { locale: "ar" },
    });
  });
});
