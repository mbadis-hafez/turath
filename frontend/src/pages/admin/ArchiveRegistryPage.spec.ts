import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArchiveRegistryPage from "@/pages/admin/ArchiveRegistryPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { AdminArchiveRow } from "@/types/archive";

const api = vi.hoisted(() => ({ listAdminArchive: vi.fn(), bulkArchive: vi.fn(), listArchiveItems: vi.fn() }));
vi.mock("@/api/archive", () => api);
vi.mock("@/api/artistCuration", () => ({ listAdminArtists: vi.fn() }));
vi.mock("@/api/artworkCuration", () => ({ searchHolders: vi.fn() }));

const row = (id: number, patch: Partial<AdminArchiveRow> = {}): AdminArchiveRow => ({
  id, legacy_ref: `ARC-${id}`, item_type: "image", title: { ar: `مادة ${id}`, en: `Item ${id}` }, date: "1979",
  source: { name: { ar: "أرشيف", en: "Archive" }, rights_status: "unknown" }, publication_status: "draft",
  incomplete: true, gap_count: 2, under_review: false, ...patch,
});

let router: Router;

async function mountPage() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions: ["archive.manage"] } as never, initialized: true });
  await router.push("/en/admin/archive");
  const wrapper = mountWithPlugins(ArchiveRegistryPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.listAdminArchive.mockReset().mockResolvedValue({
    data: [row(1), row(2, { publication_status: "published", incomplete: false }), row(3, { under_review: true })],
    meta: { current_page: 1, last_page: 1, per_page: 20, total: 3, from: null, to: null, total_all: 31240, mine_count: 114, incomplete_count: 24 },
  });
  api.bulkArchive.mockReset().mockResolvedValue({ data: { succeeded: [1], failed: [{ id: 2, message: "Rights unknown" }] } });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/archive", name: "admin.archive", component: ArchiveRegistryPage },
      { path: "/:locale/admin/imports", name: "admin.imports", component: { template: "<div />" } },
      { path: "/:locale/admin/archive/new", name: "admin.archive.new", component: { template: "<div />" } },
      { path: "/:locale/admin/archive/:id", name: "admin.archive.edit", component: { template: "<div />" } },
    ],
  });
});

describe("ArchiveRegistryPage", () => {
  it("shows the counts and a status badge per row with review taking precedence", async () => {
    const wrapper = await mountPage();

    expect(wrapper.get("[data-testid=summary]").text()).toContain("31240");
    expect(wrapper.get("[data-testid=summary]").text()).toContain("114 from your contributions");
    expect(wrapper.findAll("[data-testid=row-status]").map((s) => s.text())).toEqual(["Incomplete", "Published", "Under review"]);
  });

  it("selects rows, sends a bulk status change and lists the items that failed", async () => {
    const wrapper = await mountPage();

    await wrapper.findAll("[data-testid=row-select]")[0].setValue(true);
    await wrapper.findAll("[data-testid=row-select]")[1].setValue(true);
    expect(wrapper.get("[data-testid=bulk-bar]").text()).toContain("2 selected");

    await wrapper.get("[data-testid=bulk-status]").setValue("published");
    await flushPromises();

    expect(api.bulkArchive).toHaveBeenCalledWith({ ids: [1, 2], action: "set_status", status: "published" });
    expect(wrapper.get("[data-testid=bulk-failures]").text()).toContain("Rights unknown");
  });

  it("asks for confirmation before a bulk delete", async () => {
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=select-all]").setValue(true);

    await wrapper.get("[data-testid=bulk-delete]").trigger("click");
    expect(api.bulkArchive).not.toHaveBeenCalled();

    await wrapper.get("[data-testid=bulk-delete-confirm]").trigger("click");
    await flushPromises();
    expect(api.bulkArchive).toHaveBeenCalledWith({ ids: [1, 2, 3], action: "delete" });
  });
});
