import { reactive } from "vue";

import { useFuzzyDate } from "@/composables/useFuzzyDate";
import type { ArchiveEdit, ArchiveItemType, RightsStatus, AccessLevel } from "@/types/archive";

const blank = (v: string | null | undefined): string | null => (v && v.trim() !== "" ? v.trim() : null);

export interface ArchiveFormState {
  code: string; type: ArchiveItemType; titleAr: string; titleEn: string; placeAr: string; placeEn: string;
  descriptionAr: string; descriptionEn: string; people: string[]; keywords: string[];
  sourceName: string; holderAr: string; holderEn: string; license: string; rightsStatus: RightsStatus; verification: string;
  access: AccessLevel;
}

/** Editable archive fields shared by the add and edit modes. */
export function useArchiveForm() {
  const form = reactive<ArchiveFormState>({
    code: "", type: "image", titleAr: "", titleEn: "", placeAr: "", placeEn: "",
    descriptionAr: "", descriptionEn: "", people: [], keywords: [],
    sourceName: "", holderAr: "", holderEn: "", license: "", rightsStatus: "unknown", verification: "",
    access: "institution_only",
  });
  const date = useFuzzyDate();

  function load(a: ArchiveEdit): void {
    form.code = a.legacy_ref ?? "";
    form.type = a.item_type;
    form.titleAr = a.title.ar ?? ""; form.titleEn = a.title.en ?? "";
    date.load(a.content, a.date_note);
    form.placeAr = a.place.ar ?? ""; form.placeEn = a.place.en ?? "";
    form.descriptionAr = a.description.ar ?? ""; form.descriptionEn = a.description.en ?? "";
    form.people = [...a.people_names]; form.keywords = [...a.keywords];
    form.sourceName = a.source_name ?? "";
    form.holderAr = a.rights_holder.ar ?? ""; form.holderEn = a.rights_holder.en ?? "";
    form.license = a.license ?? ""; form.rightsStatus = a.rights_status; form.verification = a.verification_reference ?? "";
    form.access = a.access_level;
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
    if (mode === "create" || date.changed()) body.content = date.build();
    body.date_note = date.note();
    if (mode === "create") body.publication_status = "draft";
    return body;
  }

  /** Live checklist mirroring the server's, used before the item exists. */
  function checklist(hasFile: boolean): { key: string; met: boolean }[] {
    return [
      { key: "title_ar", met: blank(form.titleAr) !== null },
      { key: "type_and_file", met: hasFile },
      { key: "date", met: date.met() },
      { key: "rights_holder_license", met: (blank(form.holderAr) !== null || blank(form.holderEn) !== null) && blank(form.license) !== null },
      { key: "people_names", met: form.type !== "image" || form.people.length > 0 },
    ];
  }

  return { form, date: date.state, load, payload, checklist };
}
