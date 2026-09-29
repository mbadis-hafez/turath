import { describe, expect, it } from "vitest";

import OcrTextPanel from "@/components/curation/OcrTextPanel.vue";
import { mountWithPlugins } from "@/test/utils";
import type { OcrPageText } from "@/types/ocr";

const arPage: OcrPageText = { page: 1, text: "افتتاح المعرض", confidence: 94, segments: [{ text: "افتتاح", confidence: 94 }, { text: "المعرض", confidence: 40 }] };
const enPage1: OcrPageText = { page: 1, text: "Exhibition Opening", confidence: 88, segments: [{ text: "Exhibition", confidence: 88 }, { text: "Opening", confidence: 88 }] };
const enPage2: OcrPageText = { page: 2, text: "Second page", confidence: 90, segments: [{ text: "Second", confidence: 90 }, { text: "page", confidence: 90 }] };

describe("OcrTextPanel", () => {
  it("renders nothing when there is no text in either language", () => {
    const wrapper = mountWithPlugins(OcrTextPanel, { props: { texts: { ar: [], en: [] } } });
    expect(wrapper.find("[data-testid=ocr-text-panel]").exists()).toBe(false);
  });

  it("defaults to the first available language and shows its confidence", () => {
    const wrapper = mountWithPlugins(OcrTextPanel, { props: { texts: { ar: [arPage], en: [enPage1] } } });
    const tabs = wrapper.findAll("[data-testid=ocr-lang-tab]");
    expect(tabs[0]!.attributes("aria-pressed")).toBe("true");
    expect(wrapper.get("[data-testid=ocr-text-body]").text()).toContain("افتتاح");
  });

  it("underlines low-confidence segments and highlights a search match", async () => {
    const wrapper = mountWithPlugins(OcrTextPanel, { props: { texts: { ar: [arPage], en: [] } }, locale: "en" });

    const body = wrapper.get("[data-testid=ocr-text-body]");
    const lowConfidenceSpan = body.findAll("span").find((s) => s.text().trim() === "المعرض");
    expect(lowConfidenceSpan?.classes()).toContain("decoration-danger");

    await wrapper.get("[data-testid=ocr-text-search]").setValue("افتتاح");
    const matchSpan = body.findAll("span").find((s) => s.text().trim() === "افتتاح");
    expect(matchSpan?.classes()).toContain("bg-warn-soft");
    expect(wrapper.text()).toContain("1 matches");
  });

  it("switches language and paginates within a language's pages", async () => {
    const wrapper = mountWithPlugins(OcrTextPanel, { props: { texts: { ar: [arPage], en: [enPage1, enPage2] } } });

    const tabs = wrapper.findAll("[data-testid=ocr-lang-tab]");
    await tabs[1]!.trigger("click"); // switch to English
    expect(wrapper.get("[data-testid=ocr-text-body]").text()).toContain("Exhibition");

    await wrapper.get("[data-testid=ocr-text-next]").trigger("click");
    expect(wrapper.get("[data-testid=ocr-text-body]").text()).toContain("Second page");
  });
});
