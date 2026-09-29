import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";

import ArchiveDeleteDialog from "@/components/curation/ArchiveDeleteDialog.vue";
import { mountWithPlugins } from "@/test/utils";

const api = vi.hoisted(() => ({ bulkArchive: vi.fn() }));
vi.mock("@/api/archive", () => api);

const item = {
  id: 42,
  legacy_ref: "ARC-42",
  title: { ar: "مادة ٤٢", en: "Item 42" },
  links: [{ role: "about", kind: "artist" as const, entity_id: 7, label: { ar: "فنان", en: "Artist" } }],
};

beforeEach(() => {
  api.bulkArchive.mockReset();
});

function mountDialog(props: Partial<{ open: boolean }> = {}) {
  return mountWithPlugins(ArchiveDeleteDialog, { locale: "en", props: { open: true, item, ...props } });
}

describe("ArchiveDeleteDialog", () => {
  it("disables the delete button until the exact legacy_ref is typed", async () => {
    const wrapper = mountDialog();
    const confirmButton = wrapper.get("[data-testid=delete-confirm-button]");
    const input = wrapper.get("[data-testid=delete-confirm-input]");

    expect(confirmButton.attributes("disabled")).toBeDefined();

    await input.setValue("ARC-4");
    expect(confirmButton.attributes("disabled")).toBeDefined();

    await input.setValue("ARC-42");
    expect(confirmButton.attributes("disabled")).toBeUndefined();
  });

  it("emits deleted after a successful delete", async () => {
    api.bulkArchive.mockResolvedValue({ data: { succeeded: [42], failed: [] } });
    const wrapper = mountDialog();

    await wrapper.get("[data-testid=delete-confirm-input]").setValue("ARC-42");
    await wrapper.get("[data-testid=delete-confirm-button]").trigger("click");
    await flushPromises();

    expect(api.bulkArchive).toHaveBeenCalledWith({ ids: [42], action: "delete" });
    expect(wrapper.emitted("deleted")).toHaveLength(1);
    expect(wrapper.emitted("cancel")).toBeUndefined();
  });

  it("shows the backend error message and does not emit deleted on failure", async () => {
    api.bulkArchive.mockResolvedValue({ data: { succeeded: [], failed: [{ id: 42, message: "Has active links" }] } });
    const wrapper = mountDialog();

    await wrapper.get("[data-testid=delete-confirm-input]").setValue("ARC-42");
    await wrapper.get("[data-testid=delete-confirm-button]").trigger("click");
    await flushPromises();

    expect(wrapper.get("[data-testid=delete-error]").text()).toBe("Has active links");
    expect(wrapper.emitted("deleted")).toBeUndefined();
  });
});
