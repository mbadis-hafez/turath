import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtworkCurationPage from "@/pages/admin/ArtworkCurationPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";
import type { ArtworkCuration } from "@/types/artworkCuration";

const api = vi.hoisted(() => ({
  getArtworkCuration: vi.fn(), updateArtwork: vi.fn(), uploadArtworkImage: vi.fn(), updateArtworkImage: vi.fn(), deleteArtworkImage: vi.fn(), updateArtworkStage: vi.fn(), approveArtwork: vi.fn(),
}));
vi.mock("@/api/artworkCuration", () => api);

const editorial = vi.hoisted(() => ({ getDraft: vi.fn(), saveDraft: vi.fn(), submitDraft: vi.fn() }));
vi.mock("@/api/editorial", () => editorial);

const dims = { height_cm: null, width_cm: null, depth_cm: null, raw: null };

function bundle(patch: Partial<ArtworkCuration> = {}): ArtworkCuration {
  return {
    id: 7, legacy_ref: "AW007", title: { ar: "تكوين أعواد", en: "Composition of Lutes" }, is_untitled: false,
    artist: { id: 1, name: { ar: "أحمد", en: "Ahmad" } }, attribution_certainty: "confirmed", category: "painting",
    medium: { ar: null, en: null }, creation: { display: "1990", year_from: 1990, year_to: 1990, calendar: "gregorian", certainty: "exact" },
    signed: "unknown", material_classification: "movable", conservation_risk_note: null, notes: { ar: null, en: null }, edition: { number: null, size: null }, dimensions: dims, frame_dimensions: dims,
    weight_kg: null, holder: null, holder_inventory_no: null, inventory_by_owner: null, condition_report_link: null,
    condition_report_status: null, image_quality: null, editing_status: null, has_final_hr_image: false, images: [], publication_status: "draft",
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

async function mountPage(permissions: string[] = ["artworks.manage"]) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions } as never, initialized: true });
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
  editorial.getDraft.mockReset().mockResolvedValue({ data: null });
  editorial.saveDraft.mockReset().mockResolvedValue({ data: { status: "draft" } });
  editorial.submitDraft.mockReset().mockResolvedValue({ data: { status: "pending" } });
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

  it("uploads an image with the chosen rights and can mark another one final", async () => {
    api.uploadArtworkImage.mockResolvedValue({ data: [] });
    api.updateArtworkImage.mockResolvedValue({ data: [] });
    const image = { id: 5, url: "/i/5", filename: "a.jpg", width_px: 3000, height_px: 2000, size_bytes: 10, rights_status: "licensed", is_final: false };
    api.getArtworkCuration.mockResolvedValue({ data: bundle({ images: [image as never] }) });
    const wrapper = await mountPage();

    const file = new File(["x"], "b.jpg", { type: "image/jpeg" });
    const input = wrapper.get("[data-testid=image-input]");
    Object.defineProperty(input.element, "files", { value: [file] });
    await input.trigger("change");
    await flushPromises();
    expect(api.uploadArtworkImage).toHaveBeenCalledWith(7, file, "unknown");

    await wrapper.get("[data-testid=make-final]").trigger("click");
    await flushPromises();
    expect(api.updateArtworkImage).toHaveBeenCalledWith(7, 5, { is_final: true });
  });

  it("saves the material classification and the separate conservation risk note", async () => {
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=material-classification]").setValue("immovable");
    await wrapper.get("[data-testid=risk-note]").setValue("Stored in a humid room.");
    await wrapper.findAll("button").find((b) => b.text() === "Save")!.trigger("click");
    await flushPromises();

    expect(api.updateArtwork.mock.calls[0][1]).toMatchObject({ material_classification: "immovable", conservation_risk_note: "Stored in a humid room." });
  });

  describe("draft mode (proposals.submit)", () => {
    it("saves the fields section through saveDraft instead of the direct endpoint", async () => {
      const wrapper = await mountPage(["artworks.manage", "proposals.submit"]);

      await wrapper.get("[data-testid=risk-note]").setValue("Humid storage.");
      await wrapper.get("[data-testid=save-button]").trigger("click");
      await flushPromises();

      expect(editorial.saveDraft).toHaveBeenCalledTimes(1);
      expect(editorial.saveDraft.mock.calls[0][0]).toBe("artworks");
      expect(editorial.saveDraft.mock.calls[0][1]).toBe(7);
      expect(editorial.saveDraft.mock.calls[0][2].fields).toMatchObject({ conservation_risk_note: "Humid storage.", category: "painting" });
      expect(api.updateArtwork).not.toHaveBeenCalled();
      expect(wrapper.get("[data-testid=saved-feedback]").text()).toBe("Saved to draft");
    });

    it("appends each pipeline toggle to the pipeline section instead of PATCHing the stage", async () => {
      const wrapper = await mountPage(["artworks.manage", "proposals.submit"]);

      await wrapper.get("[data-testid=pipeline] select").setValue("done");
      await flushPromises();

      expect(editorial.saveDraft).toHaveBeenCalledTimes(1);
      expect(editorial.saveDraft.mock.calls[0][2]).toEqual({ pipeline: [{ stage_key: "work_category", status: "done" }] });
      expect(api.updateArtworkStage).not.toHaveBeenCalled();
      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("draft");
    });

    it("initializes the form and the pipeline selects from draft sections", async () => {
      editorial.getDraft.mockResolvedValue({
        data: {
          status: "draft",
          payload: {
            fields: { title: { ar: null, en: "Draft Title" }, material_classification: "immovable", risk_note: null },
            pipeline: [{ stage_key: "work_category", status: "done" }],
          },
        },
      });
      const wrapper = await mountPage(["artworks.manage", "proposals.submit"]);

      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("draft");
      expect(wrapper.get("[data-testid=pipeline] select").element).toHaveProperty("value", "done");
      expect(wrapper.get("[data-testid=material-classification]").element).toHaveProperty("value", "immovable");
      const titleInput = wrapper.findAll("input[type=text]").find((i) => (i.element as HTMLInputElement).value === "Draft Title");
      expect(titleInput).toBeDefined();
    });

    it("shows the pending state and disables saving while a review is open", async () => {
      editorial.getDraft.mockResolvedValue({ data: { status: "pending", payload: {} } });
      const wrapper = await mountPage(["artworks.manage", "proposals.submit"]);

      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("pending");
      expect(wrapper.get("[data-testid=save-button]").attributes("disabled")).toBeDefined();
    });

    it("blocks editing with a non-actionable banner when another user's review is open", async () => {
      editorial.getDraft.mockRejectedValue(new ApiError("server", "conflict", { status: 409, body: { message: "conflict", proposal_id: "p9" } }));
      const wrapper = await mountPage(["artworks.manage", "proposals.submit"]);

      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("blocked");
      expect(wrapper.get("[data-testid=save-button]").attributes("disabled")).toBeDefined();
    });

    it("sends the draft for review and flips the banner to awaiting review", async () => {
      const wrapper = await mountPage(["artworks.manage", "proposals.submit"]);
      expect(wrapper.find("[data-testid=send-for-review]").exists()).toBe(false);

      await wrapper.get("[data-testid=save-button]").trigger("click");
      await flushPromises();
      await wrapper.get("[data-testid=send-for-review]").trigger("click");
      await flushPromises();

      expect(editorial.submitDraft).toHaveBeenCalledWith("artworks", 7);
      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("pending");
      expect(wrapper.get("[data-testid=draft-notice]").text()).toBe("Your draft was sent for review.");
    });
  });
});
