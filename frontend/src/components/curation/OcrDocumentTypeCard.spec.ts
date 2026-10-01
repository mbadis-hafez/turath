import { describe, expect, it } from "vitest";

import OcrDocumentTypeCard from "@/components/curation/OcrDocumentTypeCard.vue";
import { mountWithPlugins } from "@/test/utils";
import type { DocumentSchemaField } from "@/types/ocr";

const schema: DocumentSchemaField[] = [
  { key: "exhibition", label: { ar: "المعرض", en: "Exhibition" }, kind: "link", route: "entity", target: "event", found: false },
  { key: "exhibition_title", label: { ar: "عنوان المعرض", en: "Exhibition title" }, kind: "text", route: "entity", target: "event.title", found: true },
  { key: "venue", label: { ar: "المكان", en: "Venue" }, kind: "text", route: "entity", target: "event.venue_name", found: false },
  { key: "opening_date", label: { ar: "تاريخ الافتتاح", en: "Opening date" }, kind: "date", route: "evidence", target: null, found: true },
];

function mount(props: Partial<{ source: "reviewer" | "classifier" | null; canReview: boolean }> = {}) {
  return mountWithPlugins(OcrDocumentTypeCard, {
    locale: "en",
    props: { documentType: "exhibition_document", source: "classifier", schema, canReview: true, ...props },
  });
}

describe("OcrDocumentTypeCard", () => {
  it("names the type, who chose it, and the expected fields that weren't found — not the linked record", () => {
    const wrapper = mount();

    expect(wrapper.get("[data-testid=ocr-document-type-label]").text()).toBe("Exhibition document");
    expect(wrapper.get("[data-testid=ocr-document-type-source]").text()).toBe("Detected automatically");
    expect(wrapper.get("[data-testid=ocr-document-schema]").text()).toContain("2 of 3 expected fields found");
    expect(wrapper.get("[data-testid=ocr-document-schema-missing]").text()).toBe("Not found: Venue");
  });

  it("lets a reviewer choose another type, and hand a chosen one back to automatic detection", async () => {
    const wrapper = mount({ source: "reviewer" });

    await wrapper.get("[data-testid=ocr-document-type-select]").setValue("artist_biography");
    await wrapper.get("[data-testid=ocr-document-type-reset]").trigger("click");

    expect(wrapper.emitted("set-type")).toEqual([["artist_biography"], [null]]);
  });

  it("offers no reset for a detected type, and no choice to someone who can't review", () => {
    expect(mount().find("[data-testid=ocr-document-type-reset]").exists()).toBe(false);
    expect(mount({ canReview: false }).find("[data-testid=ocr-document-type-select]").exists()).toBe(false);
  });
});
