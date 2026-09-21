import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import MaterialSubmissionsPage from "@/pages/admin/MaterialSubmissionsPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { SubmissionDetail, SubmissionRow } from "@/types/submission";

const api = vi.hoisted(() => ({
  listSubmissions: vi.fn(), getSubmission: vi.fn(), updateSubmission: vi.fn(), catalogSubmission: vi.fn(), rejectSubmission: vi.fn(), submitMaterial: vi.fn(),
  listAdminArtists: vi.fn(), listAdminArtworks: vi.fn(), searchHolders: vi.fn(),
}));
vi.mock("@/api/submissions", () => api);
vi.mock("@/api/artistCuration", () => ({ listAdminArtists: api.listAdminArtists }));
vi.mock("@/api/artworkCuration", () => ({ listAdminArtworks: api.listAdminArtworks, searchHolders: api.searchHolders }));

const row = (id: number, status: SubmissionRow["status"] = "submitted"): SubmissionRow => ({
  id, submitter_name: "Abdullah", submitter_contact: "0505", submitter_role: "artist_family", city: "Saihat",
  description: "Twelve photographs.", status, file_count: 2, submitted_at: "2026-09-01T10:00:00Z",
});
const detail = (patch: Partial<SubmissionDetail> = {}): SubmissionDetail => ({
  ...row(5), linked_artist: null, linked_artwork: null, authorization_letter_status: "not_started", staff_notes: null, reviewed_at: null,
  files: [
    { id: 1, name: "a.jpg", mime_type: "image/jpeg", size_bytes: 2048, url: "/f/1" },
    { id: 2, name: "b.pdf", mime_type: "application/pdf", size_bytes: 4096, url: "/f/2" },
  ],
  archive_items: [], ...patch,
});

let router: Router;
let pinia: ReturnType<typeof createPinia>;

function signIn(permissions: string[]) {
  pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions } as never, initialized: true });
}

async function mountPage() {
  await router.push("/en/admin/materials");
  const wrapper = mountWithPlugins(MaterialSubmissionsPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

async function open(wrapper: Awaited<ReturnType<typeof mountPage>>) {
  await wrapper.get("[data-testid=submission-toggle]").trigger("click");
  await flushPromises();
}

beforeEach(() => {
  signIn(["materials.review"]);
  api.listSubmissions.mockReset().mockResolvedValue({ data: [row(5)], meta: { current_page: 1, last_page: 1, per_page: 24, total: 1, from: null, to: null } });
  api.getSubmission.mockReset().mockResolvedValue({ data: detail() });
  api.updateSubmission.mockReset().mockResolvedValue({ data: detail() });
  api.catalogSubmission.mockReset().mockResolvedValue({ data: { ...detail(), created_archive_item_ids: [9] } });
  api.rejectSubmission.mockReset().mockResolvedValue({ data: detail({ status: "rejected" }) });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/materials", name: "admin.materials", component: MaterialSubmissionsPage },
      { path: "/:locale/admin/archive/:id", name: "admin.archive.edit", component: { template: "<div />" } },
    ],
  });
});

describe("MaterialSubmissionsPage", () => {
  it("is refused to users without materials.review, and does not even load the list", async () => {
    signIn([]);
    const wrapper = await mountPage();

    expect(wrapper.find("[data-testid=submissions-list]").exists()).toBe(false);
    expect(api.listSubmissions).not.toHaveBeenCalled();
  });

  it("filters the queue by status", async () => {
    const wrapper = await mountPage();
    expect(api.listSubmissions.mock.calls[0][0]).toEqual({ status: "", page: 1 });

    await wrapper.get("[data-testid=status-filter]").setValue("cataloging");
    await flushPromises();

    expect(api.listSubmissions.mock.lastCall![0]).toEqual({ status: "cataloging", page: 1 });
  });

  it("shows the submitter, their contact and every staged file when a row is opened", async () => {
    const wrapper = await mountPage();
    await open(wrapper);

    expect(wrapper.get("[data-testid=submitter-contact]").text()).toBe("0505");
    expect(wrapper.findAll("[data-testid=staged-files] li")).toHaveLength(2);
  });

  it("starts a review from a new submission", async () => {
    const wrapper = await mountPage();
    await open(wrapper);

    await wrapper.get("[data-testid=start-review]").trigger("click");
    await flushPromises();

    expect(api.updateSubmission).toHaveBeenCalledWith(5, { status: "initial_review" });
  });

  it("offers cataloging only during review, one item per file by default, and can fold them into one", async () => {
    api.getSubmission.mockResolvedValue({ data: detail({ status: "initial_review" }) });
    const wrapper = await mountPage();
    await open(wrapper);

    expect(wrapper.findAll("[data-testid=catalog-row]")).toHaveLength(2);
    await wrapper.get("[data-testid=single-item]").setValue(true);
    expect(wrapper.findAll("[data-testid=catalog-row]")).toHaveLength(1);
  });

  it("does not offer cataloging on a brand new submission", async () => {
    const wrapper = await mountPage();
    await open(wrapper);

    expect(wrapper.find("[data-testid=catalog-panel]").exists()).toBe(false);
  });

  it("catalogs each file into its own item with the entered titles", async () => {
    api.getSubmission.mockResolvedValue({ data: detail({ status: "initial_review" }) });
    const wrapper = await mountPage();
    await open(wrapper);

    const titles = wrapper.findAll("[data-testid=catalog-title-ar]");
    await titles[0].setValue("صورة الافتتاح");
    await titles[1].setValue("خطاب");
    await wrapper.get("[data-testid=catalog]").trigger("click");
    await flushPromises();

    expect(api.catalogSubmission).toHaveBeenCalledWith(5, [
      { item_type: "image", title: { ar: "صورة الافتتاح", en: null }, file_ids: [1] },
      { item_type: "document", title: { ar: "خطاب", en: null }, file_ids: [2] },
    ]);
  });

  it("will not reject without a reason, and warns that the files are deleted", async () => {
    const wrapper = await mountPage();
    await open(wrapper);

    await wrapper.get("[data-testid=reject-open]").trigger("click");
    expect(wrapper.get("[data-testid=submission-detail]").text()).toContain("deleted immediately");
    await wrapper.get("[data-testid=reject-confirm]").trigger("click");
    await flushPromises();
    expect(api.rejectSubmission).not.toHaveBeenCalled();
    expect(wrapper.get("[data-testid=detail-error]").text()).toContain("reason");

    await wrapper.get("[data-testid=reject-note]").setValue("Not about Saudi art.");
    await wrapper.get("[data-testid=reject-confirm]").trigger("click");
    await flushPromises();
    expect(api.rejectSubmission).toHaveBeenCalledWith(5, "Not about Saudi art.");
  });

  it("only allows marking published once the authorization letter is signed", async () => {
    api.getSubmission.mockResolvedValue({ data: detail({ status: "authorization_pending", authorization_letter_status: "pending" }) });
    const wrapper = await mountPage();
    await open(wrapper);
    expect(wrapper.get("[data-testid=mark-published]").attributes("disabled")).toBeDefined();

    api.getSubmission.mockResolvedValue({ data: detail({ status: "authorization_pending", authorization_letter_status: "signed" }) });
    await wrapper.get("[data-testid=save-letter]").trigger("click");
    await flushPromises();
    expect(wrapper.get("[data-testid=mark-published]").attributes("disabled")).toBeUndefined();
  });

  it("shows a closed submission read-only, without any action buttons", async () => {
    api.getSubmission.mockResolvedValue({ data: detail({ status: "rejected", staff_notes: "Not relevant." }) });
    const wrapper = await mountPage();
    await open(wrapper);

    expect(wrapper.find("[data-testid=start-review]").exists()).toBe(false);
    expect(wrapper.find("[data-testid=reject-open]").exists()).toBe(false);
    expect((wrapper.get("[data-testid=staff-notes]").element as HTMLTextAreaElement).value).toBe("Not relevant.");
  });
});
