import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";

import CreateArtistModal from "@/components/curation/CreateArtistModal.vue";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";

const api = vi.hoisted(() => ({ createArtist: vi.fn() }));
vi.mock("@/api/artistCuration", () => api);

beforeEach(() => api.createArtist.mockReset());

describe("CreateArtistModal", () => {
  it("keeps submit disabled until a name is entered", async () => {
    const wrapper = mountWithPlugins(CreateArtistModal, { locale: "en" });
    expect(wrapper.get("button[type=submit]").attributes("disabled")).toBeDefined();

    await wrapper.get("input[lang=en]").setValue("New Artist");
    expect(wrapper.get("button[type=submit]").attributes("disabled")).toBeUndefined();
  });

  it("creates the artist and emits its id", async () => {
    api.createArtist.mockResolvedValue({ data: { id: 77 } });
    const wrapper = mountWithPlugins(CreateArtistModal, { locale: "en" });

    await wrapper.get("input[lang=ar]").setValue("فنان جديد");
    await wrapper.get("input[lang=en]").setValue("New Artist");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.createArtist).toHaveBeenCalledWith({ name: { ar: "فنان جديد", en: "New Artist" }, legacy_code: undefined, living_status: "unknown" });
    expect(wrapper.emitted("created")?.[0]).toEqual([77]);
  });

  it("shows the API's field error next to the field", async () => {
    api.createArtist.mockImplementation(() => Promise.reject(new ApiError("validation", "bad", { status: 422, fieldErrors: { legacy_code: ["The legacy code has already been taken."] } })));
    const wrapper = mountWithPlugins(CreateArtistModal, { locale: "en" });

    await wrapper.get("input[lang=en]").setValue("X");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(wrapper.text()).toContain("The legacy code has already been taken.");
    expect(wrapper.emitted("created")).toBeUndefined();
  });
});
