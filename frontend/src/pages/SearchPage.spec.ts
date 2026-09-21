import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import SearchPage from "@/pages/SearchPage.vue";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";

const api = vi.hoisted(() => ({ listArtists: vi.fn(), listArtworks: vi.fn(), listArchiveItems: vi.fn(), listEvents: vi.fn() }));
vi.mock("@/api/artists", () => ({ listArtists: api.listArtists }));
vi.mock("@/api/artworks", () => ({ listArtworks: api.listArtworks }));
vi.mock("@/api/archive", () => ({ listArchiveItems: api.listArchiveItems }));
vi.mock("@/api/events", () => ({ listEvents: api.listEvents }));

const meta = (total: number) => ({ current_page: 1, last_page: 1, per_page: 6, total, from: null, to: null });
const found = (data: unknown[], total = data.length) => ({ data, links: [], meta: meta(total) });
const none = found([]);

const artist = (id: number) => ({ id, slug: `a${id}`, name: { ar: `فنان ${id}`, en: null }, birth: null, death: null, living_status: "unknown", verified_status: "verified", legacy_code: null });
const artwork = (id: number) => ({
  id, legacy_ref: null, title: { ar: `عمل ${id}`, en: null }, is_untitled: false, artist: null, attribution_certainty: "confirmed", category: "painting",
  medium: { ar: null, en: null }, creation: null, dimensions: { height_cm: null, width_cm: null, depth_cm: null, raw: null },
});
const material = (id: number) => ({ id, legacy_ref: `A${id}`, item_type: "article", title: { ar: `مادة ${id}`, en: null }, content: null, access_level: "public", publication_status: "published", restricted: false });
const event = (id: number) => ({ id, event_type: "exhibition", title: { ar: `معرض ${id}`, en: null }, start: null, end: null, venue_name: "دار الفنون", city: "جدة", publication_status: "published", participant_count: 0 });

let router: Router;

async function mountAt(path: string) {
  setActivePinia(createPinia());
  await router.push(path);
  const wrapper = mountWithPlugins(SearchPage, { locale: "en", router });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  for (const fn of Object.values(api)) fn.mockReset().mockResolvedValue(none);
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/search", name: "search", component: SearchPage },
      { path: "/:locale/artists", name: "artists.index", component: { template: "<div />" } },
      { path: "/:locale/artists/:slug", name: "artists.show", component: { template: "<div />" } },
      { path: "/:locale/artworks", name: "artworks.index", component: { template: "<div />" } },
      { path: "/:locale/artworks/:id", name: "artworks.show", component: { template: "<div />" } },
      { path: "/:locale/archive", name: "archive.records", component: { template: "<div />" } },
      { path: "/:locale/events/:id", name: "events.show", component: { template: "<div />" } },
    ],
  });
});
afterEach(() => vi.useRealTimers());

describe("SearchPage", () => {
  it("makes no requests without a real term, and says what to do", async () => {
    const empty = await mountAt("/en/search");
    expect(empty.get("[data-testid=prompt]").text()).toContain("Search across artists");

    const oneLetter = await mountAt("/en/search?q=أ");
    expect(oneLetter.get("[data-testid=summary]").text()).toContain("at least 2 characters");
    expect(Object.values(api).every((fn) => fn.mock.calls.length === 0)).toBe(true);
  });

  it("searches artists, artworks, archive material and events for the same term", async () => {
    api.listArtists.mockResolvedValue(found([artist(1)]));
    api.listArtworks.mockResolvedValue(found([artwork(1), artwork(2)]));
    api.listArchiveItems.mockResolvedValue(found([material(1)]));
    api.listEvents.mockResolvedValue(found([event(1)]));
    const wrapper = await mountAt("/en/search?q=المغلوث");

    for (const fn of [api.listArtists, api.listArtworks, api.listArchiveItems, api.listEvents]) {
      expect(fn.mock.calls[0][0]).toMatchObject({ q: "المغلوث", per_page: 6 });
    }
    expect(wrapper.findAll("[data-testid=result-artist]")).toHaveLength(1);
    expect(wrapper.findAll("[data-testid=result-artwork]")).toHaveLength(2);
    expect(wrapper.findAll("[data-testid=result-archive]")).toHaveLength(1);
    expect(wrapper.get("[data-testid=result-event]").text()).toContain("معرض 1");
    expect(wrapper.get("[data-testid=summary]").text()).toBe("5 results");
  });

  it("shows only the kinds that have results", async () => {
    api.listArtworks.mockResolvedValue(found([artwork(1)]));
    const wrapper = await mountAt("/en/search?q=تكوين");

    expect(wrapper.find("[data-testid=section-artworks]").exists()).toBe(true);
    for (const kind of ["artists", "archive", "events"]) expect(wrapper.find(`[data-testid=section-${kind}]`).exists()).toBe(false);
  });

  it("offers 'view all' with the term only where there are more results than shown", async () => {
    api.listArtists.mockResolvedValue(found([artist(1), artist(2)], 2));
    api.listArtworks.mockResolvedValue(found([artwork(1), artwork(2)], 40));
    api.listEvents.mockResolvedValue(found([event(1)], 9));
    const wrapper = await mountAt("/en/search?q=معرض");

    expect(wrapper.find("[data-testid=section-artists] [data-testid=view-all]").exists()).toBe(false);
    expect(decodeURIComponent(wrapper.get("[data-testid=section-artworks] [data-testid=view-all]").attributes("href")!)).toBe("/en/artworks?q=معرض");
    // Events have no list page to send people to, so no dead link.
    expect(wrapper.find("[data-testid=section-events] [data-testid=view-all]").exists()).toBe(false);
  });

  it("keeps the other results when one source fails, retries only that one, and never calls it 'no results'", async () => {
    api.listArtists.mockResolvedValue(found([artist(1)]));
    api.listArchiveItems.mockRejectedValueOnce(new ApiError("server", "down", { status: 500 }));
    const wrapper = await mountAt("/en/search?q=أحمد");

    expect(wrapper.findAll("[data-testid=result-artist]")).toHaveLength(1);
    expect(wrapper.find("[data-testid=no-results]").exists()).toBe(false);
    const archive = wrapper.get("[data-testid=section-archive]");
    expect(archive.text()).toContain("Something went wrong");

    api.listArchiveItems.mockResolvedValueOnce(found([material(1)]));
    const artistCalls = api.listArtists.mock.calls.length;
    await archive.get("button").trigger("click");
    await flushPromises();

    expect(wrapper.findAll("[data-testid=result-archive]")).toHaveLength(1);
    expect(api.listArtists.mock.calls.length).toBe(artistCalls);
  });

  it("says so, with ways onward, when nothing matches anywhere", async () => {
    const wrapper = await mountAt("/en/search?q=zzzz");

    expect(wrapper.get("[data-testid=no-results]").text()).toContain("No matching results");
    expect(wrapper.get("[data-testid=no-results]").findAll("a").map((a) => a.attributes("href"))).toEqual(["/en/artists", "/en/archive"]);
  });

  it("puts what you type in the URL after a pause, then searches once for it; clearing returns to the prompt", async () => {
    vi.useFakeTimers();
    const wrapper = await mountAt("/en/search");
    const box = wrapper.get("input[type=text]");

    await box.setValue("مغ");
    await box.setValue("مغلوث");
    await vi.advanceTimersByTimeAsync(400);
    await flushPromises();

    expect(router.currentRoute.value.query.q).toBe("مغلوث");
    expect(api.listArtists).toHaveBeenCalledTimes(1);

    await box.setValue("");
    await vi.advanceTimersByTimeAsync(400);
    await flushPromises();
    expect(router.currentRoute.value.query.q).toBeUndefined();
    expect(wrapper.find("[data-testid=prompt]").exists()).toBe(true);
  });

  it("lets the newest search win when an older response arrives late", async () => {
    let releaseOld!: (v: unknown) => void;
    api.listArtists
      .mockImplementationOnce(() => new Promise((resolve) => { releaseOld = resolve; }))
      .mockResolvedValueOnce(found([artist(2)]));
    const wrapper = await mountAt("/en/search?q=قديم");

    await router.replace({ query: { q: "جديد" } });
    await flushPromises();
    releaseOld(found([artist(1)]));
    await flushPromises();

    const names = wrapper.findAll("[data-testid=result-artist]").map((r) => r.text());
    expect(names).toHaveLength(1);
    expect(names[0]).toContain("فنان 2");
  });
});
