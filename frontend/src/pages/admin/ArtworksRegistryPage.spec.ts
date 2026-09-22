import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtworksRegistryPage from "@/pages/admin/ArtworksRegistryPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { AdminArtworkRow } from "@/types/artworkCuration";

const api = vi.hoisted(() => ({ listAdminArtworks: vi.fn(), searchHolders: vi.fn() }));
vi.mock("@/api/artworkCuration", () => api);
vi.mock("@/api/artistCuration", () => ({ listAdminArtists: vi.fn() }));

const FULL: AdminArtworkRow = {
  id: 7,
  title: { ar: "تكوين رقم ٤", en: "Composition No. 4" },
  is_untitled: false,
  artist: { id: 2, name: { ar: "فنان التجريب", en: "Test Artist" } },
  holder_id: 3,
  thumbnail_url: "https://img.test/7.jpg",
  holder: { id: 3, name: { ar: "دار الفن", en: "House of Art" } },
  year: "1987",
  flags: ["missing_dimensions", "year_uncertain"],
  publication_status: "published",
  missing_dimensions: true,
  completeness_pct: 80,
  severity: "minor",
  pipeline: { cleared: 4, total: 6 },
  merged_into_id: null,
};

const SPARSE: AdminArtworkRow = {
  ...FULL,
  id: 8,
  title: { ar: null, en: "Untitled Study" },
  artist: null,
  holder_id: null,
  thumbnail_url: null,
  holder: null,
  year: null,
  flags: [],
  publication_status: "draft",
};

const META = { current_page: 1, last_page: 1, per_page: 24, total: 2, from: 1, to: 2, candidate_count: 0 };

let router: Router;

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/artworks", name: "admin.artworks", component: ArtworksRegistryPage },
      { path: "/:locale/admin/artworks/new", name: "admin.artworks.new", component: { template: "<div />" } },
      { path: "/:locale/admin/artworks/:id", name: "admin.artworks.show", component: { template: "<div />" } },
    ],
  });
}

async function mountPage(query: Record<string, string> = {}) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions: ["artworks.manage"] } as never, initialized: true });
  await router.push({ path: "/en/admin/artworks", query });
  const wrapper = mountWithPlugins(ArtworksRegistryPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  localStorage.clear();
  api.listAdminArtworks.mockReset().mockResolvedValue({ data: [FULL, SPARSE], meta: META });
  api.searchHolders.mockReset().mockResolvedValue([]);
  router = makeRouter();
});

describe("ArtworksRegistryPage view modes", () => {
  it("defaults to grid with the grid toggle pressed", async () => {
    const wrapper = await mountPage();

    expect(wrapper.get("[data-testid=view-toggle-grid]").attributes("aria-pressed")).toBe("true");
    expect(wrapper.get("[data-testid=view-toggle-list]").attributes("aria-pressed")).toBe("false");
    expect(wrapper.findAll("[data-testid=artwork-card]")).toHaveLength(2);
  });

  it("switches to list without refetching and without changing the route query", async () => {
    const wrapper = await mountPage({ q: "oud", status: "draft" });
    const callsBefore = api.listAdminArtworks.mock.calls.length;
    const queryBefore = { ...router.currentRoute.value.query };

    await wrapper.get("[data-testid=view-toggle-list]").trigger("click");
    await flushPromises();

    expect(wrapper.get("[data-testid=view-toggle-list]").attributes("aria-pressed")).toBe("true");
    expect(wrapper.get("[data-testid=view-toggle-grid]").attributes("aria-pressed")).toBe("false");
    expect(wrapper.findAll("[data-testid=artwork-card]")).toHaveLength(2);
    expect(api.listAdminArtworks.mock.calls.length).toBe(callsBefore);
    expect({ ...router.currentRoute.value.query }).toEqual(queryBefore);

    await wrapper.get("[data-testid=view-toggle-grid]").trigger("click");
    expect(wrapper.get("[data-testid=view-toggle-grid]").attributes("aria-pressed")).toBe("true");
  });

  it("exposes the same metadata in both layouts and links both to the detail page", async () => {
    const wrapper = await mountPage();

    const gridCard = wrapper.get("[data-testid=artwork-card]");
    expect(gridCard.text()).toContain("Composition No. 4");
    expect(gridCard.text()).toContain("تكوين رقم ٤");
    expect(gridCard.text()).toContain("Test Artist");
    expect(gridCard.text()).toContain("1987");
    expect(gridCard.text()).toContain("House of Art");
    expect(gridCard.text()).toContain("Published");
    expect(gridCard.text()).toContain("Missing dimensions");
    expect(gridCard.text()).toContain("Year uncertain");
    expect(gridCard.get("a").attributes("href")).toBe("/en/admin/artworks/7");

    await wrapper.get("[data-testid=view-toggle-list]").trigger("click");
    const row = wrapper.get("[data-testid=artwork-card]");
    expect(row.text()).toContain("Composition No. 4");
    expect(row.text()).toContain("تكوين رقم ٤");
    expect(row.text()).toContain("Test Artist");
    expect(row.text()).toContain("1987");
    expect(row.text()).toContain("House of Art");
    expect(row.text()).toContain("Published");
    expect(row.text()).toContain("Missing dimensions");
    expect(row.text()).toContain("Year uncertain");
    expect(row.get("a").attributes("href")).toBe("/en/admin/artworks/7");
  });

  it("renders rows cleanly when optional fields are null", async () => {
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=view-toggle-list]").trigger("click");

    const rows = wrapper.findAll("[data-testid=artwork-card]");
    expect(rows).toHaveLength(2);
    const sparse = rows[1];
    expect(sparse.text()).toContain("Untitled Study");
    expect(sparse.text()).toContain("Draft");
    expect(sparse.find("img").exists()).toBe(false);
    expect(sparse.get("a").attributes("href")).toBe("/en/admin/artworks/8");
  });

  it("keeps title and status visible in the list row structure at narrow widths", async () => {
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=view-toggle-list]").trigger("click");

    const row = wrapper.get("[data-testid=artwork-card]");
    expect(row.text()).toContain("Composition No. 4");
    expect(row.text()).toContain("Published");
    // Secondary fields carry responsive-hide classes so only title/status stay put below sm.
    expect(row.html()).toContain("sm:");
  });

  it("persists the selected view across remounts", async () => {
    const first = await mountPage();
    await first.get("[data-testid=view-toggle-list]").trigger("click");
    expect(localStorage.getItem("admin-artworks-view")).toBe("list");
    first.unmount();

    const second = await mountPage();
    expect(second.get("[data-testid=view-toggle-list]").attributes("aria-pressed")).toBe("true");
    expect(second.findAll("[data-testid=artwork-card]")).toHaveLength(2);
  });

  it("falls back to grid when the stored preference is corrupted", async () => {
    localStorage.setItem("admin-artworks-view", '"banana"');
    const wrapper = await mountPage();

    expect(wrapper.get("[data-testid=view-toggle-grid]").attributes("aria-pressed")).toBe("true");
  });
});
