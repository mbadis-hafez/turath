import { flushPromises } from "@vue/test-utils";
import { createMemoryHistory, createRouter } from "vue-router";
import { describe, expect, it } from "vitest";

import MethodologyPage from "@/pages/MethodologyPage.vue";
import { mountWithPlugins } from "@/test/utils";

async function mount(locale: "en" | "ar") {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/about/methodology", name: "methodology", component: MethodologyPage },
      { path: "/:locale/submit", name: "submit", component: { template: "<div />" } },
    ],
  });
  await router.push(`/${locale}/about/methodology`);
  const wrapper = mountWithPlugins(MethodologyPage, { locale, router });
  await flushPromises();
  return wrapper;
}

describe("MethodologyPage", () => {
  it("explains the four stages in order, numbered", async () => {
    const wrapper = await mount("en");
    const steps = wrapper.findAll("[data-testid=step]");

    expect(steps.map((s) => s.get("h2").text())).toEqual(["Collection", "Digitization", "Documentation", "Access"]);
    expect(steps.map((s) => s.get("span").text())).toEqual(["01", "02", "03", "04"]);
  });

  it("defines each label a visitor meets on a record, verified first", async () => {
    const wrapper = await mount("en");

    expect(wrapper.findAll("[data-testid=label-badge]").map((b) => b.text())).toEqual(["Verified", "Under verification", "Restricted access", "Year uncertain"]);
    expect(wrapper.get("[data-testid=labels]").text()).toContain("published source attached to the record");
  });

  it("uses the same wording as the badges on the artist and archive pages", async () => {
    const arabic = await mount("ar");

    expect(arabic.findAll("[data-testid=label-badge]").map((b) => b.text())).toEqual(["موثّق", "قيد التحقق", "وصول مقيّد", "سنة غير مؤكدة"]);
    expect(arabic.get("h1").text()).toBe("كيف يُبنى هذا الأرشيف");
  });

  it("states that ownership stays with the owners", async () => {
    const wrapper = await mount("en");

    expect(wrapper.get("[data-testid=rights]").text()).toContain("Ownership of the material stays with its owners");
  });

  it("sends researchers to the registration form with their role already chosen, in the current language", async () => {
    const english = await mount("en");
    expect(english.get("[data-testid=rights-researchers]").attributes("href")).toBe("/en/submit?role=researcher");

    const arabic = await mount("ar");
    expect(arabic.get("[data-testid=rights-researchers]").attributes("href")).toBe("/ar/submit?role=researcher");
  });

  it("keeps the rights policy button disabled until that page exists, and has no dead links", async () => {
    const wrapper = await mount("en");

    expect(wrapper.get("[data-testid=rights-policy]").attributes("disabled")).toBeDefined();
    expect(wrapper.find("a[href='#']").exists()).toBe(false);
  });
});
