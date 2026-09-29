import type { FileOcrStatus } from "@/types/archive";

export interface OcrTextSegment {
  text: string;
  confidence: number;
}

export interface OcrPageText {
  page: number;
  text: string;
  confidence: number;
  segments: OcrTextSegment[];
}

export type ExtractedFieldStatus = "pending" | "accepted" | "rejected" | "edited";

/** How a field's value came to exist — never interchangeable. Mirrors App\Enums\ExtractionMethod. */
export type ExtractionMethod = "ocr_derived" | "ai_corrected" | "ai_inferred" | "manually_transcribed" | "human_verified";

export interface ExtractedField {
  id: number;
  field_key: string;
  extracted_value: string | null;
  confidence: number;
  source_page: number | null;
  status: ExtractedFieldStatus;
  extraction_method: ExtractionMethod;
}

/** Mirrors App\Enums\OcrRegionType — what a detected region on a page actually is, before its text is trusted. */
export type OcrRegionType = "printed_text" | "handwriting" | "logo" | "photograph" | "signature" | "form_label" | "form_value" | "footer" | "noise" | "unknown";

export interface OcrRegion {
  id: number;
  page_number: number;
  region_type: OcrRegionType;
  language: "ar" | "en" | null;
  bbox: { x: number; y: number; width: number; height: number };
  confidence: number | null;
  ocr_allowed: boolean;
  ai_correction_allowed: boolean;
  requires_human_review: boolean;
  review_reason: string | null;
  has_crop: boolean;
}

export interface OcrFormField {
  id: number;
  field_label: string;
  value_region_id: number | null;
  value_type: OcrRegionType | null;
  machine_value: string | null;
  manual_value: string | null;
  requires_manual_transcription: boolean;
  transcribed_by_user_id: number | null;
  transcribed_at: string | null;
}

export type DateCalendar = "hijri" | "gregorian" | "unknown";
export type ExtractedDateType = "document_issue_date" | "signature_date" | "other";

export interface ExtractedDate {
  id: number;
  value: string;
  calendar: DateCalendar;
  date_type: ExtractedDateType;
  source_page: number | null;
  source_method: "ocr" | "manual";
}

export interface FileOcrBundle {
  status: FileOcrStatus | null;
  progress_pct: number | null;
  language_confidence: { ar?: number; en?: number } | null;
  failure_reason: string | null;
  document_type: string | null;
  texts: { ar: OcrPageText[]; en: OcrPageText[] };
  fields: ExtractedField[];
  regions: OcrRegion[];
  form_fields: OcrFormField[];
  dates: ExtractedDate[];
}
