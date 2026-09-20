import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtworkCreatePage from "@/pages/admin/ArtworkCreatePage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";

const api = vi.hoisted(() => ({ createArtwork: vi.fn(), searchHolders: vi.fn() }));
vi.mock("@/api/artworkCuration", () => api);
vi.mock("@/api/artistCuration", () => ({ listAdminArtists: vi.fn() }));

let router: Router;

async function mountPage() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions: ["artworks.manage"] } as never, initialized: true });
  await router.push("/en/admin/artworks/new");
  const wrapper = mountWithPlugins(ArtworkCreatePage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.createArtwork.mockReset().mockResolvedValue({ data: { id: 12 } });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/artworks", name: "admin.artworks", component: { template: "<div />" } },
      { path: "/:locale/admin/artworks/new", name: "admin.artworks.new", component: ArtworkCreatePage },
      { path: "/:locale/admin/artworks/:id", name: "admin.artworks.show", component: { template: "<div />" } },
    ],
  });
});

describe("ArtworkCreatePage", () => {
  it("blocks submit until a title is given or the work is untitled", async () => {
    const wrapper = await mountPage();
    expect(wrapper.get("[data-testid=create-submit]").attributes("disabled")).toBeDefined();

    await wrapper.get("input[type=checkbox]").setValue(true);
    expect(wrapper.get("[data-testid=create-submit]").attributes("disabled")).toBeUndefined();
  });

  it("creates a draft, treats a missing artist as unattributed, and opens the detail page", async () => {
    const wrapper = await mountPage();

    await wrapper.findAll("input[type=text]")[1].setValue("تكوين");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    const payload = api.createArtwork.mock.calls[0][0];
    expect(payload).toMatchObject({ publication_status: "draft", attribution_certainty: "unattributed", artist_id: null, title: { ar: "تكوين", en: null } });
    expect(router.currentRoute.value.path).toBe("/en/admin/artworks/12");
  });
});
