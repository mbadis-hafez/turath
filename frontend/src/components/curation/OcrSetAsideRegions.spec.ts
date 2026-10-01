import { describe, expect, it } from "vitest";

import OcrSetAsideRegions from "@/components/curation/OcrSetAsideRegions.vue";
import { mountWithPlugins } from "@/test/utils";
import type { DocumentSchemaField, ExtractedField, OcrSetAsideRegion } from "@/types/ocr";

function region(patch: Partial<OcrSetAsideRegion> = {}): OcrSetAsideRegion {
  return {
    id: 7, page_number: 2, region_type: "printed_text", bbox: { x: 10, y: 20, width: 300, height: 30 }, confidence: 77,
    ocr_text: "افتتاح المعرض ١٤ مارس", ocr_allowed: true, has_correction_mark: true, has_crop: true,
    review_reason: "possible_correction", correction_marks: [], dismissed: false, transcribed_field_ids: [],
    ...patch,
  };
}

const schema: DocumentSchemaField[] = [
  { key: "venue", label: { ar: "مكان العرض", en: "Venue" }, kind: "text", route: "entity", target: "event.venue", found: false },
  { key: "opening_date", label: { ar: "تاريخ الافتتاح", en: "Opening date" }, kind: "date", route: "entity", target: "event.start", found: false },
  { key: "phone", label: { ar: "الجوال", en: "Phone" }, kind: "text", route: "artist_contact", target: "artist_contact.phone", found: false },
];

const mount = (regions: OcrSetAsideRegion[], fields: ExtractedField[] = [], canReview = true) =>
  mountWithPlugins(OcrSetAsideRegions, { locale: "en", props: { archiveItemId: 5, regions, schema, fields, canReview } });

describe("OcrSetAsideRegions", () => {
  it("shows the crop and the untrusted reading of a possibly crossed-out line, choosing nothing", () => {
    const row = mount([region()]).get("[data-testid=ocr-set-aside-region]");

    expect(row.get("[data-testid=ocr-set-aside-correction]").text()).toContain("Nothing is chosen for you");
    expect(row.get("[data-testid=ocr-set-aside-crop]").attributes("src")).toContain("/archive-items/5/file/ocr/regions/7/crop");
    expect(row.get("[data-testid=ocr-set-aside-reading]").text()).toContain("not trusted");
    expect((row.get("[data-testid=ocr-set-aside-value]").element as HTMLInputElement).value).toBe("");
  });

  it("offers the document's fields and the item's own, but not contacts or dates", () => {
    const options = mount([region()]).findAll("[data-testid=ocr-set-aside-field] option").map((o) => o.text());

    expect(options).toContain("Venue");
    expect(options).toContain("Title (English)");
    expect(options).toContain("Place (Arabic)");
    expect(options).not.toContain("Phone");
    expect(options).not.toContain("Opening date");
  });

  it("emits the chosen field and the typed value, only when both are given", async () => {
    const wrapper = mount([region()]);
    const submit = wrapper.get("[data-testid=ocr-set-aside-submit]");
    expect(submit.attributes("disabled")).toBeDefined();

    await wrapper.get("[data-testid=ocr-set-aside-field]").setValue("venue");
    await wrapper.get("[data-testid=ocr-set-aside-value]").setValue("قاعة الفنون");
    await submit.trigger("click");

    expect(wrapper.emitted("transcribe")?.[0]).toEqual([7, "venue", "قاعة الفنون"]);
  });

  it("shows what was already typed from a line", () => {
    const typed: ExtractedField = {
      id: 31, field_key: "venue", extracted_value: null, verified_value: "قاعة الفنون", confidence: 0, source_page: 2, status: "edited", extraction_method: "manually_transcribed",
    };
    expect(mount([region({ transcribed_field_ids: [31] })], [typed]).get("[data-testid=ocr-set-aside-typed]").text()).toBe("Typed into Venue: قاعة الفنون");
  });

  it("sets a line aside as nothing to take, and brings it back", async () => {
    const wrapper = mount([region(), region({ id: 8, dismissed: true, page_number: 3 })]);

    expect(wrapper.findAll("[data-testid=ocr-set-aside-region]")).toHaveLength(1);
    await wrapper.get("[data-testid=ocr-set-aside-dismiss]").trigger("click");
    await wrapper.get("[data-testid=ocr-set-aside-restore]").trigger("click");

    expect(wrapper.emitted("dismiss")).toEqual([[7, true], [8, false]]);
  });

  it("asks to show the line on its page, and offers no actions without review rights", async () => {
    const wrapper = mount([region()], [], false);

    await wrapper.get("[data-testid=ocr-set-aside-show]").trigger("click");

    expect(wrapper.emitted("show-source")?.[0]).toEqual([{ page: 2, bbox: region().bbox, label: "Printed text" }]);
    expect(wrapper.find("[data-testid=ocr-set-aside-submit]").exists()).toBe(false);
  });
});
