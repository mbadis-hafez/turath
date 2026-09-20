import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtworkCreatePage from "@/pages/admin/ArtworkCreatePage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";

const api = vi.hoisted(() => ({ createArtwork: vi.fn(), uploadArtworkImage: vi.fn(), updateArtworkImage: vi.fn(), searchHolders: vi.fn() }));
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
  URL.createObjectURL = vi.fn(() => "blob:x");
  URL.revokeObjectURL = vi.fn();
  api.createArtwork.mockReset().mockResolvedValue({ data: { id: 12 } });
  api.uploadArtworkImage.mockReset().mockResolvedValue({ data: [{ id: 99 }] });
  api.updateArtworkImage.mockReset().mockResolvedValue({ data: [] });
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

    await wrapper.get("[data-testid=untitled-input]").setValue(true);
    expect(wrapper.get("[data-testid=create-submit]").attributes("disabled")).toBeUndefined();
  });

  it("updates the live checklist from the form", async () => {
    const wrapper = await mountPage();
    const boxes = () => wrapper.findAll("[data-testid=checklist] input").map((b) => (b.element as HTMLInputElement).checked);
    expect(boxes()[0]).toBe(false);

    await wrapper.get("[data-testid=code-input]").setValue("AW001");
    expect(boxes()[0]).toBe(true);
  });

  it("creates a draft, uploads the queued image as final, and opens the detail page", async () => {
    const wrapper = await mountPage();
    await wrapper.findAll("input[type=text]")[1].setValue("تكوين");

    const file = new File(["x"], "a.jpg", { type: "image/jpeg" });
    const input = wrapper.get("[data-testid=image-input]");
    Object.defineProperty(input.element, "files", { value: [file] });
    await input.trigger("change");

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.createArtwork.mock.calls[0][0]).toMatchObject({ publication_status: "draft", attribution_certainty: "unattributed", artist_id: null, title: { ar: "تكوين", en: null } });
    expect(api.uploadArtworkImage).toHaveBeenCalledWith(12, file, "unknown");
    expect(api.updateArtworkImage).toHaveBeenCalledWith(12, 99, { is_final: true });
    expect(router.currentRoute.value.path).toBe("/en/admin/artworks/12");
  });

  it("stays on the page with a link when an image upload fails after the artwork was created", async () => {
    api.uploadArtworkImage.mockRejectedValue(new Error("boom"));
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=untitled-input]").setValue(true);
    const input = wrapper.get("[data-testid=image-input]");
    Object.defineProperty(input.element, "files", { value: [new File(["x"], "a.jpg", { type: "image/jpeg" })] });
    await input.trigger("change");

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(wrapper.find("[data-testid=images-failed]").exists()).toBe(true);
    expect(router.currentRoute.value.path).toBe("/en/admin/artworks/new");
    expect(wrapper.get("[data-testid=create-submit]").attributes("disabled")).toBeDefined();
  });
});
