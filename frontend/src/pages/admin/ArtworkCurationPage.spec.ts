import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtworkCurationPage from "@/pages/admin/ArtworkCurationPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { ArtworkCuration } from "@/types/artworkCuration";

const api = vi.hoisted(() => ({
  getArtworkCuration: vi.fn(), updateArtwork: vi.fn(), updateArtworkStage: vi.fn(), approveArtwork: vi.fn(),
}));
vi.mock("@/api/artworkCuration", () => api);

const dims = { height_cm: null, width_cm: null, depth_cm: null, raw: null };

function bundle(patch: Partial<ArtworkCuration> = {}): ArtworkCuration {
  return {
    id: 7, legacy_ref: "AW007", title: { ar: "تكوين أعواد", en: "Composition of Lutes" }, is_untitled: false,
    artist: { id: 1, name: { ar: "أحمد", en: "Ahmad" } }, attribution_certainty: "confirmed", category: "painting",
    medium: { ar: null, en: null }, creation: { display: "1990", year_from: 1990, year_to: 1990, calendar: "gregorian", certainty: "exact" },
    signed: "unknown", notes: { ar: null, en: null }, edition: { number: null, size: null }, dimensions: dims, frame_dimensions: dims,
    weight_kg: null, holder: null, holder_inventory_no: null, inventory_by_owner: null, condition_report_link: null,
    condition_report_status: null, image_quality: null, editing_status: null, has_final_hr_image: false, publication_status: "draft",
    completeness: { pct: 33, severity: "blocking", blocking: ["holder"], minor: ["dimensions", "medium"] },
    checklist: [
      { key: "code", tier: "core", met: true }, { key: "dimensions", tier: "important", met: false }, { key: "holder", tier: "core", met: false },
    ],
    approve_blockers: { "data.holder": ["Missing required field: Holder."] },
    pipeline: [{ stage_key: "work_category", status: "not_started", note: null }],
    linked_materials: [],
    ...patch,
  };
}

let router: Router;

async function mountPage() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions: ["artworks.manage"] } as never, initialized: true });
  await router.push("/en/admin/artworks/7");
  const wrapper = mountWithPlugins(ArtworkCurationPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.getArtworkCuration.mockReset().mockResolvedValue({ data: bundle() });
  api.updateArtwork.mockReset().mockResolvedValue({});
  api.updateArtworkStage.mockReset().mockResolvedValue({});
  api.approveArtwork.mockReset().mockResolvedValue({});
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/artworks", name: "admin.artworks", component: { template: "<div />" } },
      { path: "/:locale/admin/artworks/:id", name: "admin.artworks.show", component: ArtworkCurationPage },
    ],
  });
});

describe("ArtworkCurationPage", () => {
  it("disables Approve while the API reports blockers and renders the checklist", async () => {
    const wrapper = await mountPage();

    expect(wrapper.get("[data-testid=approve-button]").attributes("disabled")).toBeDefined();
    const boxes = wrapper.findAll("[data-testid=checklist] input");
    expect(boxes.map((b) => (b.element as HTMLInputElement).checked)).toEqual([true, false, false]);
  });

  it("enables Approve when nothing blocks and calls the approve endpoint", async () => {
    api.getArtworkCuration.mockResolvedValue({ data: bundle({ approve_blockers: {} }) });
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=approve-button]").trigger("click");
    await flushPromises();

    expect(api.approveArtwork).toHaveBeenCalledWith(7);
  });

  it("saves edits without re-sending an unchanged year", async () => {
    const wrapper = await mountPage();

    await wrapper.get("input[type=number]").setValue("1990");
    await wrapper.findAll("button").find((b) => b.text() === "Save")!.trigger("click");
    await flushPromises();

    const payload = api.updateArtwork.mock.calls[0][1];
    expect(api.updateArtwork.mock.calls[0][0]).toBe(7);
    expect(payload).not.toHaveProperty("creation");
    expect(payload.title).toEqual({ ar: "تكوين أعواد", en: "Composition of Lutes" });
  });

  it("updates a pipeline stage from its select", async () => {
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=pipeline] select").setValue("done");

    expect(api.updateArtworkStage).toHaveBeenCalledWith(7, "work_category", "done");
  });
});
