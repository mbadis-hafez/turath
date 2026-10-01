import { describe, expect, it } from "vitest";

import OcrFieldsReviewTable from "@/components/curation/OcrFieldsReviewTable.vue";
import { mountWithPlugins } from "@/test/utils";
import type { ExtractedField } from "@/types/ocr";

function field(patch: Partial<ExtractedField> = {}): ExtractedField {
  return {
    id: 1, field_key: "title_en", extracted_value: "Exhibition Opening", confidence: 90, source_page: 1,
    status: "pending", extraction_method: "ocr_derived", route: "record", target: "archive_item.title_en",
    ...patch,
  };
}

function schemaField(patch: Partial<ExtractedField> = {}): ExtractedField {
  return field({
    id: 2, field_key: "medium", document_type: "artwork_condition_report", label: { ar: "الخامة", en: "Medium" },
    route: "entity", target: "artwork.medium", extracted_value: "زيت على قماش", confidence: 80, ...patch,
  });
}

function mount(fields: ExtractedField[], canReview = true, locale: "en" | "ar" = "en") {
  return mountWithPlugins(OcrFieldsReviewTable, { locale, props: { fields, canReview, archiveItemId: 5 } });
}

describe("OcrFieldsReviewTable", () => {
  it("labels a document field from its schema, in the reader's language, and says where it goes", () => {
    const row = mount([schemaField()], true, "ar").get("[data-testid=ocr-field-row]");

    expect(row.get("[data-testid=field-label]").text()).toBe("الخامة");
    expect(mount([schemaField()]).get("[data-testid=field-route]").text()).toContain("artwork record is confirmed");
    expect(mount([schemaField()]).get("[data-testid=accept-field]").text()).toBe("Verify");
    expect(mount([field()]).get("[data-testid=accept-field]").text()).toBe("Accept");
  });

  it("shows a reviewer's edit as the value, with what the machine read beside it", () => {
    const wrapper = mount([field({ status: "edited", verified_value: "Exhibition opening, 1979" })]);

    expect(wrapper.get("[data-testid=field-value]").text()).toBe("Exhibition opening, 1979");
    expect(wrapper.get("[data-testid=field-machine-reading]").text()).toContain("Exhibition Opening");
  });

  it("offers no review of contact details here, and no accept for a value still to be transcribed", () => {
    const contact = mount([schemaField({ field_key: "phone", route: "artist_contact", target: "artist_contact.phone", label: { ar: "رقم الجوال", en: "Phone" } })]);
    expect(contact.find("[data-testid=accept-field]").exists()).toBe(false);
    expect(contact.find("[data-testid=reject-field]").exists()).toBe(false);
    expect(contact.get("[data-testid=field-route]").text()).toContain("artist contact panel");

    const untranscribed = mount([schemaField({ extracted_value: null, confidence: 0, form_field_id: 4 })]);
    expect(untranscribed.find("[data-testid=field-needs-transcription]").exists()).toBe(true);
    expect(untranscribed.find("[data-testid=accept-field]").exists()).toBe(false);
    expect(untranscribed.get("[data-testid=field-provenance]").text()).toContain("from a form field");
  });

  it("numbers the items of a list field", () => {
    const item = (id: number, value: string) => schemaField({ id, field_key: "exhibitions", ordinal: id - 1, label: { ar: "المعارض", en: "Exhibitions" }, route: "entity", target: "event", extracted_value: value });
    const labels = mount([item(1, "معرض الرياض 1972"), item(2, "معرض جدة 1975")]).findAll("[data-testid=field-label]").map((l) => l.text());

    expect(labels).toEqual(["Exhibitions 1", "Exhibitions 2"]);
  });

  it("counts only the item's own fields for the bulk accept", () => {
    const wrapper = mount([field(), schemaField({ confidence: 95 })]);

    expect(wrapper.get("[data-testid=accept-high-confidence]").text()).toContain("(1)");
  });

  it("emits accept, reject and an edit with the reviewer's text", async () => {
    const wrapper = mount([field()]);

    await wrapper.get("[data-testid=accept-field]").trigger("click");
    await wrapper.get("[data-testid=reject-field]").trigger("click");
    await wrapper.get("[data-testid=edit-field]").trigger("click");
    await wrapper.get("[data-testid=edit-input]").setValue("Corrected");
    await wrapper.get("[data-testid=save-edit]").trigger("click");

    expect(wrapper.emitted("accept")?.[0]).toEqual([1]);
    expect(wrapper.emitted("reject")?.[0]).toEqual([1]);
    expect(wrapper.emitted("edit")?.[0]).toEqual([1, "Corrected"]);
  });

  it("fills the edit box with the AI correction's suggestion, without saving it", async () => {
    const wrapper = mount([schemaField({ ai_correction: {
      status: "corrected", needs_review: false, review_reasons: [], corrected_line: "الخامة: زيت على قماش", suggested_value: "زيت على القماش",
      changes: [{ original: "قماش", corrected: "القماش", type: "orthographic_normalization" }], name_candidates: [],
      provider: "fake", model: "fake-model", model_version: null, prompt_version: "p3",
    } })]);

    await wrapper.get("[data-testid=field-evidence-use-suggestion]").trigger("click");

    expect((wrapper.get("[data-testid=edit-input]").element as HTMLInputElement).value).toBe("زيت على القماش");
    expect(wrapper.emitted("edit")).toBeUndefined();
  });

  it("marks a value uncertain with an optional note, keeps it open, and leaves it out of bulk accept", async () => {
    const wrapper = mount([field({ confidence: 95 })]);

    await wrapper.get("[data-testid=uncertain-field]").trigger("click");
    await wrapper.get("[data-testid=uncertain-note]").setValue("Smudged");
    await wrapper.get("[data-testid=save-uncertain]").trigger("click");
    expect(wrapper.emitted("uncertain")?.[0]).toEqual([1, "Smudged"]);

    const uncertain = mount([field({ confidence: 95, status: "uncertain", review_note: "Smudged" })]);
    expect(uncertain.get("[data-testid=field-status]").text()).toBe("Uncertain");
    expect(uncertain.get("[data-testid=field-evidence-uncertain]").text()).toContain("Smudged");
    expect(uncertain.find("[data-testid=accept-field]").exists()).toBe(true);
    expect(uncertain.find("[data-testid=accept-high-confidence]").exists()).toBe(false);
  });

  it("lets the reviewer type a value still to be read off its form field, right in the row", async () => {
    const wrapper = mount([schemaField({ extracted_value: null, form_field_id: 9, extraction_method: "manually_transcribed" })]);

    expect(wrapper.get("[data-testid=field-confidence]").text()).toBe("Typed by a reviewer");
    await wrapper.get("[data-testid=field-transcribe-input]").setValue("ألوان مائية");
    await wrapper.get("[data-testid=field-transcribe-submit]").trigger("click");

    expect(wrapper.emitted("transcribe-form-field")?.[0]).toEqual([9, "ألوان مائية"]);
  });

  it("asks to show a value's region on its page", async () => {
    const wrapper = mount([schemaField({ source_region: {
      id: 4, page_number: 2, region_type: "printed_text", bbox: { x: 1, y: 2, width: 3, height: 4 }, confidence: 80,
      ocr_text: "الخامة: زيت على قماش", ocr_allowed: true, has_correction_mark: false, has_crop: false,
    } })]);

    await wrapper.get("[data-testid=field-evidence-show-source]").trigger("click");

    expect(wrapper.emitted("show-source")?.[0]).toEqual([{ page: 2, bbox: { x: 1, y: 2, width: 3, height: 4 }, label: "Medium" }]);
  });
});
