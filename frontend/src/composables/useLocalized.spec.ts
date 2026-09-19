import { describe, expect, it } from "vitest";
import { defineComponent, h } from "vue";

import {
  pickLocalized,
  useLocalized,
  type LocalizedValue,
} from "@/composables/useLocalized";
import { mountWithPlugins } from "@/test/utils";

const bilingual: LocalizedValue = { ar: "مرحبا", en: "Hello" };

describe("pickLocalized", () => {
  it("returns the current-locale value without fallback", () => {
    expect(pickLocalized(bilingual, "ar")).toEqual({
      text: "مرحبا",
      lang: "ar",
      dir: "rtl",
      isFallback: false,
    });
    expect(pickLocalized(bilingual, "en")).toEqual({
      text: "Hello",
      lang: "en",
      dir: "ltr",
      isFallback: false,
    });
  });

  it("falls back to the other language and flags it", () => {
    const onlyEn: LocalizedValue = { ar: null, en: "Hello" };
    expect(pickLocalized(onlyEn, "ar")).toEqual({
      text: "Hello",
      lang: "en",
      dir: "ltr",
      isFallback: true,
    });
  });

  it("treats blank strings as missing", () => {
    expect(pickLocalized({ ar: "  ", en: "Hello" }, "ar")?.isFallback).toBe(
      true,
    );
  });

  it("returns null when both languages are empty", () => {
    expect(pickLocalized({ ar: null, en: "" }, "ar")).toBeNull();
    expect(pickLocalized(null, "en")).toBeNull();
  });
});

describe("useLocalized", () => {
  it("picks reactively from the current i18n locale", () => {
    const probe = defineComponent({
      setup() {
        const { pick } = useLocalized();
        return () => h("output", JSON.stringify(pick(bilingual)));
      },
    });

    const wrapper = mountWithPlugins(probe, { locale: "ar" });
    expect(wrapper.text()).toContain('"isFallback":false');
    expect(wrapper.text()).toContain("مرحبا");
  });
});
