import { describe, expect, it } from "vitest";

import HighlightedText from "@/components/search/HighlightedText.vue";
import { mountWithPlugins } from "@/test/utils";

describe("HighlightedText", () => {
  it("wraps every case-insensitive occurrence of the term in a mark", () => {
    const wrapper = mountWithPlugins(HighlightedText, {
      props: { text: "Ahmad met ahmad again", term: "Ahmad" },
    });
    const marks = wrapper.findAll("mark");
    expect(marks).toHaveLength(2);
    expect(marks[0].text()).toBe("Ahmad");
    expect(marks[1].text()).toBe("ahmad");
  });

  it("highlights Arabic text literally (no case to fold)", () => {
    const wrapper = mountWithPlugins(HighlightedText, {
      props: { text: "أحمد المغلوث فنان", term: "المغلوث" },
    });
    expect(wrapper.get("mark").text()).toBe("المغلوث");
  });

  it("renders the plain text unchanged when the term is empty or absent", () => {
    const empty = mountWithPlugins(HighlightedText, {
      props: { text: "Ahmad", term: "" },
    });
    expect(empty.find("mark").exists()).toBe(false);
    expect(empty.text()).toBe("Ahmad");

    const noMatch = mountWithPlugins(HighlightedText, {
      props: { text: "Ahmad", term: "xyz" },
    });
    expect(noMatch.find("mark").exists()).toBe(false);
  });
});
