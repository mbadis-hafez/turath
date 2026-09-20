import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import TimelinePage from "@/pages/TimelinePage.vue";
import { mountWithPlugins } from "@/test/utils";

const api = vi.hoisted(() => ({ getTimeline: vi.fn(), listThemes: vi.fn() }));
vi.mock("@/api/events", () => ({ getTimeline: api.getTimeline }));
vi.mock("@/api/artistCuration", () => ({ listThemes: api.listThemes }));

const buckets = [
  { year: 1953, entries: [{ kind: "artist_lifespan", id: 1, slug: "ibrahim", year_from: 1953, year_to: null, display: "1953", name: { ar: "إبراهيم بوقس", en: null } }] },
  { year: 1979, entries: [
    { kind: "event", id: 4, year_from: 1979, year_to: 1979, display: "1979", event_type: "exhibition", title: { ar: "المعرض الأول", en: null } },
    { kind: "artwork", id: 8, year_from: 1979, year_to: 1979, display: "1979", title: { ar: "مباني", en: null }, artist: { ar: "بوقس", en: null } },
  ] },
  { year: 1984, entries: [{ kind: "event", id: 5, year_from: 1984, year_to: null, display: "1984", event_type: "symposium", title: { ar: "ندوة", en: null } }] },
];

let router: Router;

async function mountPage() {
  const pinia = createPinia();
  setActivePinia(pinia);
  await router.push("/en/timeline");
  const wrapper = mountWithPlugins(TimelinePage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.getTimeline.mockReset().mockResolvedValue({ data: buckets, meta: { total: 4 } });
  api.listThemes.mockReset().mockResolvedValue({ data: [{ id: 2, label: { ar: "التأسيس", en: "Founding" } }] });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/timeline", name: "timeline", component: TimelinePage },
      { path: "/:locale/events/:id", name: "events.show", component: { template: "<div />" } },
      { path: "/:locale/artists/:slug", name: "artists.show", component: { template: "<div />" } },
      { path: "/:locale/artworks/:id", name: "artworks.show", component: { template: "<div />" } },
    ],
  });
});

describe("TimelinePage", () => {
  it("asks only for events by default and renders them as one kind of mark", async () => {
    const wrapper = await mountPage();

    expect(api.getTimeline.mock.calls[0][0]).toEqual({ type: ["event"], theme_id: [] });
    expect(wrapper.findAll("[data-testid=year-bucket]")).toHaveLength(3);
  });

  it("renders events, lifespans and artworks as visually distinct marks and links each to its own page", async () => {
    const wrapper = await mountPage();
    const marks = (kind: string) => wrapper.get(`[data-kind=${kind}] span[aria-hidden]`).classes().join(" ");

    expect(new Set([marks("event"), marks("artist_lifespan"), marks("artwork")]).size).toBe(3);
    expect(wrapper.get("[data-kind=artist_lifespan] a").attributes("href")).toContain("/artists/ibrahim");
    expect(wrapper.get("[data-kind=artwork] a").attributes("href")).toContain("/artworks/8");
    expect(wrapper.get("[data-kind=event] a").attributes("href")).toContain("/events/4");
  });

  it("re-queries with the overlay kinds and the chosen theme", async () => {
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=include-lifespans]").setValue(true);
    await wrapper.get("[data-testid=include-artworks]").setValue(true);
    await wrapper.get("[data-testid=theme-filter]").setValue("2");
    await flushPromises();

    expect(api.getTimeline.mock.lastCall![0]).toEqual({ type: ["event", "artist_lifespan", "artwork"], theme_id: [2] });
  });

  it("groups years into decade tabs", async () => {
    const wrapper = await mountPage();

    expect(wrapper.findAll("[role=tab]").map((t) => t.text())).toEqual(["All", "1950s", "1970s", "1980s"]);
    await wrapper.findAll("[role=tab]")[3].trigger("click");
    expect(wrapper.findAll("[data-testid=year-bucket]")).toHaveLength(1);
    expect(wrapper.get("[data-testid=year-bucket]").text()).toContain("1984");
  });
});
