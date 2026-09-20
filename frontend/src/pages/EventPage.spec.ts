import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import EventPage from "@/pages/EventPage.vue";
import { mountWithPlugins } from "@/test/utils";
import type { EventDetail } from "@/types/event";

const api = vi.hoisted(() => ({ getEvent: vi.fn() }));
vi.mock("@/api/events", () => api);

function detail(patch: Partial<EventDetail> = {}): EventDetail {
  return {
    id: 3, event_type: "award", title: { ar: "جائزة الشراع الذهبي", en: null }, start: { display: null, year_from: 1988, year_to: 1988, calendar: "gregorian", certainty: "exact" },
    end: null, venue_name: "الكويت", city: null, publication_status: "published", description: { ar: null, en: null }, date_note: null, access_level: "public",
    holder: null, themes: [], archive_items: [],
    participants: [
      { id: 1, role: "awardee", note: "الجائزة الأولى", kind: "artist", entity: { id: 5, slug: "bakr", name: { ar: "بكر شيخون", en: null } } },
      { id: 2, role: "juror", note: null, kind: "artist", entity: { id: 6, slug: "x", name: { ar: "محكّم", en: null } } },
      { id: 3, role: "exhibited_work", note: null, kind: "artwork", entity: { id: 9, title: { ar: "تكوين", en: null } } },
    ],
    ...patch,
  };
}

let router: Router;

async function mountPage() {
  const pinia = createPinia();
  setActivePinia(pinia);
  await router.push("/en/events/3");
  const wrapper = mountWithPlugins(EventPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.getEvent.mockReset().mockResolvedValue({ data: detail() });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/events/:id", name: "events.show", component: EventPage },
      { path: "/:locale", name: "home", component: { template: "<div />" } },
      { path: "/:locale/timeline", name: "timeline", component: { template: "<div />" } },
      { path: "/:locale/artists/:slug", name: "artists.show", component: { template: "<div />" } },
      { path: "/:locale/artworks/:id", name: "artworks.show", component: { template: "<div />" } },
    ],
  });
});

describe("EventPage", () => {
  it("shows a Winners section for an award event and groups everyone else by role", async () => {
    const wrapper = await mountPage();

    expect(wrapper.get("[data-testid=winners]").text()).toContain("بكر شيخون");
    expect(wrapper.get("[data-testid=winners]").text()).toContain("الجائزة الأولى");
    expect(wrapper.findAll("[data-testid=participant-group] h2").map((h) => h.text())).toEqual(["Juror", "Exhibited work"]);
  });

  it("has no Winners section on a non-award event, and lists awardee-role rows as a normal group", async () => {
    api.getEvent.mockResolvedValue({ data: detail({ event_type: "exhibition" }) });
    const wrapper = await mountPage();

    expect(wrapper.find("[data-testid=winners]").exists()).toBe(false);
    expect(wrapper.findAll("[data-testid=participant-group] h2").map((h) => h.text())).toContain("Awardee");
  });

  it("shows the not-found page when the event is unpublished", async () => {
    const { ApiError } = await import("@/types/api");
    api.getEvent.mockRejectedValue(new ApiError("not_found", "Not found", { status: 404 }));
    const wrapper = await mountPage();

    expect(wrapper.find("[data-testid=winners]").exists()).toBe(false);
    expect(wrapper.text()).not.toContain("جائزة الشراع الذهبي");
  });
});
