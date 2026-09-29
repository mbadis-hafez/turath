import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtistsRegistryPage from "@/pages/admin/ArtistsRegistryPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { AdminArtistRow } from "@/types/artistCuration";

const api = vi.hoisted(() => ({ listAdminArtists: vi.fn(), listThemes: vi.fn(), deleteArtist: vi.fn() }));
vi.mock("@/api/artistCuration", () => api);

function row(patch: Partial<AdminArtistRow> = {}): AdminArtistRow {
  return {
    id: 1, slug: "ahmad", legacy_code: "AR001", name: { ar: "أحمد", en: "Ahmad" },
    publication_status: "draft",
    verified_status: "unverified", creation_approved_at: "2026-09-20T10:00:00Z",
    city: { ar: null, en: null }, owner_type: null, linked_material_count: 0, gap_count: 0,
    severity: "minor", themes: [],
    ...patch,
  };
}

let router: Router;

async function mountPage() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions: ["artists.manage"] } as never, initialized: true });
  await router.push("/en/admin/artists");
  const wrapper = mountWithPlugins(ArtistsRegistryPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.listAdminArtists.mockReset().mockResolvedValue({
    data: [row()],
    meta: { current_page: 1, last_page: 1, per_page: 24, total: 1 },
  });
  api.listThemes.mockReset().mockResolvedValue({ data: [] });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/artists", name: "admin.artists", component: ArtistsRegistryPage },
      { path: "/:locale/admin/artists/new", name: "admin.artists.new", component: { template: "<div />" } },
      { path: "/:locale/admin/artists/:id", name: "admin.artists.show", component: { template: "<div />" } },
      { path: "/:locale/artists/:slug", name: "artists.show", component: { template: "<div />" } },
    ],
  });
});

describe("ArtistsRegistryPage", () => {
  it("marks an artist whose creation is still awaiting review", async () => {
    api.listAdminArtists.mockResolvedValue({
      data: [row({ creation_approved_at: null })],
      meta: { current_page: 1, last_page: 1, per_page: 24, total: 1 },
    });
    const wrapper = await mountPage();

    expect(wrapper.get("[data-testid=creation-pending-badge]").text()).toBe("New record — awaiting creation review");
  });

  it("shows no badge for an already-reviewed artist", async () => {
    const wrapper = await mountPage();

    expect(wrapper.find("[data-testid=creation-pending-badge]").exists()).toBe(false);
  });

  it("shows view/edit/delete actions and deletes after confirmation", async () => {
    const wrapper = await mountPage();

    expect(wrapper.get("[data-testid=artist-view]").attributes("href")).toContain("/artists/ahmad");
    expect(wrapper.get("[data-testid=artist-delete]").text()).toBe("Delete");

    await wrapper.get("[data-testid=artist-delete]").trigger("click");
    await flushPromises();
    (document.querySelector("[data-testid=confirm-dialog-confirm]") as HTMLElement).click();
    await flushPromises();

    expect(api.deleteArtist).toHaveBeenCalledWith(1);
    expect(api.listAdminArtists).toHaveBeenCalledTimes(2);
  });

  it("hides delete on a published artist for a non-admin", async () => {
    api.listAdminArtists.mockResolvedValue({
      data: [row({ publication_status: "published" })],
      meta: { current_page: 1, last_page: 1, per_page: 24, total: 1 },
    });
    const wrapper = await mountPage();

    expect(wrapper.find("[data-testid=artist-view]").exists()).toBe(true);
    expect(wrapper.find("[data-testid=artist-delete]").exists()).toBe(false);
  });
});
