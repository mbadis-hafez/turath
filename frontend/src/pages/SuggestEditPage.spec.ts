import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import SuggestEditPage from "@/pages/SuggestEditPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { Artist } from "@/types/artist";

const api = vi.hoisted(() => ({ getArtist: vi.fn(), getArtwork: vi.fn(), submitProposal: vi.fn() }));
vi.mock("@/api/artists", () => ({ getArtist: api.getArtist }));
vi.mock("@/api/artworks", () => ({ getArtwork: api.getArtwork }));
vi.mock("@/api/proposals", () => ({ submitProposal: api.submitProposal }));

const artist = {
  id: 7, slug: "ahmad", name: { ar: "أحمد", en: "Ahmad" }, bio: { ar: null, en: "A painter." },
  birth: { display: null, year_from: 1953, year_to: null, calendar: "gregorian", certainty: "exact", place: { ar: "مكة", en: "Makkah" } },
  death: null, living_status: "living", verified_status: "verified", legacy_code: null, also_known_as: [],
  verified_at: null, publication_status: "published", created_at: "", updated_at: "",
} as unknown as Artist;

let router: Router;
let pinia: ReturnType<typeof createPinia>;

function signIn(permissions: string[]) {
  pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 3, name: "Nora", email: "n@x", roles: ["contributor"], permissions } as never, initialized: true });
}

async function mountPage() {
  await router.push("/en/suggest/artists/ahmad");
  const wrapper = mountWithPlugins(SuggestEditPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.getArtist.mockReset().mockResolvedValue({ data: artist });
  api.submitProposal.mockReset().mockResolvedValue({ data: { id: "p9" } });
  signIn(["proposals.submit"]);
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/suggest/:type/:id", name: "suggest", component: SuggestEditPage },
      { path: "/:locale/proposals", name: "proposals", component: { template: "<div />" } },
    ],
  });
});

describe("SuggestEditPage", () => {
  it("offers Submit for review rather than a save, and cannot submit until something changed with a reason", async () => {
    const wrapper = await mountPage();
    const submit = wrapper.get("[data-testid=submit-for-review]");

    expect(submit.text()).toBe("Submit for review");
    expect(wrapper.text()).not.toContain("Save");
    expect(submit.attributes("disabled")).toBeDefined();
    expect(wrapper.get("[data-testid=diff-preview]").text()).toContain("Change at least one field");

    await wrapper.get("[data-testid=field-bio_en]").setValue("A painter from Alahsa.");
    expect(submit.attributes("disabled")).toBeDefined(); // still needs a rationale

    await wrapper.get("[data-testid=rationale-input]").setValue("Page 12 of the catalogue.");
    expect(submit.attributes("disabled")).toBeUndefined();
  });

  it("previews only the fields that actually changed, old against new", async () => {
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=field-bio_en]").setValue("A painter from Alahsa.");
    await wrapper.get("[data-testid=field-name_en]").setValue("Ahmad");

    const rows = wrapper.findAll("[data-testid=preview-row]");
    expect(rows).toHaveLength(1);
    expect(rows[0].text()).toContain("A painter.");
    expect(rows[0].text()).toContain("A painter from Alahsa.");
  });

  it("submits only the changed fields with the rationale, then confirms", async () => {
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=field-bio_en]").setValue("A painter from Alahsa.");
    await wrapper.get("[data-testid=rationale-input]").setValue("Page 12 of the catalogue.");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.submitProposal).toHaveBeenCalledWith("artists", 7, {
      changes: { bio_en: "A painter from Alahsa." },
      rationale: "Page 12 of the catalogue.",
    });
    expect(wrapper.find("[data-testid=submitted]").exists()).toBe(true);
  });

  it("sends null when a contributor clears a field", async () => {
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=field-bio_en]").setValue("");
    await wrapper.get("[data-testid=rationale-input]").setValue("The bio was about someone else.");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.submitProposal.mock.calls[0][2].changes).toEqual({ bio_en: null });
  });

  it("refuses the page to a user without permission to suggest", async () => {
    signIn([]);
    const wrapper = await mountPage();

    expect(wrapper.find("[data-testid=submit-for-review]").exists()).toBe(false);
    expect(api.getArtist).not.toHaveBeenCalled();
  });
});
