import { flushPromises } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import EntityPicker from "@/components/curation/EntityPicker.vue";
import { mountWithPlugins } from "@/test/utils";

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

describe("EntityPicker", () => {
  it("searches after typing and emits the chosen option", async () => {
    const search = vi.fn().mockResolvedValue([{ id: 4, label: "Dar Al-Funun" }]);
    const wrapper = mountWithPlugins(EntityPicker, { locale: "en", props: { search, modelValue: null } });

    await wrapper.get("[data-testid=picker-input]").setValue("Fun");
    await vi.advanceTimersByTimeAsync(300);
    await flushPromises();
    expect(search).toHaveBeenCalledWith("Fun");

    await wrapper.get("[data-testid=picker-options] button").trigger("click");
    expect(wrapper.emitted("update:modelValue")![0]).toEqual([{ id: 4, label: "Dar Al-Funun" }]);
  });

  it("shows the selection and clears it", async () => {
    const wrapper = mountWithPlugins(EntityPicker, { locale: "en", props: { search: vi.fn(), modelValue: { id: 4, label: "Dar" } } });

    expect(wrapper.get("[data-testid=picked]").text()).toContain("Dar");
    await wrapper.get("[data-testid=picked] button").trigger("click");
    expect(wrapper.emitted("update:modelValue")![0]).toEqual([null]);
  });
});
