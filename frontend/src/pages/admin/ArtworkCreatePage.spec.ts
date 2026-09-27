import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtworkCreatePage from "@/pages/admin/ArtworkCreatePage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";

const api = vi.hoisted(() => ({ createArtwork: vi.fn(), uploadArtworkImages: vi.fn(), updateArtworkImage: vi.fn(), searchHolders: vi.fn() }));
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
  api.uploadArtworkImages.mockReset().mockResolvedValue({ data: [{ id: 99 }], results: [{ filename: "a.jpg", status: "attached", image_id: 99, message: null }] });
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
    expect(api.uploadArtworkImages).toHaveBeenCalledWith(12, [file], "unknown");
    expect(api.updateArtworkImage).toHaveBeenCalledWith(12, 99, { is_final: true });
    expect(router.currentRoute.value.path).toBe("/en/admin/artworks/12");
  });

  it("queues every file from a single multi-file selection and uploads them in one batch call", async () => {
    api.uploadArtworkImages.mockResolvedValue({
      data: [{ id: 97 }, { id: 98 }, { id: 99 }],
      results: [
        { filename: "a.jpg", status: "attached", image_id: 97, message: null },
        { filename: "b.jpg", status: "attached", image_id: 98, message: null },
        { filename: "c.jpg", status: "attached", image_id: 99, message: null },
      ],
    });
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=untitled-input]").setValue(true);

    const files = [
      new File(["a"], "a.jpg", { type: "image/jpeg" }),
      new File(["b"], "b.jpg", { type: "image/jpeg" }),
      new File(["c"], "c.jpg", { type: "image/jpeg" }),
    ];
    const input = wrapper.get("[data-testid=image-input]");
    Object.defineProperty(input.element, "files", { value: files });
    await input.trigger("change");

    expect(wrapper.findAll("[data-testid=image-list] li")).toHaveLength(3);

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.uploadArtworkImages).toHaveBeenCalledTimes(1);
    expect(api.uploadArtworkImages).toHaveBeenCalledWith(12, files, "unknown");
    expect(api.updateArtworkImage).toHaveBeenCalledWith(12, 97, { is_final: true });
  });

  it("marks the first queued image primary, lets another be chosen, and promotes on removal", async () => {
    const wrapper = await mountPage();

    const files = [
      new File(["a"], "a.jpg", { type: "image/jpeg" }),
      new File(["b"], "b.jpg", { type: "image/jpeg" }),
    ];
    const input = wrapper.get("[data-testid=image-input]");
    Object.defineProperty(input.element, "files", { value: files });
    await input.trigger("change");
    await flushPromises();

    // The first queued image is primary automatically.
    let rows = wrapper.findAll("[data-testid=image-list] li");
    expect(rows[0].find("[data-testid=primary-badge]").exists()).toBe(true);
    expect(rows[1].find("[data-testid=primary-badge]").exists()).toBe(false);

    // Designating the second clears the first.
    await rows[1].get("[data-testid=make-final]").trigger("click");
    rows = wrapper.findAll("[data-testid=image-list] li");
    expect(rows[0].find("[data-testid=primary-badge]").exists()).toBe(false);
    expect(rows[1].find("[data-testid=primary-badge]").exists()).toBe(true);

    // Removing the primary promotes the remaining image.
    await rows[1].get("button:not([data-testid=make-final])").trigger("click");
    rows = wrapper.findAll("[data-testid=image-list] li");
    expect(rows).toHaveLength(1);
    expect(rows[0].find("[data-testid=primary-badge]").exists()).toBe(true);
  });

  it("queues files dropped onto the panel exactly like a multi-file selection", async () => {
    const wrapper = await mountPage();

    const files = [
      new File(["a"], "a.jpg", { type: "image/jpeg" }),
      new File(["b"], "b.jpg", { type: "image/jpeg" }),
      new File(["c"], "c.jpg", { type: "image/jpeg" }),
    ];
    const dropZone = wrapper.get("[data-testid=drop-zone]");
    await dropZone.trigger("drop", { dataTransfer: { files } });

    expect(wrapper.findAll("[data-testid=image-list] li")).toHaveLength(3);
  });

  it("skips a file that's already queued and tells the registrar why", async () => {
    const wrapper = await mountPage();
    const file = new File(["a"], "a.jpg", { type: "image/jpeg" });
    const input = wrapper.get("[data-testid=image-input]");

    Object.defineProperty(input.element, "files", { value: [file], configurable: true });
    await input.trigger("change");
    expect(wrapper.findAll("[data-testid=image-list] li")).toHaveLength(1);

    // Re-selecting the identical file (same name/size/lastModified).
    Object.defineProperty(input.element, "files", { value: [file], configurable: true });
    await input.trigger("change");

    expect(wrapper.findAll("[data-testid=image-list] li")).toHaveLength(1);
    expect(wrapper.get("[data-testid=image-issues]").text()).toContain("a.jpg");
  });

  it("keeps successfully attached images and names each rejected/duplicate one when a batch partially fails", async () => {
    api.uploadArtworkImages.mockResolvedValue({
      data: [{ id: 97 }],
      results: [
        { filename: "a.jpg", status: "attached", image_id: 97, message: null },
        { filename: "b.jpg", status: "duplicate", image_id: 50, message: "Already attached to this artwork." },
        { filename: "c.pdf", status: "rejected", image_id: null, message: "Must be a JPEG, PNG or WebP image." },
      ],
    });
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=untitled-input]").setValue(true);

    const files = [
      new File(["a"], "a.jpg", { type: "image/jpeg" }),
      new File(["b"], "b.jpg", { type: "image/jpeg" }),
      new File(["c"], "c.pdf", { type: "application/pdf" }),
    ];
    const input = wrapper.get("[data-testid=image-input]");
    Object.defineProperty(input.element, "files", { value: files });
    await input.trigger("change");

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    // The artwork was created and the good image attached, but a partial
    // failure keeps the registrar on this page — with a link to the artwork
    // and each failure named — rather than silently losing the report.
    expect(router.currentRoute.value.path).toBe("/en/admin/artworks/new");
    const issues = wrapper.get("[data-testid=image-issues]").text();
    expect(issues).toContain("b.jpg");
    expect(issues).toContain("c.pdf");
  });

  it("stays on the page with a link when an image upload fails after the artwork was created", async () => {
    api.uploadArtworkImages.mockRejectedValue(new Error("boom"));
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
