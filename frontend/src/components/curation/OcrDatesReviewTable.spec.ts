import { describe, expect, it } from "vitest";

import OcrDatesReviewTable from "@/components/curation/OcrDatesReviewTable.vue";
import { mountWithPlugins } from "@/test/utils";
import type { DocumentSchemaField, ExtractedDate } from "@/types/ocr";

const schema: DocumentSchemaField[] = [
  { key: "opening_date", label: { ar: "تاريخ الافتتاح", en: "Opening date" }, kind: "date", route: "evidence", target: null, found: true },
];

function date(patch: Partial<ExtractedDate> = {}): ExtractedDate {
  return {
    id: 7, value: "١٢ رجب ١٤٤٥", normalized: "1445-07-12", calendar: "hijri", date_type: "event_date", field_key: "opening_date",
    source_page: 1, source_method: "ocr", region_id: 3, context: "افتتاح المعرض", confidence: 80, status: "pending",
    ...patch,
  };
}

function mount(dates: ExtractedDate[], canReview = true) {
  return mountWithPlugins(OcrDatesReviewTable, { locale: "en", props: { dates, schema, canReview } });
}

describe("OcrDatesReviewTable", () => {
  it("shows a date as written, in its own calendar, with the field it fills and where it was read", () => {
    const row = mount([date()]).get("[data-testid=ocr-date-row]");

    expect(row.get("[data-testid=date-value]").text()).toBe("١٢ رجب ١٤٤٥");
    expect(row.get("[data-testid=date-calendar]").text()).toBe("Hijri · 1445-07-12");
    expect(row.get("[data-testid=date-role]").text()).toBe("Opening date");
    expect(row.get("[data-testid=date-source]").text()).toContain("from printed text");
  });

  it("falls back to the role, and warns when only the page text had the date", () => {
    const row = mount([date({ field_key: null, date_type: "signature_date", region_id: null })]).get("[data-testid=ocr-date-row]");

    expect(row.get("[data-testid=date-role]").text()).toBe("Signature date");
    expect(row.get("[data-testid=date-source]").text()).toContain("may be handwriting");
  });

  it("verifies or rejects a pending date, and offers nothing once decided", async () => {
    const wrapper = mount([date()]);
    await wrapper.get("[data-testid=accept-date]").trigger("click");
    await wrapper.get("[data-testid=reject-date]").trigger("click");

    expect(wrapper.emitted("review")).toEqual([[7, "accept"], [7, "reject"]]);
    expect(mount([date({ status: "accepted" })]).find("[data-testid=accept-date]").exists()).toBe(false);
    expect(mount([date()], false).find("[data-testid=accept-date]").exists()).toBe(false);
  });

  it("asks to show a date where it is: its region if it has one, otherwise just its page", async () => {
    const wrapper = mount([date(), date({ id: 8, region_id: null, source_page: 2, field_key: null, date_type: "signature_date" })]);
    const [printed, pageOnly] = wrapper.findAll("[data-testid=date-show-source]");

    expect(pageOnly.text()).toBe("Show page 2");
    await printed.trigger("click");
    await pageOnly.trigger("click");

    expect(wrapper.emitted("show-source")).toEqual([[3, 1, "Opening date"], [null, 2, "Signature date"]]);
  });
});
