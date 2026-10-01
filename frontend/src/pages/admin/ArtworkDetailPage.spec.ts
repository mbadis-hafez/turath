import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtworkDetailPage from "@/pages/admin/ArtworkDetailPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { ArtworkCuration } from "@/types/artworkCuration";

const api = vi.hoisted(() => ({ getArtworkCuration: vi.fn(), updateArtworkImage: vi.fn(), uploadArtworkImages: vi.fn() }));
vi.mock("@/api/artworkCuration", () => api);

function bundle(patch: Partial<ArtworkCuration> = {}): ArtworkCuration {
  return {
    id: 7, legacy_ref: "AW007", title: { ar: "تكوين أعواد", en: "Composition of Lutes" }, is_untitled: false,
    artist: { id: 1, name: { ar: "أحمد", en: "Ahmad" } }, attribution_certainty: "confirmed", category: "painting",
    medium: { ar: "زيت على قماش", en: "Oil on canvas" }, creation: { display: "1990", year_from: 1990, year_to: 1990, calendar: "gregorian", certainty: "exact" },
    signed: "signed", material_classification: "movable", conservation_risk_note: null, notes: { ar: "وصف العمل", en: "Artwork notes" },
    edition: { number: null, size: null }, dimensions: { height_cm: null, width_cm: null, depth_cm: null, raw: null }, frame_dimensions: { height_cm: null, width_cm: null, depth_cm: null, raw: null },
    weight_kg: null, holder: { id: 2, name: { ar: "المتحف", en: "The Museum" } }, holder_inventory_no: null, inventory_by_owner: null, condition_report_link: null,
    condition_report_status: null, image_quality: null, editing_status: null, has_final_hr_image: false,
    images: [
      { id: 1, url: "/img1", filename: "a.jpg", width_px: 1200, height_px: 900, size_bytes: 204800, rights_status: "licensed", is_final: true, view_role: "front", is_public: true },
      { id: 2, url: "/img2", filename: "b.jpg", width_px: 800, height_px: 600, size_bytes: 102400, rights_status: "unknown", is_final: false, view_role: null, is_public: false },
    ],
    publication_status: "published",
    completeness: { pct: 60, severity: "minor", blocking: [], minor: ["dimensions"] },
    checklist: [
      { key: "code", tier: "core", met: true },
      { key: "dimensions", tier: "important", met: false },
    ],
    approve_blockers: {},
    pipeline: [{ stage_key: "work_category", status: "done", note: null }, { stage_key: "status_research", status: "in_progress", note: null }],
    linked_materials: [{ id: 5, legacy_ref: "ARC-5", item_type: "image", title: { ar: "وثيقة", en: "Document" }, completeness_pct: 80 }],
    ...patch,
  };
}

let router: Router;

async function mountAt(path: string, permissions: string[] = ["artworks.manage"]) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions } as never, initialized: true });
  await router.push(path);
  const wrapper = mountWithPlugins(ArtworkDetailPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.getArtworkCuration.mockReset().mockResolvedValue({ data: bundle() });
  api.updateArtworkImage.mockReset().mockResolvedValue({ data: [] });
  api.uploadArtworkImages.mockReset().mockResolvedValue({ data: [], results: [{ filename: "c.jpg", status: "attached", image_id: 3, message: null }] });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/artworks", name: "admin.artworks", component: { template: "<div />" } },
      { path: "/:locale/admin/artworks/:id(\\d+)", name: "admin.artworks.show", component: { template: "<div />" } },
      { path: "/:locale/admin/artworks/:id(\\d+)/view", name: "admin.artworks.detail", component: ArtworkDetailPage },
      { path: "/:locale/admin/archive/:id(\\d+)/view", name: "admin.archive.show", component: { template: "<div />" } },
    ],
  });
});

describe("ArtworkDetailPage", () => {
  it("shows a forbidden state without artworks.manage", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view", []);
    expect(wrapper.text()).toContain("don't have access");
  });

  it("shows an error state on load failure with a retry option", async () => {
    api.getArtworkCuration.mockRejectedValue(new Error("network down"));
    const wrapper = await mountAt("/en/admin/artworks/7/view");
    expect(wrapper.text()).toContain("Something went wrong");

    api.getArtworkCuration.mockResolvedValue({ data: bundle() });
    await wrapper.get("button").trigger("click");
    await flushPromises();
    expect(wrapper.text()).toContain("Composition of Lutes");
  });

  it("renders the title, tags and breadcrumb code for a loaded artwork", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view");

    expect(wrapper.text()).toContain("Composition of Lutes");
    expect(wrapper.get("[data-testid=badge-row]").text()).toContain("Published");
    expect(wrapper.get("nav").text()).toContain("AW007");
  });

  it("shows the image gallery with a working prev/next and thumbnail selection", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view");

    expect(wrapper.get("[data-testid=gallery-position]").text()).toBe("1 / 2");
    expect(wrapper.get("[data-testid=file-preview]").attributes("src")).toBe("/img1");

    await wrapper.get("[data-testid=gallery-next]").trigger("click");
    expect(wrapper.get("[data-testid=gallery-position]").text()).toBe("2 / 2");
    expect(wrapper.get("[data-testid=file-preview]").attributes("src")).toBe("/img2");
    expect(wrapper.get("[data-testid=selected-image-meta]").text()).toContain("Unknown");

    const thumbs = wrapper.findAll("[data-testid=thumbnail]");
    expect(thumbs).toHaveLength(2);
    await thumbs[0].trigger("click");
    expect(wrapper.get("[data-testid=file-preview]").attributes("src")).toBe("/img1");
  });

  it("shows an empty-state message when there are no images", async () => {
    api.getArtworkCuration.mockResolvedValue({ data: bundle({ images: [] }) });
    const wrapper = await mountAt("/en/admin/artworks/7/view");

    expect(wrapper.find("[data-testid=gallery]").exists()).toBe(false);
    expect(wrapper.get("[data-testid=no-images]").text()).toContain("No images uploaded yet.");
  });

  it("flags a field as missing when its checklist item is unmet", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view");

    const flags = wrapper.findAll("[data-testid=field-flag]");
    expect(flags.length).toBeGreaterThan(0);
    const tables = wrapper.findAll("[data-testid=meta-table]").map((t) => t.text());
    expect(tables.some((t) => t.includes("Oil on canvas"))).toBe(true);
  });

  it("shows the red verification checklist while items are unmet, with a progress readout", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view");

    expect(wrapper.get("[data-testid=required-checklist]").text()).toContain("Missing for verification and approval");
    expect(wrapper.get("[data-testid=checklist-progress]").text()).toBe("1 of 2 items complete");
    expect(wrapper.find("[data-testid=required-checklist-done]").exists()).toBe(false);
  });

  it("shows the all-complete note once every checklist item is met", async () => {
    api.getArtworkCuration.mockResolvedValue({ data: bundle({ checklist: [{ key: "code", tier: "core", met: true }] }) });
    const wrapper = await mountAt("/en/admin/artworks/7/view");

    expect(wrapper.find("[data-testid=required-checklist]").exists()).toBe(false);
    expect(wrapper.get("[data-testid=required-checklist-done]").text()).toContain("All required fields are complete.");
  });

  it("shows the work pipeline stages", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view");
    const pipeline = wrapper.get("[data-testid=pipeline]").text();
    expect(pipeline).toContain("Work category");
    expect(pipeline).toContain("In progress");
  });

  it("lists related materials linking to the archive item page", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view");

    const link = wrapper.get("[data-testid=related-link]");
    expect(link.text()).toContain("Document");
    expect(link.attributes("href")).toBe("/en/admin/archive/5/view");
  });

  it("links the Edit action to the curation page", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view");
    expect(wrapper.get("[data-testid=edit-link]").attributes("href")).toBe("/en/admin/artworks/7");
  });

  it("shows required-shot placeholder tiles for roles no image has, and a status row of captured/missing shots", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view");

    const tiles = wrapper.findAll("[data-testid=missing-shot-tile]");
    expect(tiles.map((t) => t.text())).toEqual(
      expect.arrayContaining([expect.stringContaining("Signature detail"), expect.stringContaining("Frame"), expect.stringContaining("Verso"), expect.stringContaining("Edited for publish")]),
    );
    expect(tiles).toHaveLength(4);

    const shots = wrapper.get("[data-testid=required-shots]").text();
    expect(shots).toContain("Front view");
    expect(shots).toContain("Signature detail");
  });

  it("assigns a shot type to the selected image", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view");

    await wrapper.get("[data-testid=gallery-next]").trigger("click");
    await wrapper.get("[data-testid=role-select]").setValue("signature");
    await flushPromises();

    expect(api.updateArtworkImage).toHaveBeenCalledWith(7, 2, { view_role: "signature" });
    expect(api.getArtworkCuration).toHaveBeenCalledTimes(2);
  });

  it("toggles an image's public visibility", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view");

    await wrapper.get("[data-testid=public-toggle]").trigger("click");
    await flushPromises();

    expect(api.updateArtworkImage).toHaveBeenCalledWith(7, 1, { is_public: false });
  });

  it("captures a missing required shot by uploading a file and tagging it with that role", async () => {
    const wrapper = await mountAt("/en/admin/artworks/7/view");

    await wrapper.get("[data-testid=missing-shot-tile]").trigger("click");
    const input = wrapper.get("[data-testid=capture-input]");
    const file = new File(["x"], "verso.jpg", { type: "image/jpeg" });
    Object.defineProperty(input.element, "files", { value: [file] });
    await input.trigger("change");
    await flushPromises();

    expect(api.uploadArtworkImages).toHaveBeenCalledWith(7, [file], "unknown");
    expect(api.updateArtworkImage).toHaveBeenCalledWith(7, 3, { view_role: "signature" });
  });
});
