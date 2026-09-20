import { reactive } from "vue";

import type { ArchiveEdit, ArchiveItemType, RightsStatus, AccessLevel } from "@/types/archive";

const blank = (v: string | null | undefined): string | null => (v && v.trim() !== "" ? v.trim() : null);

export interface ArchiveFormState {
  code: string; type: ArchiveItemType; titleAr: string; titleEn: string; placeAr: string; placeEn: string;
  dateMode: DateMode; date: string; year: string; approxText: string; approxFrom: string; approxTo: string; certainty: "circa" | "range"; dateNote: string;
  descriptionAr: string; descriptionEn: string; people: string[]; keywords: string[];
  sourceName: string; holderAr: string; holderEn: string; license: string; rightsStatus: RightsStatus; verification: string;
  access: AccessLevel;
}

export type DateMode = "exact" | "year" | "approx";

const exactContent = (year: number, display: string): Record<string, unknown> =>
  ({ display, year_from: year, year_to: year, calendar: "gregorian", certainty: "exact" });

/** "YYYY-MM-DD" → the API's exact date, or null when it isn't a full calendar date. */
export function dateToContent(input: string): Record<string, unknown> | null {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(input)) return null;
  return exactContent(Number.parseInt(input.slice(0, 4), 10), input);
}

/** Editable archive fields shared by the add and edit modes. */
export function useArchiveForm() {
  const form = reactive<ArchiveFormState>({
    code: "", type: "image", titleAr: "", titleEn: "", placeAr: "", placeEn: "",
    dateMode: "exact", date: "", year: "", approxText: "", approxFrom: "", approxTo: "", certainty: "circa", dateNote: "",
    descriptionAr: "", descriptionEn: "", people: [], keywords: [],
    sourceName: "", holderAr: "", holderEn: "", license: "", rightsStatus: "unknown", verification: "",
    access: "institution_only",
  });
  let initialContent = "null";
  /** The existing date when it isn't a plain calendar date (approximate, range, year only), shown as a hint. */

  function load(a: ArchiveEdit): void {
    form.code = a.legacy_ref ?? "";
    form.type = a.item_type;
    form.titleAr = a.title.ar ?? ""; form.titleEn = a.title.en ?? "";
    const c = a.content;
    const display = c?.display ?? "";
    form.date = ""; form.year = ""; form.approxText = ""; form.approxFrom = ""; form.approxTo = "";
    form.certainty = c?.certainty === "range" ? "range" : "circa";
    if (/^\d{4}-\d{2}-\d{2}$/.test(display)) {
      form.dateMode = "exact"; form.date = display;
    } else if (c?.certainty === "exact" && c.year_from !== null && c.year_from === c.year_to && /^\d{4}$/.test(display)) {
      form.dateMode = "year"; form.year = display;
    } else if (c?.year_from) {
      form.dateMode = "approx"; form.approxText = display;
      form.approxFrom = String(c.year_from); form.approxTo = c.year_to ? String(c.year_to) : "";
    } else {
      form.dateMode = "exact";
    }
    form.dateNote = a.date_note ?? "";
    initialContent = JSON.stringify(buildContent());
    form.placeAr = a.place.ar ?? ""; form.placeEn = a.place.en ?? "";
    form.descriptionAr = a.description.ar ?? ""; form.descriptionEn = a.description.en ?? "";
    form.people = [...a.people_names]; form.keywords = [...a.keywords];
    form.sourceName = a.source_name ?? "";
    form.holderAr = a.rights_holder.ar ?? ""; form.holderEn = a.rights_holder.en ?? "";
    form.license = a.license ?? ""; form.rightsStatus = a.rights_status; form.verification = a.verification_reference ?? "";
    form.access = a.access_level;
  }

  // Number inputs bind numbers through v-model, so coerce before validating.
  const validYear = (v: string | number): number | null => (/^\d{4}$/.test(String(v).trim()) ? Number.parseInt(String(v), 10) : null);

  function buildContent(): Record<string, unknown> | null {
    if (form.dateMode === "exact") return dateToContent(form.date);
    if (form.dateMode === "year") {
      const y = validYear(form.year);
      return y === null ? null : exactContent(y, String(y));
    }
    const from = validYear(form.approxFrom);
    if (from === null) return null;
    const to = validYear(form.approxTo) ?? from;
    return {
      display: blank(form.approxText) ?? (from === to ? String(from) : `${from}–${to}`),
      year_from: from, year_to: Math.max(from, to), calendar: "gregorian", certainty: form.certainty,
    };
  }

  /** On existing items the date is only sent when edited, so untouched dates survive byte for byte. */
  function payload(mode: "create" | "update"): Record<string, unknown> {
    const body: Record<string, unknown> = {
      item_type: form.type,
      title: { ar: blank(form.titleAr), en: blank(form.titleEn) },
      description: { ar: blank(form.descriptionAr), en: blank(form.descriptionEn) },
      place: { ar: blank(form.placeAr), en: blank(form.placeEn) },
      people_names: form.people,
      keywords: form.keywords,
      source_name: blank(form.sourceName),
      rights_holder: { ar: blank(form.holderAr), en: blank(form.holderEn) },
      rights_status: form.rightsStatus,
      license: blank(form.license),
      verification_reference: blank(form.verification),
      access_level: form.access,
      legacy_ref: blank(form.code),
    };
    const content = buildContent();
    if (mode === "create" || JSON.stringify(content) !== initialContent) body.content = content;
    body.date_note = blank(form.dateNote);
    if (mode === "create") body.publication_status = "draft";
    return body;
  }

  function dateMet(): boolean {
    const c = buildContent();
    return c !== null && (c.certainty === "exact" || blank(form.dateNote) !== null);
  }

  /** Live checklist mirroring the server's, used before the item exists. */
  function checklist(hasFile: boolean): { key: string; met: boolean }[] {
    return [
      { key: "title_ar", met: blank(form.titleAr) !== null },
      { key: "type_and_file", met: hasFile },
      { key: "date", met: dateMet() },
      { key: "rights_holder_license", met: (blank(form.holderAr) !== null || blank(form.holderEn) !== null) && blank(form.license) !== null },
      { key: "people_names", met: form.type !== "image" || form.people.length > 0 },
    ];
  }

  return { form, load, payload, checklist };
}
