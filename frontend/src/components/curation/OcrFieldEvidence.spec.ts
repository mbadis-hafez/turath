import { describe, expect, it } from "vitest";

import OcrFieldEvidence from "@/components/curation/OcrFieldEvidence.vue";
import { mountWithPlugins } from "@/test/utils";
import type { ExtractedField, OcrFieldCorrection, OcrSourceRegion } from "@/types/ocr";

const region: OcrSourceRegion = {
  id: 4, page_number: 1, region_type: "printed_text", bbox: { x: 1, y: 2, width: 3, height: 4 }, confidence: 41,
  ocr_text: "مكان الميلاد: مدينه الرياض", ocr_allowed: true, has_correction_mark: true, has_crop: true,
};
const correction: OcrFieldCorrection = {
  status: "needs_review", needs_review: true, review_reasons: ["low_model_confidence"], corrected_line: "مكان الميلاد: مدينة الرياض",
  suggested_value: "مدينة الرياض", changes: [{ original: "مدينه", corrected: "مدينة", type: "orthographic_normalization" }],
  name_candidates: [{ ocr_text: "الرياض", candidate: "الرياض", kind: "place" }],
  provider: "fake", model: "fake-model", model_version: "2026-01", prompt_version: "p3",
};

function field(patch: Partial<ExtractedField> = {}): ExtractedField {
  return {
    id: 1, field_key: "birth_place", extracted_value: "مدينه الرياض", confidence: 41, source_page: 1, status: "pending",
    extraction_method: "ocr_derived", route: "entity", original_ocr_text: "مكان الميلاد: مدينه الرياض", source_region: region, ai_correction: correction,
    ...patch,
  };
}

const mount = (f: ExtractedField, canReview = true) =>
  mountWithPlugins(OcrFieldEvidence, { locale: "en", props: { field: f, label: "Birth place", archiveItemId: 5, canReview } });

describe("OcrFieldEvidence", () => {
  it("lays out the layers: where it is, what OCR read, what the AI would change and with which model", () => {
    const wrapper = mount(field());

    expect(wrapper.get("[data-testid=field-evidence-crop]").attributes("src")).toContain("/archive-items/5/file/ocr/regions/4/crop");
    expect(wrapper.get("[data-testid=field-evidence-ocr]").text()).toContain("مكان الميلاد: مدينه الرياض");
    expect(wrapper.get("[data-testid=field-evidence-ai-suggestion]").text()).toBe("مدينة الرياض");
    expect(wrapper.get("[data-testid=field-evidence-ai-change]").text()).toContain("(spelling)");
    expect(wrapper.get("[data-testid=field-evidence-ai-name]").text()).toContain("not as a correction");
    expect(wrapper.get("[data-testid=field-evidence-ai-model]").text()).toBe("fake · fake-model · 2026-01 · prompt p3");
  });

  it("lists what makes the reading doubtful", () => {
    const doubts = mount(field()).get("[data-testid=field-evidence-doubts]").text();

    expect(doubts).toContain("Low OCR confidence (41%)");
    expect(doubts).toContain("may be crossed out");
    expect(doubts).toContain("flagged this line for a person");
  });

  it("warns when accepting would replace a different value on the record", () => {
    expect(mount(field({ route: "record", ai_correction: null, current_record_value: "Jeddah" })).get("[data-testid=field-evidence-record]").text())
      .toContain("On the record now: “Jeddah”. Accepting replaces it.");
    expect(mount(field({ route: "record", ai_correction: null, current_record_value: "مدينه الرياض" })).get("[data-testid=field-evidence-record]").text())
      .toBe("Same as on the record.");
  });

  it("offers the suggestion only to a reviewer, for an undecided value", () => {
    expect(mount(field(), false).find("[data-testid=field-evidence-use-suggestion]").exists()).toBe(false);
    expect(mount(field({ status: "accepted" })).find("[data-testid=field-evidence-use-suggestion]").exists()).toBe(false);
  });

  it("emits the page and box to show, and the suggestion to edit with", async () => {
    const wrapper = mount(field());

    await wrapper.get("[data-testid=field-evidence-show-source]").trigger("click");
    await wrapper.get("[data-testid=field-evidence-use-suggestion]").trigger("click");

    expect(wrapper.emitted("show-source")?.[0]).toEqual([{ page: 1, bbox: region.bbox, label: "Birth place" }]);
    expect(wrapper.emitted("use-suggestion")?.[0]).toEqual(["مدينة الرياض"]);
  });

  it("says a hand-typed value was never machine-read, and shows no confidence doubt for it", () => {
    const wrapper = mount(field({ extracted_value: null, verified_value: "جدة", original_ocr_text: null, extraction_method: "manually_transcribed", source_region: null, ai_correction: null, confidence: 0 }));

    expect(wrapper.get("[data-testid=field-evidence-ocr]").text()).toContain("typed from the scan");
    expect(wrapper.find("[data-testid=field-evidence-doubts]").exists()).toBe(false);
  });
});
