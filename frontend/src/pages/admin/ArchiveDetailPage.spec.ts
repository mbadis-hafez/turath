import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArchiveDetailPage from "@/pages/admin/ArchiveDetailPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { ArchiveEdit } from "@/types/archive";

const api = vi.hoisted(() => ({
  getAdminArchiveItem: vi.fn(), bulkArchive: vi.fn(),
  getArchiveItemFileOcr: vi.fn(), acceptExtractedField: vi.fn(), rejectExtractedField: vi.fn(), editExtractedField: vi.fn(), acceptHighConfidenceFields: vi.fn(), runArchiveItemFileOcr: vi.fn(),
  ocrPageImageUrl: (itemId: number, page: number) => `/api/v1/archive-items/${itemId}/file/ocr/pages/${page}/image`,
  ocrRegionCropUrl: (itemId: number, regionId: number) => `/api/v1/archive-items/${itemId}/file/ocr/regions/${regionId}/crop`,
}));
vi.mock("@/api/archive", () => api);

const activityApi = vi.hoisted(() => ({ fetchSubjectActivity: vi.fn() }));
vi.mock("@/api/activity", () => activityApi);

// PdfViewer is loaded async (see ArchiveDetailPage.vue) and uses the real pdfjs-dist
// engine internally; stub that engine here rather than the component itself, since
// vue-test-utils' auto-stubbing doesn't play well with defineAsyncComponent boundaries.
const pdfPage = { getViewport: () => ({ width: 100, height: 140 }), render: () => ({ promise: Promise.resolve(), cancel: vi.fn() }) };
vi.mock("pdfjs-dist", () => ({
  GlobalWorkerOptions: {},
  getDocument: vi.fn(() => ({ promise: Promise.resolve({ numPages: 2, getPage: vi.fn().mockResolvedValue(pdfPage), destroy: vi.fn() }) })),
}));
vi.mock("pdfjs-dist/build/pdf.worker.min.mjs?url", () => ({ default: "worker.js" }));
HTMLCanvasElement.prototype.getContext = vi.fn().mockReturnValue({}) as never;

function bundle(patch: Partial<ArchiveEdit> = {}): ArchiveEdit {
  return {
    id: 5, legacy_ref: "ARC-1979-0412", item_type: "image", title: { ar: "افتتاح معرض", en: "Exhibition opening" },
    description: { ar: "وصف المادة", en: "Item description" }, place: { ar: "بيروت", en: null },
    date_note: null, theme_ids: [], content: { display: "1979", year_from: 1979, year_to: 1979, calendar: "gregorian", certainty: "exact" },
    people_names: [], keywords: [], source_name: "Family archive", rights_holder: { ar: "أسرة الفنان", en: null },
    rights_status: "licensed", license: "CC BY", digitized_at: "2020-01-01", verification_reference: "REF-1",
    access_level: "public", publication_status: "published", under_review: false, updated_at: "2024-01-01T00:00:00Z",
    file: { id: 1, name: "a.jpg", mime_type: "image/jpeg", size_bytes: 2048, width_px: 800, height_px: 600, is_image: true, url: "/f", ocr_status: null, ocr_progress_pct: null },
    checklist: [
      { key: "title_ar", met: true }, { key: "type_and_file", met: true }, { key: "date", met: true },
      { key: "rights_holder_license", met: true }, { key: "people_names", met: true },
    ],
    completeness_pct: 100,
    links: [{ id: 9, role: "depicts", kind: "artist", entity_id: 3, label: { ar: "منيرة الموصلي", en: null } }],
    ...patch,
  };
}

let router: Router;

async function mountAt(path: string, permissions: string[] = ["archive.manage"]) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions } as never, initialized: true });
  await router.push(path);
  const wrapper = mountWithPlugins(ArchiveDetailPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.getAdminArchiveItem.mockReset().mockResolvedValue({ data: bundle() });
  api.bulkArchive.mockReset();
  api.getArchiveItemFileOcr.mockReset().mockResolvedValue({ data: null });
  api.acceptExtractedField.mockReset().mockResolvedValue({ data: {} });
  api.rejectExtractedField.mockReset().mockResolvedValue({ data: {} });
  api.editExtractedField.mockReset().mockResolvedValue({ data: {} });
  api.acceptHighConfidenceFields.mockReset().mockResolvedValue({ data: { accepted: [] } });
  api.runArchiveItemFileOcr.mockReset().mockResolvedValue({ data: { status: "pending" } });
  activityApi.fetchSubjectActivity.mockReset().mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 24, total: 0, last_page: 1, from: null, to: null } });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/dashboard", name: "dashboard", component: { template: "<div />" } },
      { path: "/:locale/admin/archive", name: "admin.archive", component: { template: "<div />" } },
      { path: "/:locale/admin/archive/:id(\\d+)", name: "admin.archive.edit", component: { template: "<div />" } },
      { path: "/:locale/admin/archive/:id(\\d+)/view", name: "admin.archive.show", component: ArchiveDetailPage },
      { path: "/:locale/admin/artists/:id(\\d+)", name: "admin.artists.show", component: { template: "<div />" } },
      { path: "/:locale/admin/artworks/:id(\\d+)", name: "admin.artworks.show", component: { template: "<div />" } },
      { path: "/:locale/admin/events/:id(\\d+)", name: "admin.events.edit", component: { template: "<div />" } },
    ],
  });
});

describe("ArchiveDetailPage", () => {
  it("shows a forbidden state without archive.manage", async () => {
    const wrapper = await mountAt("/en/admin/archive/5/view", []);
    expect(wrapper.text()).toContain("don't have access");
  });

  it("shows an error state on load failure with a retry option", async () => {
    api.getAdminArchiveItem.mockRejectedValue(new Error("network down"));
    const wrapper = await mountAt("/en/admin/archive/5/view");
    expect(wrapper.text()).toContain("Something went wrong");

    api.getAdminArchiveItem.mockResolvedValue({ data: bundle() });
    await wrapper.get("button").trigger("click");
    await flushPromises();
    expect(wrapper.text()).toContain("Exhibition opening");
  });

  it("renders the title, badges, description and metadata table for a loaded item", async () => {
    const wrapper = await mountAt("/en/admin/archive/5/view");

    expect(wrapper.text()).toContain("Exhibition opening");
    expect(wrapper.get("[data-testid=badge-row]").text()).toContain("ARC-1979-0412");
    expect(wrapper.get("[data-testid=description]").text()).toContain("Item description");
    expect(wrapper.get("[data-testid=meta-table]").text()).toContain("Family archive");
    expect(wrapper.get("[data-testid=meta-table]").text()).toContain("CC BY");
    expect(wrapper.find("[data-testid=file-preview]").exists()).toBe(true);
    expect(wrapper.find("[data-testid=unpublished-banner]").exists()).toBe(false);
  });

  it("shows the unpublished banner with missing fields when the item is incomplete or under review", async () => {
    api.getAdminArchiveItem.mockResolvedValue({
      data: bundle({
        publication_status: "draft",
        under_review: true,
        checklist: [{ key: "rights_holder_license", met: false }, { key: "date", met: true }],
      }),
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");

    expect(wrapper.get("[data-testid=unpublished-banner]").text()).toContain("Not published");
    expect(wrapper.get("[data-testid=unpublished-banner]").text()).toContain("under review");
    expect(wrapper.get("[data-testid=missing-fields]").text()).toContain("Rights holder and license");
  });

  it("lists related records linking to the artist page", async () => {
    const wrapper = await mountAt("/en/admin/archive/5/view");
    await wrapper.get("#tab-relations").trigger("click");

    const link = wrapper.get("[data-testid=related-link]");
    expect(link.text()).toContain("منيرة الموصلي");
    expect(link.attributes("href")).toBe("/en/admin/artists/3");
  });

  it("opens the delete dialog from the Delete action and redirects to the registry once deleted", async () => {
    api.bulkArchive.mockResolvedValue({ data: { succeeded: [5], failed: [] } });
    const wrapper = await mountAt("/en/admin/archive/5/view");

    await wrapper.get("[data-testid=delete-button]").trigger("click");
    await wrapper.get("[data-testid=delete-confirm-input]").setValue("ARC-1979-0412");
    await wrapper.get("[data-testid=delete-confirm-button]").trigger("click");
    await flushPromises();

    expect(api.bulkArchive).toHaveBeenCalledWith({ ids: [5], action: "delete" });
    expect(router.currentRoute.value.path).toBe("/en/admin/archive");
  });

  it("links the Edit action to the edit page", async () => {
    const wrapper = await mountAt("/en/admin/archive/5/view");
    expect(wrapper.get("[data-testid=edit-link]").attributes("href")).toBe("/en/admin/archive/5");
  });

  it("renders a PDF file through the PDF viewer", async () => {
    api.getAdminArchiveItem.mockResolvedValue({
      data: bundle({ file: { id: 2, name: "report.pdf", mime_type: "application/pdf", size_bytes: 4096, width_px: null, height_px: null, is_image: false, url: "/f/2", ocr_status: null, ocr_progress_pct: null } }),
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");
    // The PDF viewer loads as an async component and then loads the document itself,
    // so the number of microtask ticks needed before it appears isn't fixed — poll
    // instead of guessing a tick count.
    await vi.waitFor(() => {
      expect(wrapper.find("[data-testid=pdf-page-indicator]").exists()).toBe(true);
    });

    expect(wrapper.get("[data-testid=pdf-page-indicator]").text()).toContain("1");
    expect(wrapper.find("[data-testid=file-preview]").exists()).toBe(false);
  });

  it("renders a video file with native controls", async () => {
    api.getAdminArchiveItem.mockResolvedValue({
      data: bundle({ file: { id: 3, name: "clip.mp4", mime_type: "video/mp4", size_bytes: 4096, width_px: null, height_px: null, is_image: false, url: "/f/3", ocr_status: null, ocr_progress_pct: null } }),
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");
    expect(wrapper.get("[data-testid=file-preview-video]").attributes("src")).toBe("/f/3");
  });

  it("renders an audio file with native controls", async () => {
    api.getAdminArchiveItem.mockResolvedValue({
      data: bundle({ file: { id: 4, name: "note.m4a", mime_type: "audio/mp4", size_bytes: 4096, width_px: null, height_px: null, is_image: false, url: "/f/4", ocr_status: null, ocr_progress_pct: null } }),
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");
    expect(wrapper.get("[data-testid=file-preview-audio]").attributes("src")).toBe("/f/4");
  });

  it("shows a no-preview fallback with a download link for unsupported file types", async () => {
    api.getAdminArchiveItem.mockResolvedValue({
      data: bundle({ file: { id: 5, name: "draft.docx", mime_type: "application/vnd.openxmlformats-officedocument.wordprocessingml.document", size_bytes: 4096, width_px: null, height_px: null, is_image: false, url: "/f/5", ocr_status: null, ocr_progress_pct: null } }),
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");

    const fallback = wrapper.get("[data-testid=file-preview-unsupported]");
    expect(fallback.text()).toContain("No preview available");
    expect(fallback.get("a").attributes("href")).toBe("/f/5");
  });

  it("offers to run OCR on a file that was never processed, and starts it on click", async () => {
    api.getArchiveItemFileOcr.mockResolvedValue({ data: { status: null, progress_pct: null, language_confidence: null, failure_reason: null, texts: { ar: [], en: [] }, fields: [] } });
    const wrapper = await mountAt("/en/admin/archive/5/view");

    expect(wrapper.get("[data-testid=ocr-run-card]").text()).toContain("hasn't been processed");

    api.getArchiveItemFileOcr.mockResolvedValue({ data: { status: "pending", progress_pct: 0, language_confidence: null, failure_reason: null, texts: { ar: [], en: [] }, fields: [] } });
    await wrapper.get("[data-testid=run-ocr-button]").trigger("click");
    await flushPromises();

    expect(api.runArchiveItemFileOcr).toHaveBeenCalledWith(5);
    expect(wrapper.get("[data-testid=ocr-status-label]").text()).toContain("Queued");
  });

  it("shows OCR progress while processing and polls for updates", async () => {
    api.getArchiveItemFileOcr.mockResolvedValue({ data: { status: "processing", progress_pct: 40, language_confidence: null, failure_reason: null, texts: { ar: [], en: [] }, fields: [] } });
    const wrapper = await mountAt("/en/admin/archive/5/view");

    expect(wrapper.get("[data-testid=ocr-status-label]").text()).toContain("Processing");
    expect(wrapper.get("[data-testid=ocr-progress]").text()).toContain("40%");
  });

  it("polls for OCR updates while processing, and stops once complete", async () => {
    vi.useFakeTimers({ toFake: ["setTimeout", "clearTimeout"] });
    api.getArchiveItemFileOcr.mockResolvedValue({ data: { status: "processing", progress_pct: 40, language_confidence: null, failure_reason: null, texts: { ar: [], en: [] }, fields: [] } });
    await mountAt("/en/admin/archive/5/view");
    expect(api.getArchiveItemFileOcr).toHaveBeenCalledTimes(1);

    api.getArchiveItemFileOcr.mockResolvedValue({ data: { status: "completed", progress_pct: 100, language_confidence: null, failure_reason: null, texts: { ar: [], en: [] }, fields: [] } });
    await vi.advanceTimersByTimeAsync(3000);
    expect(api.getArchiveItemFileOcr).toHaveBeenCalledTimes(2);

    await vi.advanceTimersByTimeAsync(10000);
    expect(api.getArchiveItemFileOcr).toHaveBeenCalledTimes(2); // no further polling once completed

    vi.useRealTimers();
  });

  it("lets a reviewer re-run OCR on an already-completed file", async () => {
    api.getArchiveItemFileOcr.mockResolvedValue({
      data: { status: "completed", progress_pct: 100, language_confidence: { en: 90 }, failure_reason: null, texts: { ar: [], en: [] }, fields: [] },
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");

    await wrapper.get("[data-testid=rerun-ocr-button]").trigger("click");
    await flushPromises();

    expect(api.runArchiveItemFileOcr).toHaveBeenCalledWith(5);
  });

  it("shows the OCR processing checklist and jumps to the AI extraction tab from its button", async () => {
    api.getArchiveItemFileOcr.mockResolvedValue({
      data: {
        status: "completed", progress_pct: 100, language_confidence: { ar: 90, en: 80 }, failure_reason: null,
        texts: { ar: [{ page: 1, text: "مرحبا", confidence: 90, segments: [] }], en: [] },
        fields: [{ id: 9, field_key: "title_en", extracted_value: "Exhibition Opening 1979", confidence: 92, source_page: 1, status: "pending" }],
      },
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");

    const checklist = wrapper.get("[data-testid=ocr-checklist]").text();
    expect(checklist).toContain("OCR recognition complete");
    expect(checklist).toContain("Arabic extraction complete");
    expect(checklist).toContain("1 fields identified");

    await wrapper.get("[data-testid=view-extracted-fields]").trigger("click");
    expect(wrapper.find("#panel-aiExtraction").exists()).toBe(true);
    expect(wrapper.get("[data-testid=ocr-field-row]").text()).toContain("Exhibition Opening 1979");
  });

  it("lists extracted fields once OCR completes and lets a reviewer accept one", async () => {
    api.getArchiveItemFileOcr.mockResolvedValue({
      data: {
        status: "completed", progress_pct: 100, language_confidence: { en: 90 }, failure_reason: null, texts: { ar: [], en: [] },
        fields: [{ id: 9, field_key: "title_en", extracted_value: "Exhibition Opening 1979", confidence: 92, source_page: 1, status: "pending" }],
      },
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");
    expect(wrapper.get("[data-testid=ocr-review-note]").text()).toContain("1 fields need review");

    await wrapper.get("#tab-aiExtraction").trigger("click");
    const row = wrapper.get("[data-testid=ocr-field-row]");
    expect(row.text()).toContain("Exhibition Opening 1979");

    await row.get("[data-testid=accept-field]").trigger("click");
    await flushPromises();

    expect(api.acceptExtractedField).toHaveBeenCalledWith(5, 9);
  });

  it("edits an extracted field's value before accepting it", async () => {
    api.getArchiveItemFileOcr.mockResolvedValue({
      data: {
        status: "completed", progress_pct: 100, language_confidence: null, failure_reason: null, texts: { ar: [], en: [] },
        fields: [{ id: 9, field_key: "title_en", extracted_value: "Wrong reading", confidence: 60, source_page: 1, status: "pending" }],
      },
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");
    await wrapper.get("#tab-aiExtraction").trigger("click");

    await wrapper.get("[data-testid=edit-field]").trigger("click");
    await wrapper.get("[data-testid=edit-input]").setValue("Corrected reading");
    await wrapper.get("[data-testid=save-edit]").trigger("click");
    await flushPromises();

    expect(api.editExtractedField).toHaveBeenCalledWith(5, 9, "Corrected reading");
  });

  it("bulk-accepts high-confidence fields", async () => {
    api.getArchiveItemFileOcr.mockResolvedValue({
      data: {
        status: "completed", progress_pct: 100, language_confidence: null, failure_reason: null, texts: { ar: [], en: [] },
        fields: [{ id: 9, field_key: "title_en", extracted_value: "High confidence", confidence: 92, source_page: 1, status: "pending" }],
      },
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");
    await wrapper.get("#tab-aiExtraction").trigger("click");

    await wrapper.get("[data-testid=accept-high-confidence]").trigger("click");
    await flushPromises();

    expect(api.acceptHighConfidenceFields).toHaveBeenCalledWith(5, undefined);
  });

  it("loads and shows the record's activity once the Activity tab is opened", async () => {
    activityApi.fetchSubjectActivity.mockResolvedValue({
      data: [{ id: 1, event: "updated", subject_type: "App\\Models\\ArchiveItem", subject_id: 5, subject_label: "Exhibition opening", causer: { id: 2, name: "Noura" }, edit_summary: null, changes: [], created_at: "2024-01-01T00:00:00Z" }],
      meta: { current_page: 1, per_page: 24, total: 1, last_page: 1, from: 1, to: 1 },
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");
    expect(activityApi.fetchSubjectActivity).not.toHaveBeenCalled();

    await wrapper.get("#tab-activity").trigger("click");
    await flushPromises();

    expect(activityApi.fetchSubjectActivity).toHaveBeenCalledWith("archive-items", 5);
    expect(wrapper.text()).toContain("Noura");
  });

  it("shows an error instead of the timeline when the activity request fails", async () => {
    activityApi.fetchSubjectActivity.mockRejectedValue(new Error("network down"));
    const wrapper = await mountAt("/en/admin/archive/5/view");

    await wrapper.get("#tab-activity").trigger("click");
    await flushPromises();

    expect(wrapper.text()).toContain("network down");
  });

  it("shows the page a value was read from, with its region outlined, beside the review list", async () => {
    api.getArchiveItemFileOcr.mockResolvedValue({
      data: {
        status: "completed", progress_pct: 100, language_confidence: { ar: 80 }, failure_reason: null,
        texts: { ar: [{ page: 1, text: "", confidence: 80, segments: [] }, { page: 2, text: "", confidence: 80, segments: [] }], en: [] },
        regions: [], form_fields: [], dates: [],
        fields: [{
          id: 9, field_key: "title_ar", extracted_value: "افتتاح معرض", confidence: 92, source_page: 2, status: "pending", extraction_method: "ocr_derived",
          source_region: { id: 4, page_number: 2, region_type: "printed_text", bbox: { x: 10, y: 20, width: 30, height: 40 }, confidence: 92, ocr_text: "افتتاح معرض", ocr_allowed: true, has_correction_mark: false, has_crop: false },
        }],
      },
    });
    const wrapper = await mountAt("/en/admin/archive/5/view");
    await wrapper.get("#tab-aiExtraction").trigger("click");
    expect(wrapper.get("[data-testid=ocr-source-page]").attributes("src")).toContain("/pages/1/image");

    await wrapper.get("[data-testid=field-evidence-show-source]").trigger("click");

    expect(wrapper.get("[data-testid=ocr-source-page]").attributes("src")).toContain("/pages/2/image");
    expect(wrapper.get("[data-testid=ocr-source-focus-label]").text()).toContain("Title (Arabic)");
  });
});
