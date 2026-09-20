import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtistCurationPage from "@/pages/admin/ArtistCurationPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { ArtistCuration } from "@/types/artistCuration";

const api = vi.hoisted(() => ({ getArtistCuration: vi.fn(), updateArtistCuration: vi.fn(), verifyArtist: vi.fn() }));
vi.mock("@/api/artistCuration", () => api);

function bundle(patch: Partial<ArtistCuration> = {}): ArtistCuration {
  return {
    id: 36, slug: "ahmad", legacy_code: "AR036", name: { ar: "أحمد المغلوث", en: "Ahmad Almaghlout" },
    city: { ar: "الأحساء", en: "Alahsa" }, life_dates: { birth: null, death: null },
    name_as_in_sources: null, identified_through: { note: "Site visit", date: null },
    bio: { ar: null, en: "Saudi artist.", source_type: "derived_from_linked_materials" },
    verified_status: "unverified",
    contact: { key_contact_name: "Ahmad", owner_type: "artist", contact_email: "a@b.co", contact_phone: null, ref_supervisor_note: null },
    pipeline: { authorization_letter: { status: "not_started", file_id: null, file_name: null }, owner_pre_agreement: { status: "not_started" } },
    checklist: [
      { key: "name", tier: "core", met: true, supported: true },
      { key: "name_verified", tier: "core", met: false, supported: true },
      { key: "portrait", tier: "important", met: false, supported: false },
    ],
    public_visibility: "hidden",
    verify_blockers: { "data.primary_source": ["Missing required field: Primary source."], "pipeline.authorization_letter": ["Authorization letter is not_started."] },
    themes: [], linked_materials: [{ id: 1, legacy_ref: "ARC1", item_type: "image", year: "1978", title: { ar: null, en: "Photo" }, completeness_pct: 30, gap_count: 6 }],
    ...patch,
  };
}

let router: Router;

async function mountPage() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions: ["artists.manage"] } as never, initialized: true });
  await router.push("/en/admin/artists/36");
  const wrapper = mountWithPlugins(ArtistCurationPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.getArtistCuration.mockReset().mockResolvedValue({ data: bundle() });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/artists", name: "admin.artists", component: { template: "<div />" } },
      { path: "/:locale/admin/artists/:id", name: "admin.artists.show", component: ArtistCurationPage },
    ],
  });
});

describe("ArtistCurationPage", () => {
  it("disables Verify and names the first blocking reason from the API", async () => {
    const wrapper = await mountPage();
    const button = wrapper.get("[data-testid=verify-button]");

    expect(button.attributes("disabled")).toBeDefined();
    expect(button.attributes("title")).toBe("Cannot verify yet: Missing required field: Primary source.");
    expect(wrapper.get("[data-testid=visibility]").text()).toBe("Hidden");
  });

  it("enables Verify when nothing blocks and calls the verify endpoint", async () => {
    api.getArtistCuration.mockResolvedValue({ data: bundle({ verify_blockers: {}, public_visibility: "visible" }) });
    api.verifyArtist.mockResolvedValue({});
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=verify-button]").trigger("click");
    await flushPromises();

    expect(api.verifyArtist).toHaveBeenCalledWith(36);
  });

  it("renders the checklist, the provisional-bio badge and the linked material's gap count", async () => {
    const wrapper = await mountPage();

    const boxes = wrapper.findAll("[data-testid=checklist] input");
    expect(boxes.map((b) => (b.element as HTMLInputElement).checked)).toEqual([true, false, false]);
    expect(wrapper.text()).toContain("not tracked yet");
    expect(wrapper.find("[data-testid=provisional-bio]").exists()).toBe(true);
    expect(wrapper.get("[data-testid=materials]").text()).toContain("6 gaps");
  });

  it("highlights missing identity fields in red and counts them, like the mockup", async () => {
    api.getArtistCuration.mockResolvedValue({ data: bundle({ checklist: [
      { key: "name", tier: "core", met: true, supported: true },
      { key: "name_verified", tier: "core", met: false, supported: true },
      { key: "life_dates", tier: "core", met: false, supported: true },
    ] }) });
    const wrapper = await mountPage();

    expect(wrapper.get("[data-testid=name-verified-field]").classes()).toContain("bg-danger-soft");
    expect(wrapper.get("[data-testid=life-dates-field]").text()).toBe("Not recorded");
    expect(wrapper.get("[data-testid=identity-missing]").text()).toBe("2 fields missing");
    expect(wrapper.get("[data-testid=work-path]").text()).toContain("Name verified");
    expect(wrapper.text()).toContain("1 of 3 required fields met");
    expect(wrapper.text()).toContain("Ahmad Almaghlout");
  });

  it("shows the internal contact section only to holders of artists.manage", async () => {
    const wrapper = await mountPage();
    expect(wrapper.find("[data-testid=contact-section]").exists()).toBe(true);

    useAuthStore().$patch({ user: { id: 2, name: "R", email: "r@x", roles: ["reader"], permissions: [] } as never });
    await flushPromises();
    expect(wrapper.find("[data-testid=contact-section]").exists()).toBe(false);
  });
});
