import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";

import MergeToolModal from "@/components/curation/MergeToolModal.vue";
import { mountWithPlugins } from "@/test/utils";

const api = vi.hoisted(() => ({ getArtistCuration: vi.fn(), listAdminArtists: vi.fn(), mergeArtists: vi.fn() }));
vi.mock("@/api/artistCuration", () => api);

const row = (id: number, en: string) => ({ id, slug: en, legacy_code: `AR${id}`, name: { ar: null, en }, verified_status: "unverified", city: { ar: null, en: null }, owner_type: null, linked_material_count: 0, gap_count: 0, severity: "clear", themes: [] });
const curation = (id: number, bioEn: string | null) => ({
  data: { id, name: { ar: null, en: "Same Name" }, bio: { ar: null, en: bioEn, source_type: "unspecified" }, nationality: { ar: null, en: null }, contact: { owner_type: null } },
});

beforeEach(() => {
  api.listAdminArtists.mockReset().mockImplementation(async ({ q }: { q: string }) => ({ data: q === "keep" ? [row(1, "Keep")] : [row(2, "Dup")] }));
  api.getArtistCuration.mockReset().mockImplementation(async (id: number) => (id === 1 ? curation(1, null) : curation(2, "Real bio")));
  api.mergeArtists.mockReset().mockResolvedValue({});
});

async function pick(wrapper: ReturnType<typeof mountWithPlugins>, side: number, term: string) {
  const input = wrapper.findAll("input[type=search]")[side]!;
  await input.setValue(term);
  await input.trigger("input");
  await flushPromises();
  await wrapper.findAll("ul button")[0]!.trigger("click");
  await flushPromises();
}

describe("MergeToolModal", () => {
  it("defaults to the duplicate's value where the survivor is empty and submits the field_resolution", async () => {
    const wrapper = mountWithPlugins(MergeToolModal, { locale: "en" });
    await pick(wrapper, 0, "keep");
    await pick(wrapper, 1, "dup");

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.mergeArtists).toHaveBeenCalledWith({ survivor_id: 1, duplicate_id: 2, field_resolution: { bio_en: "duplicate" } });
    expect(wrapper.emitted("merged")).toHaveLength(1);
  });

  it("keeps submit disabled until two different artists are chosen", async () => {
    const wrapper = mountWithPlugins(MergeToolModal, { locale: "en" });
    expect(wrapper.get("button[type=submit]").attributes("disabled")).toBeDefined();
  });
});
