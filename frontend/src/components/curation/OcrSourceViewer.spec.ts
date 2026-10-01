import type { VueWrapper } from "@vue/test-utils";
import { describe, expect, it } from "vitest";

import OcrSourceViewer from "@/components/curation/OcrSourceViewer.vue";
import { mountWithPlugins } from "@/test/utils";
import type { OcrRegion } from "@/types/ocr";

function region(patch: Partial<OcrRegion> = {}): OcrRegion {
  return {
    id: 1, page_number: 1, region_type: "printed_text", language: "ar", bbox: { x: 200, y: 100, width: 400, height: 50 }, confidence: 80,
    ocr_allowed: true, ai_correction_allowed: true, requires_human_review: false, review_reason: null, has_correction_mark: false, correction_marks: [], has_crop: false,
    ...patch,
  };
}

const mount = (props: Record<string, unknown>) =>
  mountWithPlugins(OcrSourceViewer, { locale: "en", props: { archiveItemId: 5, pageCount: 2, regions: [], focus: null, ...props } });

/** jsdom never loads images: give the page its rendered size and fire load. */
async function loadPage(wrapper: VueWrapper, width = 2000, height = 1000): Promise<void> {
  const img = wrapper.get("[data-testid=ocr-source-page]");
  Object.defineProperty(img.element, "naturalWidth", { value: width, configurable: true });
  Object.defineProperty(img.element, "naturalHeight", { value: height, configurable: true });
  await img.trigger("load");
}

describe("OcrSourceViewer", () => {
  it("shows the focused page as OCR rendered it, with the value's region outlined in place", async () => {
    const wrapper = mount({ focus: { page: 2, bbox: { x: 500, y: 250, width: 1000, height: 100 }, label: "Venue" } });
    await loadPage(wrapper);

    expect(wrapper.get("[data-testid=ocr-source-page]").attributes("src")).toBe("/api/v1/archive-items/5/file/ocr/pages/2/image");
    expect(wrapper.get("[data-testid=ocr-source-page-label]").text()).toBe("Page 2 of 2");
    expect(wrapper.get("[data-testid=ocr-source-focus-label]").text()).toContain("Venue");
    const style = wrapper.get("[data-testid=ocr-source-focus]").attributes("style");
    expect(style).toContain("left: 25%");
    expect(style).toContain("top: 25%");
    expect(style).toContain("width: 50%");
    expect(style).toContain("height: 10%");
  });

  it("outlines the region only on its own page", async () => {
    const wrapper = mount({ focus: { page: 1, bbox: { x: 0, y: 0, width: 10, height: 10 }, label: null } });
    await loadPage(wrapper);
    expect(wrapper.find("[data-testid=ocr-source-focus]").exists()).toBe(true);

    await wrapper.get("[data-testid=ocr-source-next]").trigger("click");

    expect(wrapper.find("[data-testid=ocr-source-focus]").exists()).toBe(false);
    expect(wrapper.get("[data-testid=ocr-source-page]").attributes("src")).toContain("/pages/2/image");
  });

  it("can show every region on the page, and any possible correction marks", async () => {
    const wrapper = mount({ regions: [
      region({ id: 1 }),
      region({ id: 2, region_type: "handwriting", has_correction_mark: true, correction_marks: [{ kind: "line_strike", bbox: { x: 10, y: 10, width: 20, height: 5 } }] }),
      region({ id: 3, page_number: 2 }),
    ] });
    await loadPage(wrapper);
    expect(wrapper.findAll("[data-testid=ocr-source-region]")).toHaveLength(0);

    await wrapper.get("[data-testid=ocr-source-show-all]").setValue(true);

    expect(wrapper.findAll("[data-testid=ocr-source-region]")).toHaveLength(2);
    expect(wrapper.findAll("[data-testid=ocr-source-correction-mark]")).toHaveLength(1);
  });

  it("says so when the page image can't be loaded", async () => {
    const wrapper = mount({});
    await wrapper.get("[data-testid=ocr-source-page]").trigger("error");

    expect(wrapper.find("[data-testid=ocr-source-unavailable]").exists()).toBe(true);
  });
});
