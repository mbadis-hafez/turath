import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArchiveEditPage from "@/pages/admin/ArchiveEditPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { ArchiveEdit } from "@/types/archive";

const api = vi.hoisted(() => ({
  getAdminArchiveItem: vi.fn(), createArchiveItem: vi.fn(), updateArchiveItem: vi.fn(), uploadArchiveFile: vi.fn(),
  deleteArchiveFile: vi.fn(), submitArchiveReview: vi.fn(), addArchiveLink: vi.fn(), removeArchiveLink: vi.fn(),
}));
vi.mock("@/api/archive", () => api);
vi.mock("@/api/artistCuration", () => ({ listAdminArtists: vi.fn() }));
vi.mock("@/api/artworkCuration", () => ({ searchHolders: vi.fn(), listAdminArtworks: vi.fn() }));

function bundle(patch: Partial<ArchiveEdit> = {}): ArchiveEdit {
  return {
    id: 5, legacy_ref: "ARC-1979-0412", item_type: "image", title: { ar: "افتتاح معرض", en: null }, description: { ar: null, en: null },
    place: { ar: null, en: null }, date_note: null, content: { display: "1979 (approx.)", year_from: 1979, year_to: 1979, calendar: "gregorian", certainty: "circa" },
    people_names: [], keywords: [], source_name: null, rights_holder: { ar: null, en: null }, rights_status: "unknown", license: null,
    verification_reference: null, access_level: "registered", publication_status: "draft", under_review: false, updated_at: null,
    file: { id: 1, name: "a.tif", mime_type: "image/tiff", size_bytes: 2048, width_px: 4200, height_px: 3100, is_image: false, url: "/f" },
    checklist: [
      { key: "title_ar", met: true }, { key: "type_and_file", met: true }, { key: "date", met: false },
      { key: "rights_holder_license", met: false }, { key: "people_names", met: false },
    ],
    completeness_pct: 40, links: [{ id: 9, role: "depicts", kind: "artist", entity_id: 3, label: { ar: "منيرة الموصلي", en: null } }],
    ...patch,
  };
}

let router: Router;

async function mountAt(path: string) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions: ["archive.manage"] } as never, initialized: true });
  await router.push(path);
  const wrapper = mountWithPlugins(ArchiveEditPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  URL.createObjectURL = vi.fn(() => "blob:x");
  URL.revokeObjectURL = vi.fn();
  api.getAdminArchiveItem.mockReset().mockResolvedValue({ data: bundle() });
  api.createArchiveItem.mockReset().mockResolvedValue({ data: { id: 5 } });
  api.updateArchiveItem.mockReset().mockResolvedValue({});
  api.uploadArchiveFile.mockReset().mockResolvedValue({ data: {} });
  api.addArchiveLink.mockReset().mockResolvedValue({});
  api.submitArchiveReview.mockReset().mockResolvedValue({ data: bundle({ under_review: true }) });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/dashboard", name: "dashboard", component: { template: "<div />" } },
      { path: "/:locale/admin/archive", name: "admin.archive", component: { template: "<div />" } },
      { path: "/:locale/admin/archive/new", name: "admin.archive.new", component: ArchiveEditPage },
      { path: "/:locale/admin/archive/:id", name: "admin.archive.edit", component: ArchiveEditPage },
    ],
  });
});

describe("ArchiveEditPage (edit)", () => {
  it("shows the checklist and links, and keeps Send for review disabled until everything is met", async () => {
    const wrapper = await mountAt("/en/admin/archive/5");

    expect(wrapper.get("[data-testid=pct]").text()).toContain("40%");
    expect(wrapper.get("[data-testid=links]").text()).toContain("منيرة الموصلي");
    expect(wrapper.get("[data-testid=send-review]").attributes("disabled")).toBeDefined();
    expect(wrapper.get("[data-testid=date-mode]").element).toHaveProperty("value", "approx");
    expect(wrapper.get("[data-testid=approx-text]").element).toHaveProperty("value", "1979 (approx.)");
  });

  it("saves without re-sending an untouched approximate date, and sends the exact date once the precision is switched", async () => {
    const wrapper = await mountAt("/en/admin/archive/5");

    await wrapper.get("[data-testid=save-draft]").trigger("click");
    await flushPromises();
    expect(api.updateArchiveItem.mock.calls[0][1]).not.toHaveProperty("content");

    await wrapper.get("[data-testid=date-mode]").setValue("exact");
    await wrapper.get("[data-testid=date-input]").setValue("1979-03-14");
    await wrapper.get("[data-testid=save-draft]").trigger("click");
    await flushPromises();
    expect(api.updateArchiveItem.mock.calls[1][1].content).toMatchObject({ display: "1979-03-14", certainty: "exact", year_from: 1979 });
  });

  it("submits for review once complete, saving the form first", async () => {
    api.getAdminArchiveItem.mockResolvedValue({ data: bundle({ checklist: bundle().checklist.map((c) => ({ ...c, met: true })) }) });
    const wrapper = await mountAt("/en/admin/archive/5");

    await wrapper.get("[data-testid=send-review]").trigger("click");
    await flushPromises();

    expect(api.updateArchiveItem).toHaveBeenCalledBefore(api.submitArchiveReview);
    expect(api.submitArchiveReview).toHaveBeenCalledWith(5);
    expect(wrapper.get("[data-testid=send-review]").text()).toBe("Under review");
  });
});

describe("ArchiveEditPage (approximate dates)", () => {
  it("counts an approximate date only once its reason is given, and sends it as a circa span", async () => {
    const wrapper = await mountAt("/en/admin/archive/new");
    const dateMet = () => (wrapper.findAll("[data-testid=checklist] input")[2].element as HTMLInputElement).checked;

    await wrapper.get("[data-testid=date-mode]").setValue("approx");
    await wrapper.get("[data-testid=approx-text]").setValue("أوائل الثمانينيات الميلادية");
    await wrapper.get("[data-testid=approx-from]").setValue("1980");
    await wrapper.get("[data-testid=approx-to]").setValue("1983");
    expect(dateMet()).toBe(false);
    expect(wrapper.text()).toContain("needs a stated reason");

    await wrapper.get("[data-testid=date-note]").setValue("The card only says early 1980s.");
    expect(dateMet()).toBe(true);

    await wrapper.get("[data-testid=save-draft]").trigger("click");
    await flushPromises();
    expect(api.createArchiveItem.mock.calls[0][0]).toMatchObject({
      content: { display: "أوائل الثمانينيات الميلادية", year_from: 1980, year_to: 1983, certainty: "circa" },
      date_note: "The card only says early 1980s.",
    });
  });

  it("treats a bare year as an exact date", async () => {
    const wrapper = await mountAt("/en/admin/archive/new");
    await wrapper.get("[data-testid=date-mode]").setValue("year");
    await wrapper.get("[data-testid=year-input]").setValue("1984");

    expect((wrapper.findAll("[data-testid=checklist] input")[2].element as HTMLInputElement).checked).toBe(true);
  });
});

describe("ArchiveEditPage (add)", () => {
  it("updates the live checklist, then creates a draft with the queued file and links and opens the item", async () => {
    const wrapper = await mountAt("/en/admin/archive/new");
    const met = () => wrapper.findAll("[data-testid=checklist] input").map((b) => (b.element as HTMLInputElement).checked);
    expect(met()).toEqual([false, false, false, false, false]);

    await wrapper.get("[data-testid=title-ar]").setValue("افتتاح");
    await wrapper.get("[data-testid=date-input]").setValue("1979-03-14");
    const file = new File(["x"], "a.jpg", { type: "image/jpeg" });
    const input = wrapper.get("[data-testid=file-input]");
    Object.defineProperty(input.element, "files", { value: [file] });
    await input.trigger("change");
    expect(met()).toEqual([true, true, true, false, false]);

    api.getAdminArchiveItem.mockResolvedValue({ data: bundle() });
    await wrapper.get("[data-testid=save-draft]").trigger("click");
    await flushPromises();

    expect(api.createArchiveItem.mock.calls[0][0]).toMatchObject({ publication_status: "draft", title: { ar: "افتتاح", en: null }, content: { display: "1979-03-14", certainty: "exact" } });
    expect(api.uploadArchiveFile).toHaveBeenCalledWith(5, file);
    expect(router.currentRoute.value.path).toBe("/en/admin/archive/5");
  });

  it("stays put with a link when the file upload fails after the item was created", async () => {
    api.uploadArchiveFile.mockRejectedValue(new Error("boom"));
    const wrapper = await mountAt("/en/admin/archive/new");
    const input = wrapper.get("[data-testid=file-input]");
    Object.defineProperty(input.element, "files", { value: [new File(["x"], "a.jpg", { type: "image/jpeg" })] });
    await input.trigger("change");

    await wrapper.get("[data-testid=save-draft]").trigger("click");
    await flushPromises();

    expect(wrapper.find("[data-testid=partial-failure]").exists()).toBe(true);
    expect(router.currentRoute.value.path).toBe("/en/admin/archive/new");
  });
});
