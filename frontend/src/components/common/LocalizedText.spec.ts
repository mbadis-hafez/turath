import { describe, expect, it } from "vitest";

import LocalizedText from "@/components/common/LocalizedText.vue";
import { mountWithPlugins } from "@/test/utils";

describe("LocalizedText", () => {
  it("renders the current-locale value with lang/dir attributes", () => {
    const wrapper = mountWithPlugins(LocalizedText, {
      locale: "ar",
      props: { text: { ar: "مرحبا", en: "Hello" } },
    });

    const span = wrapper.get("span[lang]");
    expect(span.text()).toBe("مرحبا");
    expect(span.attributes("lang")).toBe("ar");
    expect(span.attributes("dir")).toBe("rtl");
    expect(wrapper.find("[role='note']").exists()).toBe(false);
  });

  it("falls back to the other language with a badge", () => {
    const wrapper = mountWithPlugins(LocalizedText, {
      locale: "ar",
      props: { text: { ar: null, en: "Hello only" } },
    });

    const span = wrapper.get("span[lang]");
    expect(span.text()).toBe("Hello only");
    expect(span.attributes("lang")).toBe("en");
    expect(span.attributes("dir")).toBe("ltr");
    expect(wrapper.get("[role='note']").text()).toBe("النص متوفر بلغة أخرى");
  });

  it("renders nothing when both languages are empty", () => {
    const wrapper = mountWithPlugins(LocalizedText, {
      locale: "en",
      props: { text: { ar: "", en: null } },
    });
    expect(wrapper.find("[lang]").exists()).toBe(false);
    expect(wrapper.text()).toBe("");
  });

  it("renders nothing for null input", () => {
    const wrapper = mountWithPlugins(LocalizedText, {
      locale: "en",
      props: { text: null },
    });
    expect(wrapper.find("[lang]").exists()).toBe(false);
  });
});
