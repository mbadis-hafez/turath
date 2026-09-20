import { beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import { createMemoryHistory, createRouter, type Router } from "vue-router";
import { createPinia } from "pinia";

import ArtistPage from "@/pages/ArtistPage.vue";
import { i18n, applyLocale } from "@/i18n";
import { ApiError } from "@/types/api";
import type { Artist } from "@/types/artist";

vi.mock("@/api/artists", () => ({
  getArtist: vi.fn(),
  listArtists: vi.fn(),
  getArtistActivity: vi.fn(),
}));

import { getArtist } from "@/api/artists";

function makeArtist(patch: Partial<Artist> = {}): Artist {
  return {
    id: 7,
    slug: "inji-efflatoun",
    name: { ar: "إنجي أفلاطون", en: "Inji Efflatoun" },
    birth: {
      display: null,
      year_from: 1924,
      year_to: null,
      calendar: "gregorian",
      certainty: "exact",
      place: { ar: "القاهرة", en: "Cairo" },
    },
    death: {
      display: null,
      year_from: 1989,
      year_to: null,
      calendar: "gregorian",
      certainty: "exact",
      place: { ar: null, en: null },
    },
    living_status: "deceased",
    verified_status: "verified",
    legacy_code: null,
    bio: {
      ar: "فنانة مصرية رائدة في الفن التشكيلي.",
      en: "A pioneering Egyptian painter.",
    },
    also_known_as: [
      {
        id: 1,
        name: "Efflatoun, Inji",
        language: "en",
        type: "transliteration",
        source_note: null,
      },
      {
        id: 2,
        name: "انجي افلاطون",
        language: "und",
        type: "typo",
        source_note: null,
      },
    ],
    verified_at: "2026-01-01T00:00:00Z",
    publication_status: "published",
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
    ...patch,
  };
}

let router: Router;

async function mountPage(path: string, locale: "ar" | "en" = "ar") {
  applyLocale(locale);
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: "/:locale/artists/:slug",
        name: "artists.show",
        component: ArtistPage,
      },
      { path: "/:locale", name: "home", component: { template: "<div />" } },
      { path: "/:locale/events/:id", name: "events.show", component: { template: "<div />" } },
    ],
  });
  await router.push(path);
  await router.isReady();

  const wrapper = mount(ArtistPage, {
    global: { plugins: [createPinia(), i18n, router] },
  });
  await flushPromises();
  return wrapper;
}

describe("ArtistPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders names, dates, places and bio in the current locale", async () => {
    vi.mocked(getArtist).mockResolvedValue({ data: makeArtist() });
    const wrapper = await mountPage("/ar/artists/inji-efflatoun");

    expect(wrapper.text()).toContain("إنجي أفلاطون");
    expect(wrapper.text()).toContain("Inji Efflatoun");
    expect(wrapper.text()).toContain("1924");
    expect(wrapper.text()).toContain("1989");
    expect(wrapper.text()).toContain("القاهرة");
    expect(wrapper.text()).toContain("فنانة مصرية رائدة");
    expect(document.title).toContain("إنجي أفلاطون");
  });

  it("shows the fallback badge when the bio exists only in the other language", async () => {
    vi.mocked(getArtist).mockResolvedValue({
      data: makeArtist({ bio: { ar: null, en: "A pioneering Egyptian painter." } }),
    });
    const wrapper = await mountPage("/ar/artists/inji-efflatoun");

    expect(wrapper.get("[role='note']").text()).toBe("النص متوفر بلغة أخرى");
    expect(wrapper.text()).toContain("A pioneering Egyptian painter.");
  });

  it("shows the noBio state when no biography exists in either language", async () => {
    vi.mocked(getArtist).mockResolvedValue({
      data: makeArtist({ bio: { ar: null, en: null } }),
    });
    const wrapper = await mountPage("/ar/artists/inji-efflatoun");

    expect(wrapper.text()).toContain("لا توجد سيرة مكتوبة بعد");
  });

  it("renders the also-known-as list with per-variant direction", async () => {
    vi.mocked(getArtist).mockResolvedValue({ data: makeArtist() });
    const wrapper = await mountPage("/ar/artists/inji-efflatoun");

    expect(wrapper.text()).toContain("يُعرف أيضًا بـ");
    expect(wrapper.get("li[dir='ltr']").text()).toContain("Efflatoun, Inji");
    expect(wrapper.get("li[dir='auto']").text()).toContain("انجي افلاطون");
  });

  it("renders the shared not-found page for unknown slugs", async () => {
    vi.mocked(getArtist).mockRejectedValue(
      new ApiError("not_found", "Not found", { status: 404 }),
    );
    const wrapper = await mountPage("/ar/artists/nope");

    expect(wrapper.text()).toContain("الصفحة غير موجودة");
    expect(wrapper.find("h1").text()).not.toContain("إنجي");
  });

  it("sets a meta description from the first 160 bio characters", async () => {
    const longBio = "سيرة طويلة. ".repeat(40).trim();
    vi.mocked(getArtist).mockResolvedValue({
      data: makeArtist({ bio: { ar: longBio, en: null } }),
    });
    await mountPage("/ar/artists/inji-efflatoun");

    const meta = document.querySelector('meta[name="description"]');
    expect(meta?.getAttribute("content")).toBe(longBio.slice(0, 160));
    expect(meta?.getAttribute("content")).toHaveLength(160);
  });

  it("lists real events with the artist's role on the Events tab", async () => {
    vi.mocked(getArtist).mockResolvedValue({
      data: makeArtist({
        events: [{
          id: 3, event_type: "award", title: { ar: "جائزة الشراع الذهبي", en: null }, start: { display: null, year_from: 1988, year_to: 1988, calendar: "gregorian", certainty: "exact" },
          end: null, venue_name: "الكويت", city: null, publication_status: "published", role: "awardee", note: "الجائزة الأولى",
        }],
      }),
    });
    const wrapper = await mountPage("/ar/artists/inji-efflatoun");

    const tab = wrapper.findAll("[role=tab]").find((t) => t.text().includes("الفعاليات"));
    expect(tab?.text()).toContain("(1)");
    await tab!.trigger("click");

    expect(wrapper.get("[data-testid=public-event]").text()).toContain("جائزة الشراع الذهبي");
    expect(wrapper.get("[data-testid=event-role]").text()).toBe("فائز");
    expect(wrapper.text()).toContain("الجائزة الأولى");
  });
});
