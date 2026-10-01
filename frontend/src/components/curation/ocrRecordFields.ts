/**
 * The archive item's own fields an OCR value can fill, with the edit form's
 * labels (reused rather than duplicated). Mirrors ExtractedFieldPayloadMapper.
 */
export const RECORD_FIELD_LABEL_KEYS: Record<string, string> = {
  title_ar: "archive.edit.titleAr", title_en: "archive.edit.titleEn",
  description_ar: "archive.edit.descriptionAr", description_en: "archive.edit.descriptionEn",
  place_ar: "archive.edit.place", place_en: "archive.edit.place",
  rights_holder_ar: "archive.edit.rightsHolder", rights_holder_en: "archive.edit.rightsHolder",
  source_name: "archive.edit.sourceName", verification_reference: "archive.edit.verification",
  date_display: "archive.edit.date",
};
