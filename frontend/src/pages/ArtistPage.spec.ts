import { beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import { createMemoryHistory, createRouter, type Router } from "vue-router";
import { createPinia, setActivePinia } from "pinia";
import { useAuthStore } from "@/stores/auth";

import ArtistPage from "@/pages/ArtistPage.vue";
import { i18n, applyLocale } from "@/i18n";
import { ApiError } from "@/types/api";
import type { Artist } from "@/types/artist";

vi.mock("@/api/artists", () => ({
  getArtist: vi.fn(),
  listArtists: vi.fn(),
  getArtistActivity: vi.fn(),
}));

vi.mock("@/api/archive", () => ({ listArtistArchiveItems: vi.fn() }));
vi.mock("@/api/artworks", () => ({ listArtistArtworks: vi.fn() }));

import { listArtistArchiveItems } from "@/api/archive";
import { listArtistArtworks } from "@/api/artworks";
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
      {
        path: "/:locale/artists/:slug",
        name: "artists.show",
        component: ArtistPage,
      },
      { path: "/:locale", name: "home", component: { template: "<div />" } },
      { path: "/:locale/artists", name: "artists.index", component: { template: "<div />" } },
      { path: "/:locale/archive", name: "archive.records", component: { template: "<div />" } },
      { path: "/:locale/login", name: "login", component: { template: "<div />" } },
      { path: "/:locale/suggest/:type/:id", name: "suggest", component: { template: "<div />" } },
      { path: "/:locale/artworks/:id", name: "artworks.show", component: { template: "<div />" } },
      { path: "/:locale/events/:id", name: "events.show", component: { template: "<div />" } },
    ],
  });
  await router.push(path);
  await router.isReady();

  const wrapper = mount(ArtistPage, {
    global: { plugins: [pinia, i18n, router] },
  });
  await flushPromises();
  return wrapper;
}

describe("ArtistPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listArtistArchiveItems).mockResolvedValue({ data: [], links: [], meta: { current_page: 1, last_page: 1, per_page: 100, total: 0, from: null, to: null } });
    vi.mocked(listArtistArtworks).mockResolvedValue({ data: [], links: [], meta: { current_page: 1, last_page: 1, per_page: 24, total: 0, from: null, to: null } });
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
  it("lists real events with the artist's role in the events section", async () => {
    vi.mocked(getArtist).mockResolvedValue({
      data: makeArtist({
        events: [{
          id: 3, event_type: "award", title: { ar: "جائزة الشراع الذهبي", en: null }, start: { display: null, year_from: 1988, year_to: 1988, calendar: "gregorian", certainty: "exact" },
          end: null, venue_name: "الكويت", city: null, publication_status: "published", role: "awardee", note: "الجائزة الأولى",
        }],
      }),
    });
    const wrapper = await mountPage("/ar/artists/inji-efflatoun");

    expect(wrapper.get("[data-testid=public-event]").text()).toContain("جائزة الشراع الذهبي");
    expect(wrapper.get("[data-testid=event-role]").text()).toBe("فائز");
    expect(wrapper.get("[data-testid=stats]").text()).toContain("1 فعاليات");
  });

  it("shows the portrait, the themes, and the stats line with the archive and works counts", async () => {
    vi.mocked(getArtist).mockResolvedValue({
      data: makeArtist({ portrait_url: "/api/v1/artists/7/portrait", themes: [{ id: 1, label: { ar: "الأحساء", en: "Alahsa" } }] }),
    });
    vi.mocked(listArtistArchiveItems).mockResolvedValue({ data: [], links: [], meta: { current_page: 1, last_page: 1, per_page: 100, total: 8, from: null, to: null } });
    vi.mocked(listArtistArtworks).mockResolvedValue({ data: [], links: [], meta: { current_page: 1, last_page: 1, per_page: 24, total: 3, from: null, to: null } });
    const wrapper = await mountPage("/en/artists/inji-efflatoun", "en");

    expect(wrapper.get("[data-testid=portrait]").attributes("src")).toBe("/api/v1/artists/7/portrait");
    expect(wrapper.get("[data-testid=themes]").text()).toContain("Alahsa");
    expect(wrapper.get("[data-testid=stats]").text()).toContain("8 archive items");
    expect(wrapper.get("[data-testid=stats]").text()).toContain("3 works mentioned");
  });

  it("flags an unverified name in the stats and the sidebar, and a missing life date, in red", async () => {
    vi.mocked(getArtist).mockResolvedValue({ data: makeArtist({ verified_status: "unverified", birth: null, death: null }) });
    const wrapper = await mountPage("/en/artists/inji-efflatoun", "en");

    expect(wrapper.get("[data-testid=stat-unverified]").text()).toBe("Name under verification");
    expect(wrapper.get("[data-testid=record-verification]").text()).toContain("no published source attached yet");
    expect(wrapper.get("[data-testid=record-verification]").classes()).toContain("text-danger");
    expect(wrapper.get("[data-testid=record-life]").text()).toBe("Not recorded");
  });

  it("shows a verified name as verified, with no unverified flag", async () => {
    vi.mocked(getArtist).mockResolvedValue({ data: makeArtist({ verified_status: "verified" }) });
    const wrapper = await mountPage("/en/artists/inji-efflatoun", "en");

    expect(wrapper.find("[data-testid=stat-unverified]").exists()).toBe(false);
    expect(wrapper.get("[data-testid=record-verification]").text()).toBe("Verified");
  });

  it("builds the career list oldest first, labelling anything but an exact year as uncertain", async () => {
    const item = (id: number, ref: string, year: number | null, certainty: string) => ({
      id, legacy_ref: ref, item_type: "article", title: { ar: `مادة ${id}`, en: null }, access_level: "public", publication_status: "published", restricted: false,
      content: year ? { display: String(year), year_from: year, year_to: year, calendar: "gregorian", certainty } : null,
    });
    vi.mocked(listArtistArchiveItems).mockResolvedValue({
      data: [item(1, "ARCIMG002", null, "unknown"), item(2, "ARTCL003", 1985, "exact"), item(3, "ARTCL001", 1977, "exact"), item(4, "ARCIMG004", 1990, "circa")] as never,
      links: [], meta: { current_page: 1, last_page: 1, per_page: 100, total: 4, from: null, to: null },
    });
    const wrapper = await mountPage("/en/artists/inji-efflatoun", "en");

    const rows = wrapper.findAll("[data-testid=career-row]");
    expect(rows.map((r) => r.text())).toEqual([
      expect.stringContaining("1977"), expect.stringContaining("1985"), expect.stringContaining("Year uncertain"), expect.stringContaining("Year uncertain"),
    ]);
    expect(rows[0].text()).toContain("ARTCL001");
  });

  it("marks restricted archive cards and only offers 'view all' when there are more than the grid shows", async () => {
    const item = (id: number, restricted: boolean) => ({
      id, legacy_ref: `A${id}`, item_type: "image", title: { ar: `مادة ${id}`, en: null }, access_level: "registered", publication_status: "published", restricted,
      content: { display: "1978", year_from: 1978, year_to: 1978, calendar: "gregorian", certainty: "exact" },
    });
    vi.mocked(listArtistArchiveItems).mockResolvedValue({
      data: Array.from({ length: 8 }, (_, n) => item(n + 1, n === 0)) as never,
      links: [], meta: { current_page: 1, last_page: 1, per_page: 100, total: 8, from: null, to: null },
    });
    const wrapper = await mountPage("/en/artists/inji-efflatoun", "en");

    expect(wrapper.findAll("[data-testid=archive-card]")).toHaveLength(6);
    expect(wrapper.findAll("[data-testid=restricted-badge]")).toHaveLength(1);
    expect(wrapper.get("[data-testid=view-all]").attributes("href")).toContain("artist_id=7");
  });

  it("sends a signed-out visitor to sign in before correcting, and a contributor straight to the suggestion form", async () => {
    vi.mocked(getArtist).mockResolvedValue({ data: makeArtist() });
    const visitor = await mountPage("/en/artists/inji-efflatoun", "en");
    const href = visitor.get("[data-testid=send-correction]").attributes("href")!;
    expect(href).toContain("/en/login");
    expect(decodeURIComponent(href)).toContain("redirect=/en/suggest/artists/7");
  });

  it("sends a signed-in contributor straight to the suggestion form, and hides the button from a user who cannot suggest", async () => {
    vi.mocked(getArtist).mockResolvedValue({ data: makeArtist() });

    const contributor = await mountPage("/en/artists/inji-efflatoun", "en", ["proposals.submit"]);
    expect(contributor.get("[data-testid=send-correction]").attributes("href")).toBe("/en/suggest/artists/7");

    const reader = await mountPage("/en/artists/inji-efflatoun", "en", []);
    expect(reader.find("[data-testid=send-correction]").exists()).toBe(false);
    expect(reader.get("[data-testid=correction-box]").text()).toContain("Know more about this artist?");
  });
});
