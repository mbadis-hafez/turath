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

  it("hides the transcription input when the viewer cannot review", () => {
    const wrapper = mount([field()], false);
    expect(wrapper.find("[data-testid=form-field-transcribe-input]").exists()).toBe(false);
  });
});
