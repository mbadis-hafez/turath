import { describe, expect, it } from "vitest";

import TagsInput from "@/components/curation/TagsInput.vue";
import { mountWithPlugins } from "@/test/utils";

describe("TagsInput", () => {
  it("adds a tag on Enter, ignores duplicates and blanks, and removes with the button", async () => {
    const wrapper = mountWithPlugins(TagsInput, { locale: "en", props: { modelValue: ["a"] } });
    const input = wrapper.get("[data-testid=tag-input]");

    await input.setValue("b");
    await input.trigger("keydown", { key: "Enter" });
    expect(wrapper.emitted("update:modelValue")![0]).toEqual([["a", "b"]]);

    await input.setValue("a");
    await input.trigger("keydown", { key: "Enter" });
    await input.setValue("  ");
    await input.trigger("keydown", { key: "Enter" });
    expect(wrapper.emitted("update:modelValue")).toHaveLength(1);

    await wrapper.get("[data-testid=tag] button").trigger("click");
    expect(wrapper.emitted("update:modelValue")![1]).toEqual([["b"]]);
  });
});
