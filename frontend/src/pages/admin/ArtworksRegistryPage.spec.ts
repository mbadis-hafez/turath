import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtworksRegistryPage from "@/pages/admin/ArtworksRegistryPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { AdminArtworkRow } from "@/types/artworkCuration";

const api = vi.hoisted(() => ({
  listAdminArtworks: vi.fn(), searchHolders: vi.fn(), bulkArtworks: vi.fn(), deleteArtwork: vi.fn(),
  getArtworkCuration: vi.fn(), createArtwork: vi.fn(),
}));
vi.mock("@/api/artworkCuration", () => api);
vi.mock("@/api/artistCuration", () => ({ listAdminArtists: vi.fn() }));

const FULL: AdminArtworkRow = {
  id: 7,
  legacy_ref: "AW007",
  title: { ar: "تكوين رقم ٤", en: "Composition No. 4" },
  is_untitled: false,
  medium: { ar: "زيت على قماش", en: "Oil on canvas" },
  artist: { id: 2, name: { ar: "فنان التجريب", en: "Test Artist" } },
  holder_id: 3,
  thumbnail_url: "https://img.test/7.jpg",
  holder: { id: 3, name: { ar: "دار الفن", en: "House of Art" } },
  year: "1987",
  image_count: 3,
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
  legacy_ref: null,
  title: { ar: null, en: "Untitled Study" },
  medium: { ar: null, en: null },
  artist: null,
  holder_id: null,
  thumbnail_url: null,
  holder: null,
  year: null,
  image_count: 0,
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
      { path: "/:locale/admin/artworks/:id/view", name: "admin.artworks.detail", component: { template: "<div />" } },
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
  api.bulkArtworks.mockReset().mockResolvedValue({ data: { succeeded: [], failed: [] } });
  api.deleteArtwork.mockReset().mockResolvedValue(undefined);
  api.getArtworkCuration.mockReset();
  api.createArtwork.mockReset();
  router = makeRouter();
});

// ConfirmDialog teleports to document.body, which vue-test-utils doesn't
// unmount between tests on its own — without this, a later test's
// document.querySelector could pick up a stale dialog left by an earlier one.
afterEach(() => {
  document.body.innerHTML = "";
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
    expect(gridCard.text()).toContain("Oil on canvas");
    expect(gridCard.text()).toContain("AW007");
    expect(gridCard.text()).toContain("Published");
    expect(gridCard.text()).toContain("Missing dimensions");
    expect(gridCard.text()).toContain("Year uncertain");
    expect(gridCard.get("a").attributes("href")).toBe("/en/admin/artworks/7/view");

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
    expect(row.get("a").attributes("href")).toBe("/en/admin/artworks/7/view");
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
    expect(sparse.get("a").attributes("href")).toBe("/en/admin/artworks/8/view");
    expect(sparse.text()).toContain("No images");
  });

  it("wraps the list table in a horizontally scrollable container for narrow widths", async () => {
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=view-toggle-list]").trigger("click");

    expect(wrapper.find("table").exists()).toBe(true);
    expect(wrapper.get("table").element.closest(".overflow-x-auto")).not.toBeNull();
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

describe("ArtworksRegistryPage selection and bulk actions", () => {
  it("shows the bulk bar once a card is selected, and clears it via Clear selection", async () => {
    const wrapper = await mountPage();
    expect(wrapper.find("[data-testid=bulk-bar]").exists()).toBe(false);

    await wrapper.get("[data-testid=card-select]").trigger("change");
    expect(wrapper.get("[data-testid=bulk-bar]").text()).toContain("1 selected");

    await wrapper.get("[data-testid=bulk-clear]").trigger("click");
    expect(wrapper.find("[data-testid=bulk-bar]").exists()).toBe(false);
  });

  it("selects all with the header checkbox and reflects an indeterminate state", async () => {
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=card-select]").trigger("change");
    expect((wrapper.get("[data-testid=select-all]").element as HTMLInputElement).indeterminate).toBe(true);

    await wrapper.get("[data-testid=select-all]").trigger("change");
    expect(wrapper.get("[data-testid=bulk-bar]").text()).toContain("2 selected");

    await wrapper.get("[data-testid=select-all]").trigger("change");
    expect(wrapper.find("[data-testid=bulk-bar]").exists()).toBe(false);
  });

  it("changes the status of selected artworks in bulk", async () => {
    api.bulkArtworks.mockResolvedValue({ data: { succeeded: [7], failed: [] } });
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=card-select]").trigger("change");

    await wrapper.get("[data-testid=bulk-status]").setValue("hidden");
    await flushPromises();

    expect(api.bulkArtworks).toHaveBeenCalledWith({ ids: [7], action: "set_status", status: "hidden" });
    expect(wrapper.text()).toContain("1 updated, 0 could not be updated.");
  });

  it("disables the Link to exhibition bulk action with an explanatory tooltip", async () => {
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=card-select]").trigger("change");

    const button = wrapper.get("[data-testid=bulk-link-exhibition]");
    expect(button.attributes("disabled")).toBeDefined();
    expect(button.attributes("title")).toContain("isn't available yet");
  });

  it("exports selected artworks as a CSV download", async () => {
    const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => {});
    URL.createObjectURL = vi.fn(() => "blob:export");
    URL.revokeObjectURL = vi.fn();

    const wrapper = await mountPage();
    await wrapper.get("[data-testid=card-select]").trigger("change");
    await wrapper.get("[data-testid=bulk-export]").trigger("click");

    expect(clickSpy).toHaveBeenCalled();
    clickSpy.mockRestore();
  });

  it("bulk-deletes the selected artworks after confirmation", async () => {
    api.bulkArtworks.mockResolvedValue({ data: { succeeded: [7], failed: [] } });
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=card-select]").trigger("change");

    await wrapper.get("[data-testid=bulk-delete]").trigger("click");
    await flushPromises();
    expect(document.body.textContent).toContain("Delete 1 artworks?");
    (document.querySelector("[data-testid=confirm-dialog-confirm]") as HTMLElement).click();
    await flushPromises();

    expect(api.bulkArtworks).toHaveBeenCalledWith({ ids: [7], action: "delete" });
  });
});

describe("ArtworksRegistryPage row actions", () => {
  it("deletes a single artwork after confirmation", async () => {
    api.deleteArtwork.mockResolvedValue(undefined);
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=card-delete]").trigger("click");
    await flushPromises();
    expect(document.body.textContent).toContain("Delete this artwork?");
    (document.querySelector("[data-testid=confirm-dialog-confirm]") as HTMLElement).click();
    await flushPromises();

    expect(api.deleteArtwork).toHaveBeenCalledWith(7);
  });

  it("shows the delete error and keeps the dialog open on failure", async () => {
    api.deleteArtwork.mockRejectedValue(new Error("boom"));
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=card-delete]").trigger("click");
    await flushPromises();
    (document.querySelector("[data-testid=confirm-dialog-confirm]") as HTMLElement).click();
    await flushPromises();

    expect(document.body.textContent).toContain("boom");
  });

  it("duplicates an artwork by cloning its curation fields into a new draft", async () => {
    api.getArtworkCuration.mockResolvedValue({
      data: {
        id: 7, legacy_ref: "AW007", title: { ar: "تكوين رقم ٤", en: "Composition No. 4" }, is_untitled: false,
        artist: null, attribution_certainty: "unattributed", category: "painting", medium: { ar: null, en: null },
        creation: null, signed: "unknown", material_classification: "movable", conservation_risk_note: null,
        notes: { ar: null, en: null }, edition: { number: null, size: null },
        dimensions: { height_cm: null, width_cm: null, depth_cm: null, raw: null },
        frame_dimensions: { height_cm: null, width_cm: null, depth_cm: null, raw: null },
        weight_kg: null, holder: null, holder_inventory_no: null, inventory_by_owner: null,
        condition_report_link: null, condition_report_status: null, image_quality: null, editing_status: null,
        has_final_hr_image: false, images: [], publication_status: "draft",
        completeness: { pct: 0, severity: "blocking", blocking: [], minor: [] }, checklist: [], approve_blockers: {},
        pipeline: [], linked_materials: [],
      },
    });
    api.createArtwork.mockResolvedValue({ data: { id: 99 } });
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=card-duplicate]").trigger("click");
    await flushPromises();

    expect(api.getArtworkCuration).toHaveBeenCalledWith(7);
    expect(api.createArtwork).toHaveBeenCalled();
    expect(api.createArtwork.mock.calls[0][0]).not.toHaveProperty("legacy_ref");
    expect(router.currentRoute.value.path).toBe("/en/admin/artworks/99");
  });

  it("shows the sort dropdown and applies a sort choice via the query string", async () => {
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=sort-select]").setValue("title");
    await flushPromises();

    expect(router.currentRoute.value.query.sort).toBe("title");
    expect(api.listAdminArtworks.mock.calls.at(-1)?.[0]).toMatchObject({ sort: "title" });
  });
});
