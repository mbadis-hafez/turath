import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtistCreatePage from "@/pages/admin/ArtistCreatePage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";

const api = vi.hoisted(() => ({ createArtist: vi.fn(), updateArtistCuration: vi.fn() }));
vi.mock("@/api/artistCuration", () => api);

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
  it("disables Create until a name is entered and updates the checklist live", async () => {
    const wrapper = await mountPage();
    expect(wrapper.get("[data-testid=create-submit]").attributes("disabled")).toBeDefined();
    expect(wrapper.text()).toContain("0 of 8 required fields met");

    await wrapper.get("input[lang=ar]").setValue("فنان جديد");
    await wrapper.get("input[lang=en]").setValue("New Artist");
    await wrapper.get("input[placeholder=AR150]").setValue("AR150");

    expect(wrapper.get("[data-testid=create-submit]").attributes("disabled")).toBeUndefined();
    expect(wrapper.text()).toContain("2 of 8 required fields met");
  });

  it("creates the artist, saves the internal fields, and opens the new record", async () => {
    const wrapper = await mountPage();
    await wrapper.get("input[lang=en]").setValue("New Artist");
    await wrapper.get("input[type=email]").setValue("a@b.co");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.createArtist).toHaveBeenCalledWith(expect.objectContaining({ name: { ar: null, en: "New Artist" }, living_status: "unknown" }));
    expect(api.updateArtistCuration).toHaveBeenCalledWith(77, expect.objectContaining({ contact_email: "a@b.co" }));
    expect(router.currentRoute.value.path).toBe("/en/admin/artists/77");
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
