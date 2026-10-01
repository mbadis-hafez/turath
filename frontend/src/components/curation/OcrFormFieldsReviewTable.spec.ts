import { describe, expect, it } from "vitest";

import OcrFormFieldsReviewTable from "@/components/curation/OcrFormFieldsReviewTable.vue";
import { mountWithPlugins } from "@/test/utils";
import type { OcrFormField } from "@/types/ocr";

function field(patch: Partial<OcrFormField> = {}): OcrFormField {
  return {
    id: 1, field_label: "اسم الفنان/ة", value_region_id: 7, value_type: "handwriting",
    machine_value: null, manual_value: null, requires_manual_transcription: true,
    transcribed_by_user_id: null, transcribed_at: null,
    ...patch,
  };
}

function mount(fields: OcrFormField[], canReview = true) {
  return mountWithPlugins(OcrFormFieldsReviewTable, { locale: "en", props: { archiveItemId: 5, formFields: fields, canReview } });
}

describe("OcrFormFieldsReviewTable", () => {
  it("renders nothing when there are no form fields", () => {
    const wrapper = mount([]);
    expect(wrapper.find("[data-testid=ocr-form-fields-table]").exists()).toBe(false);
  });

  it("shows a crop image and a manual transcription input for a handwritten value", () => {
    const wrapper = mount([field()]);
    expect(wrapper.find("[data-testid=form-field-crop]").attributes("src")).toContain("/file/ocr/regions/7/crop");
    expect(wrapper.find("[data-testid=form-field-transcribe-input]").exists()).toBe(true);
    expect(wrapper.find("[data-testid=form-field-needs-transcription-badge]").exists()).toBe(true);
  });

  it("shows a manual-entry prompt with no crop when no value region was detected at all", () => {
    const wrapper = mount([field({ value_region_id: null, value_type: null })]);
    expect(wrapper.find("[data-testid=form-field-crop]").exists()).toBe(false);
    expect(wrapper.find("[data-testid=form-field-no-source]").exists()).toBe(true);
    expect(wrapper.find("[data-testid=form-field-transcribe-input]").exists()).toBe(true);
  });

  it("emits transcribe with the typed value and does not emit on an empty submission", async () => {
    const wrapper = mount([field()]);
    await wrapper.get("[data-testid=form-field-transcribe-submit]").trigger("click");
    expect(wrapper.emitted("transcribe")).toBeUndefined();

    await wrapper.get("[data-testid=form-field-transcribe-input]").setValue("محمد عبدالله السليم");
    await wrapper.get("[data-testid=form-field-transcribe-submit]").trigger("click");
    expect(wrapper.emitted("transcribe")?.[0]).toEqual([1, "محمد عبدالله السليم"]);
  });

  it("shows a resolved, already-transcribed value instead of the input", () => {
    const wrapper = mount([field({ manual_value: "0555555555", transcribed_at: "2026-09-30T10:00:00Z", transcribed_by_user_id: 2 })]);
    expect(wrapper.find("[data-testid=form-field-manual-value]").text()).toBe("0555555555");
    expect(wrapper.find("[data-testid=form-field-transcribed-badge]").exists()).toBe(true);
    expect(wrapper.find("[data-testid=form-field-transcribe-input]").exists()).toBe(false);
  });

  it("shows a printed machine value directly, with no transcription controls", () => {
    const wrapper = mount([field({ value_type: "printed_text", machine_value: "AR036", requires_manual_transcription: false })]);
    expect(wrapper.find("[data-testid=form-field-machine-value]").text()).toBe("AR036");
    expect(wrapper.find("[data-testid=form-field-transcribe-input]").exists()).toBe(false);
  });

  it("warns about a possible correction and requires typing the intended value, showing the raw reading without choosing from it", () => {
    const wrapper = mount([field({
      value_type: "printed_text", machine_value: "old@example.com new@example.com",
      requires_manual_transcription: true, has_correction_mark: true,
    })]);

    expect(wrapper.find("[data-testid=form-field-correction-badge]").exists()).toBe(true);
    expect(wrapper.find("[data-testid=form-field-correction-note]").exists()).toBe(true);
    expect(wrapper.get("[data-testid=form-field-raw-reading]").text()).toContain("old@example.com new@example.com");
    // The reading is shown as evidence, never pre-filled as the answer.
    expect((wrapper.get("[data-testid=form-field-transcribe-input]").element as HTMLInputElement).value).toBe("");
    expect(wrapper.find("[data-testid=form-field-machine-value]").exists()).toBe(false);
  });

  it("keeps the correction badge on a value the reviewer has since transcribed", () => {
    const wrapper = mount([field({ has_correction_mark: true, manual_value: "new@example.com", transcribed_at: "2026-09-30T10:00:00Z", transcribed_by_user_id: 2 })]);

    expect(wrapper.find("[data-testid=form-field-correction-badge]").exists()).toBe(true);
    expect(wrapper.find("[data-testid=form-field-correction-note]").exists()).toBe(false);
    expect(wrapper.get("[data-testid=form-field-manual-value]").text()).toBe("new@example.com");
  });

  describe("handwriting suggestions", () => {
    const suggestion = {
      id: 31, status: "suggested" as const, text: "أحمد الملو", confidence: 0.726, provider: "kraken", model: "muharaf_rec_best.mlmodel",
      model_version: "sha256:726052869fa5aaf2", decision: null, final_text: null, decided_by_user_id: null, decided_at: null,
    };

    function mountWith(patch: Partial<OcrFormField>, handwritingAvailable = true, canReview = true) {
      return mountWithPlugins(OcrFormFieldsReviewTable, { locale: "en", props: { archiveItemId: 5, formFields: [field(patch)], canReview, handwritingAvailable } });
    }

    it("shows a pending suggestion as evidence with its provider and a warning, without filling the answer", () => {
      const wrapper = mountWith({ suggestion, can_request_suggestion: true });

      expect(wrapper.get("[data-testid=form-field-suggestion-text]").text()).toBe("أحمد الملو");
      expect(wrapper.get("[data-testid=form-field-suggestion]").text()).toContain("kraken · confidence 73%");
      expect(wrapper.get("[data-testid=form-field-suggestion]").text()).toContain("often wrong");
      expect((wrapper.get("[data-testid=form-field-transcribe-input]").element as HTMLInputElement).value).toBe("");
    });

    it("pre-fills the input from the suggestion only when asked, and still needs an explicit save", async () => {
      const wrapper = mountWith({ suggestion });

      await wrapper.get("[data-testid=form-field-use-suggestion]").trigger("click");

      expect((wrapper.get("[data-testid=form-field-transcribe-input]").element as HTMLInputElement).value).toBe("أحمد الملو");
      expect(wrapper.emitted("transcribe")).toBeUndefined();

      await wrapper.get("[data-testid=form-field-transcribe-input]").setValue("أحمد المغلوث");
      await wrapper.get("[data-testid=form-field-transcribe-submit]").trigger("click");
      expect(wrapper.emitted("transcribe")?.[0]).toEqual([1, "أحمد المغلوث"]);
    });

    it("rejects a suggestion", async () => {
      const wrapper = mountWith({ suggestion });

      await wrapper.get("[data-testid=form-field-reject-suggestion]").trigger("click");

      expect(wrapper.emitted("rejectSuggestion")?.[0]).toEqual([31]);
    });

    it("offers to ask for a suggestion only when a provider is available and the crop is eligible", async () => {
      const available = mountWith({ can_request_suggestion: true });
      await available.get("[data-testid=form-field-request-suggestion]").trigger("click");
      expect(available.emitted("requestSuggestion")?.[0]).toEqual([7]);

      expect(mountWith({ can_request_suggestion: true }, false).find("[data-testid=form-field-request-suggestion]").exists()).toBe(false);
      expect(mountWith({ can_request_suggestion: false }).find("[data-testid=form-field-request-suggestion]").exists()).toBe(false);
      expect(mountWith({ can_request_suggestion: true }, true, false).find("[data-testid=form-field-request-suggestion]").exists()).toBe(false);
    });

    it("says so when the reader read nothing, or the suggestion was rejected", () => {
      expect(mountWith({ suggestion: { ...suggestion, status: "empty", text: null } }).find("[data-testid=form-field-suggestion-empty]").exists()).toBe(true);
      expect(mountWith({ suggestion: { ...suggestion, decision: "rejected" } }).find("[data-testid=form-field-suggestion-rejected]").exists()).toBe(true);
    });
  });

  it("hides the transcription input when the viewer cannot review", () => {
    const wrapper = mount([field()], false);
    expect(wrapper.find("[data-testid=form-field-transcribe-input]").exists()).toBe(false);
  });

  it("asks to show a form field's value region on its page", async () => {
    const wrapper = mount([field()]);

    await wrapper.get("[data-testid=form-field-show-source]").trigger("click");

    expect(wrapper.emitted("show-source")?.[0]).toEqual([field().value_region_id, field().field_label]);
    expect(mount([field({ value_region_id: null })]).find("[data-testid=form-field-show-source]").exists()).toBe(false);
  });
});
