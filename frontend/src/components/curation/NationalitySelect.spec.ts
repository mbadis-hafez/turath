import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";

import NationalitySelect from "@/components/curation/NationalitySelect.vue";
import { findNationality, flagEmoji, NATIONALITIES } from "@/data/nationalities";
import { i18n } from "@/i18n";

function mountSelect(ar: string | null = null, en: string | null = null) {
  return mount(NationalitySelect, {
    props: { ar, en, inputClass: "input" },
    global: { plugins: [i18n] },
  });
}

describe("findNationality", () => {
  it("matches on either language", () => {
    expect(findNationality("سعودي", null)?.code).toBe("SA");
    expect(findNationality(null, "Saudi")?.code).toBe("SA");
    expect(findNationality("سعودي", "Saudi")?.code).toBe("SA");
  });

  it("returns null for empty or unknown values", () => {
    expect(findNationality(null, null)).toBeNull();
    expect(findNationality("", " ")).toBeNull();
    expect(findNationality("حجازي", "Hijazi")).toBeNull();
  });
});

describe("NATIONALITIES", () => {
  it("has unique ISO codes and both languages filled for every entry", () => {
    const codes = NATIONALITIES.map((n) => n.code);
    expect(new Set(codes).size).toBe(codes.length);
    for (const n of NATIONALITIES) {
      expect(n.en.trim()).not.toBe("");
      expect(n.ar.trim()).not.toBe("");
    }
  });
});

describe("flagEmoji", () => {
  it("builds the regional-indicator pair for a code", () => {
    expect(flagEmoji("SA")).toBe("🇸🇦");
  });
});

describe("NationalitySelect", () => {
  it("shows a picked chip with the flag for a listed pair", () => {
    const wrapper = mountSelect("سعودي", "Saudi");
    const chip = wrapper.get("[data-testid=nationality-picked]");
    expect(chip.text()).toContain("سعودي");
    expect(chip.text()).toContain("🇸🇦");
    expect(wrapper.find("[data-testid=nationality-search]").exists()).toBe(false);
  });

  it("falls back to custom inputs for an unlisted nationality", () => {
    const wrapper = mountSelect("حجازي", "Hijazi");
    const custom = wrapper.get("[data-testid=nationality-custom]");
    const inputs = custom.findAll("input");
    expect((inputs[0].element as HTMLInputElement).value).toBe("حجازي");
    expect((inputs[1].element as HTMLInputElement).value).toBe("Hijazi");
  });

  it("filters options by search term in either language", async () => {
    const wrapper = mountSelect();
    const input = wrapper.get("[data-testid=nationality-search]");
    await input.trigger("focus");
    expect(wrapper.get("[data-testid=nationality-options]").findAll("li").length).toBeGreaterThan(100);

    await input.setValue("fren");
    const options = wrapper.get("[data-testid=nationality-options]").findAll("li");
    expect(options.some((li) => li.text().includes("French"))).toBe(true);
    expect(options.some((li) => li.text().includes("German"))).toBe(false);

    await input.setValue("سعودي");
    const arOptions = wrapper.get("[data-testid=nationality-options]").findAll("li");
    expect(arOptions.some((li) => li.text().includes("سعودي"))).toBe(true);
    expect(arOptions.some((li) => li.text().includes("French"))).toBe(false);
  });

  it("fills both languages when an option is picked", async () => {
    const wrapper = mountSelect();
    const input = wrapper.get("[data-testid=nationality-search]");
    await input.trigger("focus");
    await input.setValue("Algerian");
    const option = wrapper
      .get("[data-testid=nationality-options]")
      .findAll("button")
      .find((b) => b.text().includes("Algerian"));
    await option!.trigger("mousedown");
    expect(wrapper.emitted("update:ar")?.[0]).toEqual(["جزائري"]);
    expect(wrapper.emitted("update:en")?.[0]).toEqual(["Algerian"]);
  });

  it("enters and leaves the custom-entry mode", async () => {
    const wrapper = mountSelect();
    const input = wrapper.get("[data-testid=nationality-search]");
    await input.trigger("focus");
    await wrapper.get("[data-testid=nationality-other]").trigger("mousedown");

    const custom = wrapper.get("[data-testid=nationality-custom]");
    const inputs = custom.findAll("input");
    await inputs[0].setValue("حجازي");
    await inputs[1].setValue("Hijazi");
    expect(wrapper.emitted("update:ar")?.[0]).toEqual(["حجازي"]);
    expect(wrapper.emitted("update:en")?.[0]).toEqual(["Hijazi"]);

    await wrapper.get("[data-testid=nationality-back-to-list]").trigger("click");
    expect(wrapper.find("[data-testid=nationality-custom]").exists()).toBe(false);
    expect(wrapper.emitted("update:ar")?.at(-1)).toEqual([null]);
    expect(wrapper.emitted("update:en")?.at(-1)).toEqual([null]);
  });

  it("clears a picked value", async () => {
    const wrapper = mountSelect("سعودي", "Saudi");
    await wrapper.get("[data-testid=nationality-picked] button").trigger("click");
    expect(wrapper.emitted("update:ar")?.[0]).toEqual([null]);
    expect(wrapper.emitted("update:en")?.[0]).toEqual([null]);
    expect(wrapper.find("[data-testid=nationality-search]").exists()).toBe(true);
  });
});
