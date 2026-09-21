import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArchivePage from "@/pages/ArchivePage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { ArchiveFacets, ArchiveItem } from "@/types/archive";

const api = vi.hoisted(() => ({ listArchiveItems: vi.fn() }));
vi.mock("@/api/archive", () => api);

const item = (id: number, patch: Partial<ArchiveItem> = {}): ArchiveItem => ({
  id, legacy_ref: `ARC-${id}`, item_type: "article", title: { ar: `مادة ${id}`, en: null },
  content: { display: "1978", year_from: 1978, year_to: 1978, calendar: "gregorian", certainty: "exact" },
  access_level: "public", publication_status: "published", restricted: false,
  artists: [{ id: 1, slug: "ahmad", name: { ar: "أحمد المغلوث", en: null } }], place: { ar: "الرياض", en: null }, ...patch,
});

const facets: ArchiveFacets = {
  item_type: [{ value: "article", count: 7 }, { value: "image", count: 4 }],
  place: [{ value: "الأحساء", count: 6 }, { value: "الرياض", count: 9 }],
  theme_id: [{ value: 2, count: 4, label: { ar: "نشأة الحركة", en: "Founding" } }],
  access: [{ value: "full", count: 5 }, { value: "preview", count: 7 }],
};

const page = (data: ArchiveItem[], total = data.length, withFacets = true) => ({
  data, links: [], meta: { current_page: 1, last_page: 1, per_page: 12, total, from: null, to: null, ...(withFacets ? { facets } : {}) },
});

let router: Router;
let pinia: ReturnType<typeof createPinia>;

function signIn(permissions: string[] | null) {
  pinia = createPinia();
  setActivePinia(pinia);
  if (permissions) useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: [], permissions } as never, initialized: true });
}

async function mountAt(path = "/en/archive") {
  await router.push(path);
  const wrapper = mountWithPlugins(ArchivePage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  signIn(null);
  api.listArchiveItems.mockReset().mockResolvedValue(page([item(1), item(2, { item_type: "image", restricted: true, content: null, artists: [], place: undefined })], 12));
  router = createRouter({ history: createMemoryHistory(), routes: [{ path: "/:locale/archive", name: "archive.records", component: ArchivePage }] });
});
afterEach(() => vi.useRealTimers());

describe("ArchivePage", () => {
  it("shows the total, cards with their artist and place, and marks restricted items", async () => {
    const wrapper = await mountAt();

    expect(wrapper.get("[data-testid=summary]").text()).toContain("12 items");
    const cards = wrapper.findAll("[data-testid=archive-card]");
    expect(cards).toHaveLength(2);
    expect(cards[0].get("[data-testid=card-byline]").text()).toBe("أحمد المغلوث · الرياض");
    expect(cards[0].get("[data-testid=card-year]").text()).toBe("1978");
    expect(cards[0].find("[data-testid=restricted-badge]").exists()).toBe(false);
    expect(cards[1].find("[data-testid=restricted-badge]").exists()).toBe(true);
    expect(cards[1].get("[data-testid=card-year]").text()).toBe("n/a");
    expect(cards[1].find("[data-testid=card-byline]").exists()).toBe(false);
  });

  it("asks for facets with the first page and lists every group with its counts", async () => {
    const wrapper = await mountAt();

    expect(api.listArchiveItems.mock.calls[0][0]).toMatchObject({ page: 1, include_facets: 1, item_type: [], place: [], theme_id: [], access: [] });
    expect(wrapper.get("[data-testid=facet-type]").text()).toContain("Article");
    expect(wrapper.get("[data-testid=facet-type]").text()).toContain("7");
    expect(wrapper.get("[data-testid=facet-place]").text()).toContain("الرياض");
    expect(wrapper.get("[data-testid=facet-theme]").text()).toContain("Founding");
    expect(wrapper.get("[data-testid=facet-access]").text()).toContain("Fully available");
    expect(wrapper.get("[data-testid=facet-access]").text()).toContain("Preview only");
  });

  it("puts a ticked option in the URL and refetches with it, then unticks it", async () => {
    const wrapper = await mountAt();

    await wrapper.get("[data-testid=facet-type] [data-testid=facet-option]").setValue(true);
    await flushPromises();
    expect(router.currentRoute.value.query.type).toEqual(["article"]);
    expect(api.listArchiveItems.mock.lastCall![0]).toMatchObject({ item_type: ["article"], page: 1, include_facets: 1 });

    await wrapper.findAll("[data-testid=facet-type] [data-testid=facet-option]")[1].setValue(true);
    await flushPromises();
    expect(api.listArchiveItems.mock.lastCall![0].item_type).toEqual(["article", "image"]);

    await wrapper.get("[data-testid=facet-type] [data-testid=facet-option]").setValue(false);
    await flushPromises();
    expect(api.listArchiveItems.mock.lastCall![0].item_type).toEqual(["image"]);
  });

  it("keeps a ticked option listed with a zero when nothing else matches it any more", async () => {
    api.listArchiveItems.mockResolvedValue(page([item(1)], 1, true));
    const wrapper = await mountAt("/en/archive?place=جدة&access=preview");

    const place = wrapper.get("[data-testid=facet-place]");
    expect(place.text()).toContain("جدة");
    const row = place.findAll("li").find((li) => li.text().includes("جدة"))!;
    expect(row.get("[data-testid=facet-count]").text()).toBe("0");
    expect((row.get("input").element as HTMLInputElement).checked).toBe(true);
  });

  it("clears every filter at once but keeps the chosen view", async () => {
    const wrapper = await mountAt("/en/archive?type=image&place=الرياض&theme=2&access=full&view=list");
    expect(wrapper.find("[data-testid=clear-filters]").exists()).toBe(true);

    await wrapper.get("[data-testid=clear-filters]").trigger("click");
    await flushPromises();

    expect(router.currentRoute.value.query).toEqual({ view: "list" });
    expect(api.listArchiveItems.mock.lastCall![0]).toMatchObject({ item_type: [], place: [], theme_id: [], access: [] });
  });

  it("loads more into the same list without asking for facets again, and hides the button once everything is shown", async () => {
    api.listArchiveItems
      .mockResolvedValueOnce(page([item(1), item(2)], 3))
      .mockResolvedValueOnce(page([item(2), item(3)], 3, false));
    const wrapper = await mountAt();

    await wrapper.get("[data-testid=load-more]").trigger("click");
    await flushPromises();

    expect(api.listArchiveItems.mock.lastCall![0]).toMatchObject({ page: 2 });
    expect(api.listArchiveItems.mock.lastCall![0].include_facets).toBeUndefined();
    // Item 2 came back on both pages and must appear once.
    expect(wrapper.findAll("[data-testid=archive-card]")).toHaveLength(3);
    expect(wrapper.find("[data-testid=load-more]").exists()).toBe(false);
    // Facets from the first page are still on screen.
    expect(wrapper.get("[data-testid=facet-type]").text()).toContain("Article");
  });

  it("switches between grid and list, and reflects it in the URL", async () => {
    const wrapper = await mountAt();
    expect(wrapper.get("[data-testid=results]").attributes("data-view")).toBe("grid");

    await wrapper.get("[data-testid=view-list]").trigger("click");
    await flushPromises();

    expect(router.currentRoute.value.query.view).toBe("list");
    expect(wrapper.get("[data-testid=results]").attributes("data-view")).toBe("list");
    expect(wrapper.get("[data-testid=archive-card]").attributes("data-layout")).toBe("list");
    expect(wrapper.get("[data-testid=view-list]").attributes("aria-pressed")).toBe("true");
  });

  it("debounces the search box into one request", async () => {
    vi.useFakeTimers();
    const wrapper = await mountAt();
    const calls = api.listArchiveItems.mock.calls.length;

    await wrapper.get("[data-testid=search]").setValue("مع");
    await wrapper.get("[data-testid=search]").setValue("معرض");
    await vi.advanceTimersByTimeAsync(400);
    await flushPromises();

    expect(api.listArchiveItems.mock.calls.length).toBe(calls + 1);
    expect(api.listArchiveItems.mock.lastCall![0].q).toBe("معرض");
  });

  it("offers the unpublished toggle only to archive managers", async () => {
    expect((await mountAt()).find("[data-testid=include-unpublished]").exists()).toBe(false);

    signIn(["archive.manage"]);
    const wrapper = await mountAt();
    await wrapper.get("[data-testid=include-unpublished]").setValue(true);
    await flushPromises();
    expect(api.listArchiveItems.mock.lastCall![0].status).toBe("all");
  });

  it("notes an artist filter arriving from an artist page and lets the visitor clear it", async () => {
    const wrapper = await mountAt("/en/archive?artist_id=36");
    expect(api.listArchiveItems.mock.calls[0][0].artist_id).toBe(36);
    expect(wrapper.get("[data-testid=artist-filter]").text()).toContain("one artist");

    await wrapper.get("[data-testid=clear-artist]").trigger("click");
    await flushPromises();
    expect(router.currentRoute.value.query.artist_id).toBeUndefined();
  });

  it("shows an empty state with a way back when filters match nothing", async () => {
    api.listArchiveItems.mockResolvedValue(page([], 0));
    const wrapper = await mountAt("/en/archive?type=poster");

    expect(wrapper.text()).toContain("No archival items found");
    await wrapper.get("button.bg-accent").trigger("click");
    await flushPromises();
    expect(router.currentRoute.value.query.type).toBeUndefined();
  });
});
