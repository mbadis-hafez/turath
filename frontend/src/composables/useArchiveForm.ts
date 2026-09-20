import { reactive, ref } from "vue";

import type { ArchiveEdit, ArchiveItemType, RightsStatus, AccessLevel } from "@/types/archive";

const blank = (v: string | null | undefined): string | null => (v && v.trim() !== "" ? v.trim() : null);

export interface ArchiveFormState {
  code: string; type: ArchiveItemType; titleAr: string; titleEn: string; date: string; placeAr: string; placeEn: string;
  descriptionAr: string; descriptionEn: string; people: string[]; keywords: string[];
  sourceName: string; holderAr: string; holderEn: string; license: string; rightsStatus: RightsStatus; verification: string;
  access: AccessLevel;
}

/** "YYYY-MM-DD" → the API's exact date; the year alone is not enough for the checklist. */
export function dateToContent(input: string): Record<string, unknown> | null {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(input)) return null;
  const year = Number.parseInt(input.slice(0, 4), 10);
  return { display: input, year_from: year, year_to: year, calendar: "gregorian", certainty: "exact" };
}

/** Editable archive fields shared by the add and edit modes. */
export function useArchiveForm() {
  const form = reactive<ArchiveFormState>({
    code: "", type: "image", titleAr: "", titleEn: "", date: "", placeAr: "", placeEn: "",
    descriptionAr: "", descriptionEn: "", people: [], keywords: [],
    sourceName: "", holderAr: "", holderEn: "", license: "", rightsStatus: "unknown", verification: "",
    access: "institution_only",
  });
  let initialDate = "";
  /** The existing date when it isn't a plain calendar date (approximate, range, year only), shown as a hint. */
  const existingDateText = ref("");

  function load(a: ArchiveEdit): void {
    form.code = a.legacy_ref ?? "";
    form.type = a.item_type;
    form.titleAr = a.title.ar ?? ""; form.titleEn = a.title.en ?? "";
    const display = a.content?.display ?? "";
    form.date = initialDate = /^\d{4}-\d{2}-\d{2}$/.test(display) ? display : "";
    existingDateText.value = form.date === "" ? (display || (a.content?.year_from ? String(a.content.year_from) : "")) : "";
    form.placeAr = a.place.ar ?? ""; form.placeEn = a.place.en ?? "";
    form.descriptionAr = a.description.ar ?? ""; form.descriptionEn = a.description.en ?? "";
    form.people = [...a.people_names]; form.keywords = [...a.keywords];
    form.sourceName = a.source_name ?? "";
    form.holderAr = a.rights_holder.ar ?? ""; form.holderEn = a.rights_holder.en ?? "";
    form.license = a.license ?? ""; form.rightsStatus = a.rights_status; form.verification = a.verification_reference ?? "";
    form.access = a.access_level;
  }

  /** On existing items the date is only sent when edited, so approximate dates survive. */
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
    if (mode === "create" || form.date !== initialDate) body.content = dateToContent(form.date);
    if (mode === "create") body.publication_status = "draft";
    return body;
  }

  /** Live checklist mirroring the server's, used before the item exists. */
  function checklist(hasFile: boolean): { key: string; met: boolean }[] {
    return [
      { key: "title_ar", met: blank(form.titleAr) !== null },
      { key: "type_and_file", met: hasFile },
      { key: "exact_date", met: dateToContent(form.date) !== null },
      { key: "rights_holder_license", met: (blank(form.holderAr) !== null || blank(form.holderEn) !== null) && blank(form.license) !== null },
      { key: "people_names", met: form.type !== "image" || form.people.length > 0 },
    ];
  }

  return { form, load, payload, checklist, existingDateText };
}
