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

export interface ExtractedField {
  id: number;
  field_key: string;
  extracted_value: string | null;
  confidence: number;
  source_page: number | null;
  status: ExtractedFieldStatus;
}

export interface FileOcrBundle {
  status: FileOcrStatus | null;
  progress_pct: number | null;
  language_confidence: { ar?: number; en?: number } | null;
  failure_reason: string | null;
  texts: { ar: OcrPageText[]; en: OcrPageText[] };
  fields: ExtractedField[];
}
