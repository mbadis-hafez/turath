import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArchiveEditPage from "@/pages/admin/ArchiveEditPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";
import type { ArchiveEdit } from "@/types/archive";

const api = vi.hoisted(() => ({
  getAdminArchiveItem: vi.fn(), createArchiveItem: vi.fn(), updateArchiveItem: vi.fn(), uploadArchiveFile: vi.fn(),
  deleteArchiveFile: vi.fn(), submitArchiveReview: vi.fn(), publishArchiveItem: vi.fn(), addArchiveLink: vi.fn(), removeArchiveLink: vi.fn(),
  syncArchiveItemThemes: vi.fn(), listThemes: vi.fn(),
}));
vi.mock("@/api/archive", () => api);
const artistCuration = vi.hoisted(() => ({ updateArtistCuration: vi.fn() }));
vi.mock("@/api/artistCuration", () => ({ listAdminArtists: vi.fn(), listThemes: api.listThemes, updateArtistCuration: artistCuration.updateArtistCuration }));
vi.mock("@/api/artworkCuration", () => ({ searchHolders: vi.fn(), listAdminArtworks: vi.fn() }));
vi.mock("@/api/events", () => ({ listAdminEvents: vi.fn() }));
const dashboardApi = vi.hoisted(() => ({ addFieldCitation: vi.fn() }));
vi.mock("@/api/dashboard", () => dashboardApi);
vi.mock("@/components/curation/ArtworkPickers", () => ({
  labelOf: (t: { ar: string | null; en: string | null }) => t.ar ?? t.en ?? "",
  searchArtistOptions: vi.fn().mockResolvedValue([{ id: 7, label: "منيرة الموصلي" }]),
}));

const editorial = vi.hoisted(() => ({ getDraft: vi.fn(), saveDraft: vi.fn(), submitDraft: vi.fn() }));
vi.mock("@/api/editorial", () => editorial);

function bundle(patch: Partial<ArchiveEdit> = {}): ArchiveEdit {
  return {
    id: 5, legacy_ref: "ARC-1979-0412", item_type: "image", title: { ar: "افتتاح معرض", en: null }, description: { ar: null, en: null },
    place: { ar: null, en: null }, date_note: null, theme_ids: [2], content: { display: "1979 (approx.)", year_from: 1979, year_to: 1979, calendar: "gregorian", certainty: "circa" },
    people_names: [], keywords: [], source_name: null, rights_holder: { ar: null, en: null }, rights_status: "unknown", license: null,
    digitized_at: null,
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

async function mountAt(path: string, permissions: string[] = ["archive.manage"]) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions } as never, initialized: true });
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
  api.syncArchiveItemThemes.mockReset().mockResolvedValue({});
  api.listThemes.mockReset().mockResolvedValue({ data: [{ id: 2, label: { ar: "التأسيس", en: "Founding" } }, { id: 3, label: { ar: "الطبيعة", en: "Nature" } }] });
  api.submitArchiveReview.mockReset().mockResolvedValue({ data: bundle({ under_review: true }) });
  api.publishArchiveItem.mockReset().mockResolvedValue({ data: bundle({ publication_status: "published" }) });
  artistCuration.updateArtistCuration.mockReset().mockResolvedValue({ data: {} });
  dashboardApi.addFieldCitation.mockReset().mockResolvedValue({});
  editorial.getDraft.mockReset().mockResolvedValue({ data: null });
  editorial.saveDraft.mockReset().mockResolvedValue({ data: { status: "draft" } });
  editorial.submitDraft.mockReset().mockResolvedValue({ data: { status: "pending" } });
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

  it("hides the Publish button without archive.publish", async () => {
    const wrapper = await mountAt("/en/admin/archive/5");

    expect(wrapper.find("[data-testid=publish]").exists()).toBe(false);
  });

  it("publishes the item and refreshes its status", async () => {
    const wrapper = await mountAt("/en/admin/archive/5", ["archive.manage", "archive.publish"]);
    api.getAdminArchiveItem.mockResolvedValueOnce({ data: bundle({ publication_status: "published" }) });

    await wrapper.get("[data-testid=publish]").trigger("click");
    await flushPromises();

    expect(api.publishArchiveItem).toHaveBeenCalledWith(5);
    expect(wrapper.get("[data-testid=publish]").text()).toBe("Published");
    expect(wrapper.get("[data-testid=publish]").attributes("disabled")).toBeDefined();
  });

  describe("artist proof links", () => {
    async function pickLinkedArtist(wrapper: Awaited<ReturnType<typeof mountAt>>): Promise<void> {
      await wrapper.get("[data-testid=picker-input]").setValue("مني");
      await new Promise((r) => setTimeout(r, 260));
      await flushPromises();
      await wrapper.get("[data-testid=picker-options] button").trigger("click");
    }

    it("offers the proof roles only for an existing artist link, with artists.manage", async () => {
      const wrapper = await mountAt("/en/admin/archive/5", ["archive.manage", "artists.manage"]);
      await wrapper.get("[data-testid=link-open]").trigger("click");

      const roleValues = () => wrapper.get("[data-testid=link-role]").findAll("option").map((o) => o.attributes("value"));
      expect(roleValues()).toEqual(expect.arrayContaining(["authorization_letter", "name_verification"]));

      await wrapper.get("[data-testid=link-role]").setValue("authorization_letter");
      await wrapper.get("[data-testid=link-kind]").setValue("artwork");
      expect(roleValues()).not.toEqual(expect.arrayContaining(["authorization_letter", "name_verification"]));
      expect((wrapper.get("[data-testid=link-role]").element as HTMLSelectElement).value).toBe("about");
    });

    it("hides the proof roles without artists.manage", async () => {
      const wrapper = await mountAt("/en/admin/archive/5", ["archive.manage"]);
      await wrapper.get("[data-testid=link-open]").trigger("click");

      const roleValues = wrapper.get("[data-testid=link-role]").findAll("option").map((o) => o.attributes("value"));
      expect(roleValues).not.toEqual(expect.arrayContaining(["authorization_letter", "name_verification"]));
    });

    it("signs the artist's authorization letter with this item's file", async () => {
      const wrapper = await mountAt("/en/admin/archive/5", ["archive.manage", "artists.manage"]);
      await wrapper.get("[data-testid=link-open]").trigger("click");
      await wrapper.get("[data-testid=link-role]").setValue("authorization_letter");
      await pickLinkedArtist(wrapper);

      await wrapper.get("[data-testid=link-apply]").trigger("click");
      await flushPromises();

      expect(artistCuration.updateArtistCuration).toHaveBeenCalledWith(7, { authorization_letter_status: "signed", authorization_letter_file_id: 1 });
      expect(api.addArchiveLink).toHaveBeenCalledWith(5, { linkable_type: "artist", linkable_id: 7, role: "authorization_letter" });
    });

    it("cites the item as the artist's primary source", async () => {
      const wrapper = await mountAt("/en/admin/archive/5", ["archive.manage", "artists.manage"]);
      await wrapper.get("[data-testid=link-open]").trigger("click");
      await wrapper.get("[data-testid=link-role]").setValue("name_verification");
      await pickLinkedArtist(wrapper);

      await wrapper.get("[data-testid=link-apply]").trigger("click");
      await flushPromises();

      expect(dashboardApi.addFieldCitation).toHaveBeenCalledWith("artist", 7, {
        field_key: "name", new_source: { linked_archive_item_id: 5 }, claimed_value: "منيرة الموصلي",
      });
      expect(api.addArchiveLink).toHaveBeenCalledWith(5, { linkable_type: "artist", linkable_id: 7, role: "name_verification" });
    });

    it("blocks the authorization-letter link when the item has no file yet", async () => {
      api.getAdminArchiveItem.mockResolvedValue({ data: bundle({ file: null }) });
      const wrapper = await mountAt("/en/admin/archive/5", ["archive.manage", "artists.manage"]);
      await wrapper.get("[data-testid=link-open]").trigger("click");
      await wrapper.get("[data-testid=link-role]").setValue("authorization_letter");
      await pickLinkedArtist(wrapper);

      await wrapper.get("[data-testid=link-apply]").trigger("click");
      await flushPromises();

      expect(artistCuration.updateArtistCuration).not.toHaveBeenCalled();
      expect(api.addArchiveLink).not.toHaveBeenCalled();
      expect(wrapper.text()).toContain("Upload this item's file before using it as an authorization letter.");
    });
  });

  describe("draft mode (proposals.submit)", () => {
    it("saves the fields section through saveDraft instead of the direct endpoint", async () => {
      const wrapper = await mountAt("/en/admin/archive/5", ["archive.manage", "proposals.submit"]);

      await wrapper.get("[data-testid=license]").setValue("CC0");
      await wrapper.get("[data-testid=save-draft]").trigger("click");
      await flushPromises();

      expect(editorial.saveDraft).toHaveBeenCalledTimes(1);
      expect(editorial.saveDraft.mock.calls[0][0]).toBe("archive-items");
      expect(editorial.saveDraft.mock.calls[0][1]).toBe(5);
      expect(editorial.saveDraft.mock.calls[0][2].fields).toMatchObject({ license: "CC0", item_type: "image" });
      expect(api.updateArchiveItem).not.toHaveBeenCalled();
      expect(api.syncArchiveItemThemes).toHaveBeenCalledWith(5, [2]);
      expect(wrapper.get("[data-testid=saved-feedback]").text()).toBe("Saved to draft");
    });

    it("initializes the form from the draft fields section instead of live data", async () => {
      editorial.getDraft.mockResolvedValue({
        data: {
          status: "draft",
          payload: { fields: { title: { ar: "مسودة العنوان", en: null }, license: "CC BY", description: { ar: "وصف", en: "Desc" } } },
        },
      });
      const wrapper = await mountAt("/en/admin/archive/5", ["archive.manage", "proposals.submit"]);

      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("draft");
      expect(wrapper.get("[data-testid=title-ar]").element).toHaveProperty("value", "مسودة العنوان");
      expect(wrapper.get("[data-testid=license]").element).toHaveProperty("value", "CC BY");
    });

    it("shows the pending state and disables saving while a review is open", async () => {
      editorial.getDraft.mockResolvedValue({ data: { status: "pending", payload: {} } });
      const wrapper = await mountAt("/en/admin/archive/5", ["archive.manage", "proposals.submit"]);

      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("pending");
      expect(wrapper.get("[data-testid=save-draft]").attributes("disabled")).toBeDefined();
    });

    it("blocks editing with a non-actionable banner when another user's review is open", async () => {
      editorial.getDraft.mockRejectedValue(new ApiError("server", "conflict", { status: 409, body: { message: "conflict", proposal_id: "p9" } }));
      const wrapper = await mountAt("/en/admin/archive/5", ["archive.manage", "proposals.submit"]);

      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("blocked");
      expect(wrapper.get("[data-testid=save-draft]").attributes("disabled")).toBeDefined();
      expect(editorial.saveDraft).not.toHaveBeenCalled();
    });

    it("sends the draft for review and flips the banner to awaiting review", async () => {
      const wrapper = await mountAt("/en/admin/archive/5", ["archive.manage", "proposals.submit"]);
      expect(wrapper.find("[data-testid=send-for-review]").exists()).toBe(false);

      await wrapper.get("[data-testid=save-draft]").trigger("click");
      await flushPromises();
      await wrapper.get("[data-testid=send-for-review]").trigger("click");
      await flushPromises();

      expect(editorial.submitDraft).toHaveBeenCalledWith("archive-items", 5);
      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("pending");
      expect(wrapper.get("[data-testid=draft-notice]").text()).toBe("Your draft was sent for review.");
    });
  });
});

describe("ArchiveEditPage (themes)", () => {
  it("shows the item's themes ticked and saves the changed selection", async () => {
    const wrapper = await mountAt("/en/admin/archive/5");
    const boxes = () => wrapper.findAll("[data-testid=themes] input").map((b) => (b.element as HTMLInputElement).checked);
    expect(boxes()).toEqual([true, false]);

    await wrapper.findAll("[data-testid=themes] input")[1].setValue(true);
    await wrapper.get("[data-testid=save-draft]").trigger("click");
    await flushPromises();

    expect(api.syncArchiveItemThemes).toHaveBeenCalledWith(5, [2, 3]);
  });

  it("tags a new item with the chosen themes right after creating it", async () => {
    const wrapper = await mountAt("/en/admin/archive/new");
    await wrapper.findAll("[data-testid=themes] input")[0].setValue(true);
    api.getAdminArchiveItem.mockResolvedValue({ data: bundle() });

    await wrapper.get("[data-testid=save-draft]").trigger("click");
    await flushPromises();

    expect(api.syncArchiveItemThemes).toHaveBeenCalledWith(5, [2]);
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
