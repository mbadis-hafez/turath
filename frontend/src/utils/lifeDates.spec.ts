import { describe, expect, it } from "vitest";

import { formatLifeDates } from "@/utils/lifeDates";
import type { PartialDate } from "@/types/artist";

function date(patch: Partial<PartialDate>): PartialDate {
  return {
    display: null,
    year_from: null,
    year_to: null,
    calendar: "gregorian",
    certainty: "exact",
    ...patch,
  };
}

describe("formatLifeDates", () => {
  it("renders an exact year in both locales with Western digits", () => {
    const input = date({ year_from: 1939 });
    expect(formatLifeDates(input, "ar")).toEqual({
      text: "1939",
      asRecorded: null,
    });
    expect(formatLifeDates(input, "en")).toEqual({
      text: "1939",
      asRecorded: null,
    });
    expect(formatLifeDates(input, "ar")?.text).not.toContain("١٩٣٩");
  });

  it("prefixes circa dates with the locale label", () => {
    expect(formatLifeDates(date({ year_from: 1200, certainty: "circa" }), "ar"))
      .toEqual({ text: "نحو 1200", asRecorded: null });
    expect(formatLifeDates(date({ year_from: 1200, certainty: "circa" }), "en"))
      .toEqual({ text: "c. 1200", asRecorded: null });
  });

  it("renders year ranges with an en dash", () => {
    const input = date({ year_from: 1900, year_to: 1910, certainty: "range" });
    expect(formatLifeDates(input, "ar")?.text).toBe("1900–1910");
    expect(formatLifeDates(input, "en")?.text).toBe("1900–1910");
  });

  it("suffixes hijri dates with the locale label", () => {
    const input = date({ year_from: 1340, calendar: "hijri" });
    expect(formatLifeDates(input, "ar")).toEqual({
      text: "1340 هـ",
      asRecorded: null,
    });
    expect(formatLifeDates(input, "en")).toEqual({
      text: "1340 AH",
      asRecorded: null,
    });
  });

  it("combines circa and hijri", () => {
    const input = date({
      year_from: 1340,
      calendar: "hijri",
      certainty: "circa",
    });
    expect(formatLifeDates(input, "ar")?.text).toBe("نحو 1340 هـ");
    expect(formatLifeDates(input, "en")?.text).toBe("c. 1340 AH");
  });

  it("falls back to the display text when no structured years exist", () => {
    const input = date({ display: "أوائل القرن العشرين" });
    expect(formatLifeDates(input, "ar")).toEqual({
      text: "أوائل القرن العشرين",
      asRecorded: null,
    });
    expect(formatLifeDates(input, "en")).toEqual({
      text: "أوائل القرن العشرين",
      asRecorded: null,
    });
  });

  it("exposes the display text as asRecorded when it differs from the render", () => {
    const input = date({ year_from: 1939, display: "1939م تقريبًا" });
    expect(formatLifeDates(input, "ar")).toEqual({
      text: "1939",
      asRecorded: "1939م تقريبًا",
    });
  });

  it("omits asRecorded when the display matches the render", () => {
    const input = date({ year_from: 1939, display: "1939" });
    expect(formatLifeDates(input, "en")).toEqual({
      text: "1939",
      asRecorded: null,
    });
  });

  it("returns null when nothing is known", () => {
    expect(formatLifeDates(date({}), "ar")).toBeNull();
    expect(formatLifeDates(date({ certainty: "unknown" }), "en")).toBeNull();
    expect(formatLifeDates(null, "ar")).toBeNull();
    expect(formatLifeDates(undefined, "en")).toBeNull();
  });

  it("renders a single year when a range has identical bounds", () => {
    const input = date({ year_from: 1900, year_to: 1900, certainty: "range" });
    expect(formatLifeDates(input, "en")?.text).toBe("1900");
  });
});
