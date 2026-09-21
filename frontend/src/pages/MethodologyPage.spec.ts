import { describe, expect, it } from "vitest";

import MethodologyPage from "@/pages/MethodologyPage.vue";
import { mountWithPlugins } from "@/test/utils";

const mount = (locale: "en" | "ar") => mountWithPlugins(MethodologyPage, { locale });

describe("MethodologyPage", () => {
  it("explains the four stages in order, numbered", () => {
    const wrapper = mount("en");
    const steps = wrapper.findAll("[data-testid=step]");

    expect(steps.map((s) => s.get("h2").text())).toEqual(["Collection", "Digitization", "Documentation", "Access"]);
    expect(steps.map((s) => s.get("span").text())).toEqual(["01", "02", "03", "04"]);
  });

  it("defines each label a visitor meets on a record, verified first", () => {
    const wrapper = mount("en");

    expect(wrapper.findAll("[data-testid=label-badge]").map((b) => b.text())).toEqual(["Verified", "Under verification", "Restricted access", "Approximate year"]);
    expect(wrapper.get("[data-testid=labels]").text()).toContain("published source attached to the record");
  });

  it("uses the same wording as the badges on the artist and archive pages", () => {
    const arabic = mount("ar");

    expect(arabic.findAll("[data-testid=label-badge]").map((b) => b.text())).toEqual(["موثّق", "قيد التحقق", "وصول مقيّد", "سنة تقريبية"]);
    expect(arabic.get("h1").text()).toBe("كيف يُبنى هذا الأرشيف");
  });

  it("states that ownership stays with the owners, and does not link to pages that do not exist", () => {
    const wrapper = mount("en");

    expect(wrapper.get("[data-testid=rights]").text()).toContain("Ownership of the material stays with its owners");
    expect(wrapper.find("a[href='#']").exists()).toBe(false);
    for (const k of ["policy", "researchers"]) expect(wrapper.get(`[data-testid=rights-${k}]`).attributes("disabled")).toBeDefined();
  });
});
