import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtistCreatePage from "@/pages/admin/ArtistCreatePage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { ProfileCompletenessPreviewRequest, ProfileCompletenessSummary } from "@/types/completeness";

const api = vi.hoisted(() => ({
  createArtist: vi.fn(), updateArtistCuration: vi.fn(), syncArtistEntries: vi.fn(), syncArtistSocialLinks: vi.fn(),
  uploadArtistPortrait: vi.fn(), searchStaffOptions: vi.fn(), updateArtistAssignment: vi.fn(), previewArtistCompleteness: vi.fn(),
}));
vi.mock("@/api/artistCuration", () => api);

/** Mirrors the backend's 11-field rules closely enough for the live-preview test. */
function previewSummary(body: ProfileCompletenessPreviewRequest): ProfileCompletenessSummary {
  const checks: boolean[] = [
    Boolean(body.legacy_code),
    Boolean(body.name?.ar),
    Boolean(body.name?.en),
    Boolean(body.birth_place?.ar || body.birth_place?.en),
    body.living_status !== "unknown",
    body.birth?.year_from != null,
    body.living_status === "living" || body.death?.year_from != null,
    Boolean(body.nationality?.ar || body.nationality?.en),
    Boolean(body.bio?.ar),
    Boolean(body.bio?.en),
    Boolean(body.portrait_uploaded),
  ];
  const met = checks.filter(Boolean).length;
  const section = (indexes: number[]) => {
    const sectionMet = indexes.filter((i) => checks[i]).length;
    return { met: sectionMet, total: indexes.length, percentage: indexes.length === 0 ? 0 : Math.round((sectionMet / indexes.length) * 100) };
  };
  return {
    percentage: Math.round((met / checks.length) * 100),
    met_count: met,
    total_count: checks.length,
    complete: met === checks.length,
    sections: { identity: section([0, 1, 2, 3, 4, 5, 6, 7]), biography: section([8, 9]), media: section([10]) },
    missing: [],
  };
}

let router: Router;

async function mountPage() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions: ["artists.manage"] } as never, initialized: true });
  await router.push("/en/admin/artists/new");
  const wrapper = mountWithPlugins(ArtistCreatePage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.createArtist.mockReset().mockResolvedValue({ data: { id: 77 } });
  api.updateArtistCuration.mockReset().mockResolvedValue({ data: {} });
  api.syncArtistEntries.mockReset().mockResolvedValue({});
  api.syncArtistSocialLinks.mockReset().mockResolvedValue({});
  api.uploadArtistPortrait.mockReset().mockResolvedValue({ data: {} });
  api.previewArtistCompleteness.mockReset().mockImplementation((body: ProfileCompletenessPreviewRequest) =>
    Promise.resolve({ data: previewSummary(body) }));
  api.searchStaffOptions.mockReset().mockResolvedValue({ data: [{ id: 5, name: "Samar", email: "samar@hafezgallery.com" }] });
  api.updateArtistAssignment.mockReset().mockResolvedValue({ data: { id: 5, name: "Samar", email: "samar@hafezgallery.com" } });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/artists", name: "admin.artists", component: { template: "<div />" } },
      { path: "/:locale/admin/artists/new", name: "admin.artists.new", component: ArtistCreatePage },
      { path: "/:locale/admin/artists/:id", name: "admin.artists.show", component: { template: "<div />" } },
    ],
  });
});

describe("ArtistCreatePage", () => {
  it("disables Create until a name is entered and updates the completeness preview live", async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const wrapper = await mountPage();
    await vi.advanceTimersByTimeAsync(300);
    await flushPromises();
    expect(wrapper.get("[data-testid=create-submit]").attributes("disabled")).toBeDefined();
    expect(wrapper.get("[data-testid=required-met]").text()).toBe("0 of 11 required fields met");

    await wrapper.get("input[lang=ar]").setValue("فنان جديد");
    await wrapper.get("input[lang=en]").setValue("New Artist");
    await wrapper.get("input[placeholder=AR150]").setValue("AR150");
    await vi.advanceTimersByTimeAsync(300);
    await flushPromises();

    expect(wrapper.get("[data-testid=create-submit]").attributes("disabled")).toBeUndefined();
    expect(wrapper.get("[data-testid=required-met]").text()).toBe("3 of 11 required fields met");
    expect(api.previewArtistCompleteness).toHaveBeenCalled();
    expect(api.previewArtistCompleteness.mock.calls.at(-1)![0]).toMatchObject({
      name: { ar: "فنان جديد", en: "New Artist" },
      legacy_code: "AR150",
      living_status: "unknown",
      portrait_uploaded: false,
      portrait_rights_status: "unknown",
    });
    vi.useRealTimers();
  });

  it("maps the date inputs to years and living status in the preview body", async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const wrapper = await mountPage();
    await vi.advanceTimersByTimeAsync(300);
    await flushPromises();

    const livingSelect = wrapper
      .findAll("select")
      .find((s) => s.findAll("option").some((o) => o.attributes("value") === "living"));
    await livingSelect!.setValue("living");
    await wrapper.findAll("input[type=date]")[0]!.setValue("1900-06-15");
    await vi.advanceTimersByTimeAsync(300);
    await flushPromises();

    expect(api.previewArtistCompleteness.mock.calls.at(-1)![0]).toMatchObject({
      birth: { year_from: 1900 },
      living_status: "living",
    });
    vi.useRealTimers();
  });

  it("keeps the form usable when the completeness preview request fails", async () => {
    api.previewArtistCompleteness.mockReset().mockRejectedValue(new Error("boom"));
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const wrapper = await mountPage();
    await vi.advanceTimersByTimeAsync(300);
    await flushPromises();

    expect(wrapper.find("[data-testid=completeness-panel]").exists()).toBe(false);

    await wrapper.get("input[lang=en]").setValue("New Artist");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.createArtist).toHaveBeenCalled();
    vi.useRealTimers();
  });

  it("creates the artist, saves the internal fields, and opens the new record", async () => {
    const wrapper = await mountPage();
    await wrapper.get("input[lang=en]").setValue("New Artist");
    await wrapper.get("[data-testid=contact-section] [data-testid=list-add]").trigger("click");
    await wrapper.get("[data-testid=contact-section] input[type=email]").setValue("a@b.co");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.createArtist).toHaveBeenCalledWith(expect.objectContaining({ name: { ar: null, en: "New Artist" }, living_status: "unknown" }));
    expect(api.updateArtistCuration).toHaveBeenCalledWith(77, expect.objectContaining({ contacts: [expect.objectContaining({ email: "a@b.co" })] }));
    expect(router.currentRoute.value.path).toBe("/en/admin/artists/77");
  });

  it("searches and assigns a real staff member, then saves the assignment after the artist is created", async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const wrapper = await mountPage();
    await wrapper.get("input[lang=en]").setValue("New Artist");

    const picker = wrapper.get("[data-testid=assigned-staff]");
    await picker.get("[data-testid=picker-input]").setValue("Samar");
    await vi.advanceTimersByTimeAsync(300);
    await flushPromises();
    expect(api.searchStaffOptions).toHaveBeenCalledWith("Samar");
    await picker.get("[data-testid=picker-options] button").trigger("click");
    expect(picker.get("[data-testid=picked]").text()).toContain("Samar");

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.updateArtistAssignment).toHaveBeenCalledWith(77, 5);
    vi.useRealTimers();
  });

  it("does not call the assignment endpoint when no staff member was picked", async () => {
    const wrapper = await mountPage();
    await wrapper.get("input[lang=en]").setValue("New Artist");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.updateArtistAssignment).not.toHaveBeenCalled();
  });

  it("shows API field errors and stays on the page", async () => {
    api.createArtist.mockImplementation(() => Promise.reject(Object.assign(new Error("bad"), {})));
    const wrapper = await mountPage();
    await wrapper.get("input[lang=en]").setValue("X");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.updateArtistCuration).not.toHaveBeenCalled();
    expect(router.currentRoute.value.path).toBe("/en/admin/artists/new");
    expect(wrapper.text()).toContain("bad");
  });
});
