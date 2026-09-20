import { describe, expect, it } from "vitest";

import DimensionsDisplay from "@/components/artworks/DimensionsDisplay.vue";
import { mountWithPlugins } from "@/test/utils";
import type { Dimensions } from "@/types/artwork";

function dims(patch: Partial<Dimensions> = {}): Dimensions {
  return {
    height_cm: null,
    width_cm: null,
    depth_cm: null,
    raw: null,
    ...patch,
  };
}

function textOf(dimensions: Dimensions): string {
  return mountWithPlugins(DimensionsDisplay, {
    locale: "en",
    props: { dimensions },
  }).text();
}

describe("DimensionsDisplay", () => {
  it("renders height × width", () => {
    expect(textOf(dims({ height_cm: 50, width_cm: 70 }))).toBe("50 × 70 cm");
  });

  it("renders height × width × depth", () => {
    expect(
      textOf(dims({ height_cm: 50, width_cm: 70, depth_cm: 12 })),
    ).toBe("50 × 70 × 12 cm");
  });

  it("omits missing segments and their separators", () => {
    expect(textOf(dims({ height_cm: 50, depth_cm: 12 }))).toBe("50 × 12 cm");
    expect(textOf(dims({ width_cm: 70 }))).toBe("70 cm");
  });

  it("falls back to the raw string when all structured values are null", () => {
    const wrapper = mountWithPlugins(DimensionsDisplay, {
      locale: "en",
      props: { dimensions: dims({ raw: "approx. 50 × 70" }) },
    });
    expect(wrapper.text()).toBe("approx. 50 × 70");
    expect(wrapper.find("span.italic").exists()).toBe(true);
  });

  it("renders nothing when everything is null and no raw value exists", () => {
    const wrapper = mountWithPlugins(DimensionsDisplay, {
      locale: "en",
      props: { dimensions: dims() },
    });
    expect(wrapper.text()).toBe("");
    expect(wrapper.find("span").exists()).toBe(false);
  });

  it("prefers structured values over raw", () => {
    expect(
      textOf(dims({ height_cm: 50, raw: "some raw text" })),
    ).toBe("50 cm");
  });
});
