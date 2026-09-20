import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import EventEditPage from "@/pages/admin/EventEditPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { EventDetail } from "@/types/event";

const api = vi.hoisted(() => ({
  getEvent: vi.fn(), createEvent: vi.fn(), updateEvent: vi.fn(), publishEvent: vi.fn(), syncEventParticipants: vi.fn(), syncEventThemes: vi.fn(),
  listThemes: vi.fn(), listAdminArtists: vi.fn(), listAdminArtworks: vi.fn(), searchHolders: vi.fn(),
}));
vi.mock("@/api/events", () => api);
vi.mock("@/api/artistCuration", () => ({ listThemes: api.listThemes, listAdminArtists: api.listAdminArtists }));
vi.mock("@/api/artworkCuration", () => ({ listAdminArtworks: api.listAdminArtworks, searchHolders: api.searchHolders }));

function detail(patch: Partial<EventDetail> = {}): EventDetail {
  return {
    id: 7, event_type: "exhibition", title: { ar: "المعرض الأول", en: null }, start: { display: "أوائل الثمانينيات", year_from: 1980, year_to: 1983, calendar: "gregorian", certainty: "circa" },
    end: null, venue_name: "دار الفنون", city: "جدة", publication_status: "draft", description: { ar: null, en: null }, date_note: "Only early 1980s is stated.",
    access_level: "public", holder: null, themes: [{ id: 2, label: { ar: "التأسيس", en: "Founding" } }], participants: [
      { id: 1, role: "awardee", note: "الجائزة الأولى", kind: "artist", entity: { id: 4, slug: "x", name: { ar: "منيرة", en: null } } },
    ], archive_items: [], completeness: { pct: 60, blocking: [], minor: ["holder", "description"] },
    ...patch,
  };
}

let router: Router;

async function mountAt(path: string) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions: ["events.manage"] } as never, initialized: true });
  await router.push(path);
  const wrapper = mountWithPlugins(EventEditPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.getEvent.mockReset().mockResolvedValue({ data: detail() });
  api.createEvent.mockReset().mockResolvedValue({ data: detail({ id: 9 }) });
  api.updateEvent.mockReset().mockResolvedValue({ data: detail() });
  api.publishEvent.mockReset().mockResolvedValue({ data: detail({ publication_status: "published" }) });
  api.syncEventParticipants.mockReset().mockResolvedValue({});
  api.syncEventThemes.mockReset().mockResolvedValue({});
  api.listThemes.mockReset().mockResolvedValue({ data: [{ id: 2, label: { ar: "التأسيس", en: "Founding" } }, { id: 3, label: { ar: "الحداثة", en: "Modernity" } }] });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/events", name: "admin.events", component: { template: "<div />" } },
      { path: "/:locale/admin/events/new", name: "admin.events.new", component: EventEditPage },
      { path: "/:locale/admin/events/:id", name: "admin.events.edit", component: EventEditPage },
      { path: "/:locale/admin/archive/:id", name: "admin.archive.edit", component: { template: "<div />" } },
    ],
  });
});

describe("EventEditPage (edit)", () => {
  it("loads participants and themes, and enables Publish once the core fields are met", async () => {
    const wrapper = await mountAt("/en/admin/events/7");

    expect(wrapper.findAll("[data-testid=themes] input").map((i) => (i.element as HTMLInputElement).checked)).toEqual([true, false]);
    expect(wrapper.get("[data-testid=participant-role]").element).toHaveProperty("value", "awardee");
    expect(wrapper.get("[data-testid=publish]").attributes("disabled")).toBeUndefined();
  });

  it("keeps Publish disabled while a core field is missing", async () => {
    api.getEvent.mockResolvedValue({ data: detail({ completeness: { pct: 40, blocking: ["venue_name"], minor: [] } }) });
    const wrapper = await mountAt("/en/admin/events/7");

    expect(wrapper.get("[data-testid=publish]").attributes("disabled")).toBeDefined();
  });

  it("saves fields, participants and themes, without re-sending the untouched start date", async () => {
    const wrapper = await mountAt("/en/admin/events/7");

    await wrapper.get("[data-testid=venue]").setValue("Dar Al-Funun");
    await wrapper.get("[data-testid=save]").trigger("click");
    await flushPromises();

    expect(api.updateEvent.mock.calls[0][1]).not.toHaveProperty("start");
    expect(api.updateEvent.mock.calls[0][1]).toMatchObject({ venue_name: "Dar Al-Funun", date_note: "Only early 1980s is stated." });
    expect(api.syncEventParticipants).toHaveBeenCalledWith(7, [{ id: 1, type: "artist", participant_id: 4, role: "awardee", note: "الجائزة الأولى" }]);
    expect(api.syncEventThemes).toHaveBeenCalledWith(7, [2]);
  });

  it("publishes after saving", async () => {
    const wrapper = await mountAt("/en/admin/events/7");

    await wrapper.get("[data-testid=publish]").trigger("click");
    await flushPromises();

    expect(api.updateEvent).toHaveBeenCalledBefore(api.publishEvent);
    expect(api.publishEvent).toHaveBeenCalledWith(7);
  });
});

describe("EventEditPage (add)", () => {
  it("creates a draft with an approximate date and its reason, then opens the event", async () => {
    const wrapper = await mountAt("/en/admin/events/new");

    await wrapper.get("[data-testid=title-ar]").setValue("معرض");
    await wrapper.get("[data-testid=date-mode]").setValue("approx");
    await wrapper.get("[data-testid=approx-from]").setValue("1980");
    await wrapper.get("[data-testid=date-note]").setValue("Only the decade is stated.");
    await wrapper.get("[data-testid=save]").trigger("click");
    await flushPromises();

    expect(api.createEvent.mock.calls[0][0]).toMatchObject({
      publication_status: "draft", event_type: "exhibition", title: { ar: "معرض", en: null },
      start: { year_from: 1980, year_to: 1980, certainty: "circa" }, date_note: "Only the decade is stated.",
    });
    expect(router.currentRoute.value.path).toBe("/en/admin/events/9");
  });

  it("stays on the page with a link when participants fail to save after the event was created", async () => {
    api.syncEventParticipants.mockRejectedValue(new Error("boom"));
    const wrapper = await mountAt("/en/admin/events/new");
    await wrapper.get("[data-testid=title-ar]").setValue("معرض");

    await wrapper.get("[data-testid=save]").trigger("click");
    await flushPromises();

    expect(wrapper.find("[data-testid=partial-failure]").exists()).toBe(true);
    expect(router.currentRoute.value.path).toBe("/en/admin/events/new");
  });
});
