import type { FileOcrStatus } from "@/types/archive";
import type { ProposalStatus } from "@/types/proposal";

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

/** "uncertain": a reviewer looked and can't confirm it from the source — never applied, still open to a decision. */
export type ExtractedFieldStatus = "pending" | "accepted" | "rejected" | "edited" | "uncertain";

/** How a field's value came to exist — never interchangeable. Mirrors App\Enums\ExtractionMethod. */
export type ExtractionMethod = "ocr_derived" | "ai_corrected" | "ai_inferred" | "manually_transcribed" | "human_verified";

/** Mirrors App\Enums\DocumentType. */
export type DocumentType = "artist_authorization" | "artwork_condition_report" | "artist_biography" | "exhibition_document" | "unknown";

/**
 * Where a verified value can go. Mirrors App\Support\Ocr\Extraction\FieldDefinition:
 * record = this archive item's own field (applied on accept); entity = a name or
 * attribute of an artist/artwork/event/institution/source (verified only, applied
 * once that record is confirmed); artist_contact = reviewed as a contact proposal,
 * never here; evidence = recorded and verified, no record field for it.
 */
export type ExtractedFieldRoute = "record" | "entity" | "artist_contact" | "evidence";

export interface ExtractedField {
  id: number;
  field_key: string;
  /** Set for a document type's schema fields; null for the archive item's own (title, date). */
  document_type?: DocumentType | null;
  /** A list field (an exhibition history) has one row per item. */
  ordinal?: number;
  label?: { ar: string; en: string } | null;
  route?: ExtractedFieldRoute | null;
  /** e.g. "artwork.medium", "artist", "artist_contact.email". */
  target?: string | null;
  /** What the machine read — never overwritten. Null when there is nothing to read yet (untranscribed handwriting). */
  extracted_value: string | null;
  /** The reviewer's edit, or the value they confirmed. */
  verified_value?: string | null;
  confidence: number;
  source_page: number | null;
  region_id?: number | null;
  form_field_id?: number | null;
  has_crop?: boolean;
  original_ocr_text?: string | null;
  rule?: string | null;
  status: ExtractedFieldStatus;
  extraction_method: ExtractionMethod;
  reviewed_at?: string | null;
  /** Why a reviewer marked it uncertain. */
  review_note?: string | null;
  /** Candidate records for a name or title this field holds; in the review bundle only. */
  match?: EntityMatch | null;
  /** The region the value was read from, and that region's own OCR reading; null for a whole-page reading. */
  source_region?: OcrSourceRegion | null;
  /** The region's AI correction, applied to just this value — a suggestion, never applied by itself. */
  ai_correction?: OcrFieldCorrection | null;
  /** For the archive item's own fields: what the record holds now, i.e. what accepting would replace. */
  current_record_value?: string | null;
}

export interface OcrSourceRegion {
  id: number;
  page_number: number;
  region_type: OcrRegionType;
  bbox: { x: number; y: number; width: number; height: number };
  confidence: number | null;
  /** The engine's reading; null for a region it wasn't allowed to read (handwriting). */
  ocr_text: string | null;
  ocr_allowed: boolean;
  has_correction_mark: boolean;
  has_crop: boolean;
}

/** Mirrors FileOcrRegionCorrection + its OcrCorrection, narrowed to one field's value. */
export interface OcrFieldCorrection {
  status: "unchanged" | "corrected" | "needs_review" | "rejected";
  needs_review: boolean;
  review_reasons: string[];
  corrected_line: string | null;
  /** The value with only the changes that fall inside it applied; null when none do. */
  suggested_value: string | null;
  changes: { original: string; corrected: string; type: string | null }[];
  /** The model's reading of a name — for matching and a person, never a spelling fix. */
  name_candidates: { ocr_text: string; candidate: string; kind: string }[];
  provider: string;
  model: string;
  model_version: string | null;
  prompt_version: string;
}

/** A line the pipeline set aside for a person that no form field covers: a possible strikethrough, loose handwriting. */
export interface OcrSetAsideRegion extends OcrSourceRegion {
  review_reason: string | null;
  correction_marks: OcrCorrectionMark[];
  dismissed: boolean;
  /** Values a reviewer already typed from this line. */
  transcribed_field_ids: number[];
}

/** The record types a name is matched against; a "place" is a spelling already used in records, not a record. */
export type EntityMatchType = "artist" | "artwork" | "event" | "holder" | "source" | "place";

export interface EntityMatchCandidate {
  /** Record id (a uuid for sources); null for a place. */
  id: number | string | null;
  /** A place's spelling. */
  key: string | null;
  /** How alike the texts are, 0..1 — text similarity, never proof of identity. */
  score: number;
  strength: "high" | "medium" | "low";
  /** The evidence, e.g. "exact_title", "same_artist". */
  basis: string[];
  label: { ar: string; en: string } | null;
  detail: string | null;
  /** The record has been deleted since the match was made. */
  missing: boolean;
}

export interface EntityMatch {
  id: number;
  entity_type: EntityMatchType;
  source_text: string;
  status: "pending" | "confirmed" | "no_match";
  requires_review: boolean;
  candidates: EntityMatchCandidate[];
  confirmed: { id: string | null; key: string | null; label: { ar: string; en: string } | null; detail: string | null; missing: boolean } | null;
  reviewed_at: string | null;
}

export type DocumentFieldKind = "text" | "list" | "date" | "link";

/** One field a document type expects, and whether anything was found for it. */
export interface DocumentSchemaField {
  key: string;
  label: { ar: string; en: string };
  kind: DocumentFieldKind;
  route: ExtractedFieldRoute;
  target: string | null;
  found: boolean;
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
  /** A possible crossed-out or struck-through value — never resolved automatically. */
  has_correction_mark?: boolean;
  correction_marks?: OcrCorrectionMark[];
  has_crop: boolean;
}

export interface OcrCorrectionMark {
  kind: "scribble" | "line_strike";
  bbox: { x: number; y: number; width: number; height: number };
}

export interface OcrFormField {
  id: number;
  field_label: string;
  value_region_id: number | null;
  value_type: OcrRegionType | null;
  machine_value: string | null;
  manual_value: string | null;
  requires_manual_transcription: boolean;
  /** Something in the value looks crossed out: the reviewer must type the intended value. */
  has_correction_mark?: boolean;
  /** A machine reading of the handwriting, if one was requested — never the field's value by itself. */
  suggestion?: OcrHandwritingSuggestion | null;
  can_request_suggestion?: boolean;
  transcribed_by_user_id: number | null;
  transcribed_at: string | null;
}

/** Mirrors OcrHandwritingSuggestion: a provider's reading of a crop, and the reviewer's decision about it. */
export interface OcrHandwritingSuggestion {
  id: number;
  status: "suggested" | "empty";
  text: string | null;
  /** The provider's own 0–1 estimate; not a reliable guide to correctness. */
  confidence: number | null;
  provider: string;
  model: string;
  model_version: string | null;
  decision: "accepted" | "edited" | "rejected" | null;
  final_text: string | null;
  decided_by_user_id: number | null;
  decided_at: string | null;
}

export interface HandwritingAvailability {
  available: boolean;
  reason: string | null;
  provider: string | null;
  external: boolean;
}

export type DateCalendar = "hijri" | "gregorian" | "unknown";
/** Mirrors App\Enums\ExtractedDateType; "other" is the unknown role. */
export type ExtractedDateType =
  | "document_issue_date" | "publication_date" | "event_date" | "artwork_date" | "birth_date" | "death_date" | "signature_date" | "other";

export interface ExtractedDate {
  id: number;
  /** As written on the page — never converted to another calendar. */
  value: string;
  /** Y[-m[-d]] in the date's own calendar, when its parts are plausible. */
  normalized?: string | null;
  calendar: DateCalendar;
  date_type: ExtractedDateType;
  /** The document field it fills, e.g. "opening_date". */
  field_key?: string | null;
  source_page: number | null;
  source_method: "ocr" | "manual";
  /** The printed region it was read from; null when only the whole-page text had it (possibly handwriting). */
  region_id?: number | null;
  context?: string | null;
  confidence?: number | null;
  status?: ExtractedFieldStatus;
  reviewed_at?: string | null;
}

/** The OCR pipeline's stages, in order. Mirrors App\Enums\OcrStage. */
export type OcrStageName = "recognize" | "extract" | "match" | "correct";

export type OcrStageStatus = "pending" | "queued" | "running" | "succeeded" | "skipped" | "failed";

/** Where one pipeline stage stands; status null means it hasn't run since stage tracking began. */
export interface OcrStageRun {
  stage: OcrStageName;
  /** Core stages decide the file's status; an optional one (AI correction) failing never fails the file. */
  core: boolean;
  status: OcrStageStatus | null;
  /** Why it was skipped or is queued again, e.g. "up_to_date", "disabled", "retrying". */
  reason: string | null;
  attempts: number;
  error: string | null;
  /** Running or queued for longer than it should be — its worker is probably gone. */
  stale: boolean;
  can_run: boolean;
  queued_at: string | null;
  started_at: string | null;
  finished_at: string | null;
  summary: Record<string, unknown> | null;
}

export interface FileOcrBundle {
  status: FileOcrStatus | null;
  progress_pct: number | null;
  language_confidence: { ar?: number; en?: number } | null;
  failure_reason: string | null;
  document_type: string | null;
  /** Whether a reviewer chose the type, or the classifier guessed it. */
  document_type_source?: "reviewer" | "classifier" | null;
  schema?: DocumentSchemaField[];
  stages?: OcrStageRun[];
  handwriting?: HandwritingAvailability;
  texts: { ar: OcrPageText[]; en: OcrPageText[] };
  fields: ExtractedField[];
  regions: OcrRegion[];
  form_fields: OcrFormField[];
  dates: ExtractedDate[];
  set_aside_regions?: OcrSetAsideRegion[];
}

export type ArtistContactKey = "artist_name" | "email" | "phone" | "address";

/** One contact/identity value pre-filled from a detected form field — never a guess at handwriting. */
export interface ExtractedContactValue {
  form_field_id: number | null;
  field_label: string | null;
  value: string | null;
  method: ExtractionMethod | null;
  needs_transcription: boolean;
}

export interface ArtistSummary {
  id: number;
  slug: string | null;
  name: { ar: string | null; en: string | null };
  legacy_code: string | null;
}

/** Ordinal tiers from string comparison, not probabilities. Mirrors ArtistCandidateFinder. */
export type ArtistMatchStrength = "high" | "medium" | "low";
export type ArtistMatchBasis = "exact_name" | "exact_variant" | "shared_name_parts" | "linked_to_archive_item";

export interface ArtistCandidate {
  artist: ArtistSummary;
  strength: ArtistMatchStrength;
  basis: ArtistMatchBasis[];
}

export interface ExistingArtistContact {
  id: number;
  name: string | null;
  role_note: string | null;
  email: string | null;
  phone: string | null;
  address: string | null;
}

export type ContactProposalField = "email" | "phone" | "address";
/** Mirrors ArtistContactProposal on the backend: undecided values follow their draft through review. */
export type ContactProposalStatus = "pending" | "changes_requested" | "approved" | "rejected" | "superseded";
export type ContactProposalSupersededReason = "newer_proposal" | "draft_rewritten" | "other_proposal_approved";

/** One contact value proposed from a document: what, where it came from, and what became of it. */
export interface ContactProposalValue {
  id: number;
  field: ContactProposalField;
  action: "new_contact" | "update_contact";
  target_contact_id: number | null;
  proposed_value: string;
  /** The value it would replace — only while undecided, and only for viewers who may read artist contacts. */
  current_value: string | null;
  current_value_shown: boolean;
  replaces_existing: boolean;
  status: ContactProposalStatus;
  superseded_reason: ContactProposalSupersededReason | null;
  edit_proposal_id: string | null;
  /** Copied when proposed, so it outlives an OCR re-run; region_id is null once that region is gone. */
  source: {
    file_id: number;
    page: number | null;
    region_id: number | null;
    bbox: { x: number; y: number; width: number; height: number } | null;
    label: string | null;
    has_crop: boolean;
  };
  extraction_method: ExtractionMethod;
  /** An OCR engine's 0–100 confidence; null for a person's transcription. */
  confidence: number | null;
  /** The handwriting model a transcription was taken from — never its text. */
  machine_suggestion: { provider: string; model: string; model_version: string | null; confidence: number | null; decision: string | null } | null;
  has_correction_mark: boolean;
  edited_by_proposer: boolean;
  proposed_by: { id: number; name: string } | null;
  proposed_at: string;
  reviewed_by: { id: number; name: string } | null;
  reviewed_at: string | null;
  review_note: string | null;
}

/** State of the authorization letter → ArtistContact flow for one file. */
export interface OcrArtistContactState {
  applicable: boolean;
  extracted: Record<ArtistContactKey, ExtractedContactValue>;
  search_name: string | null;
  candidates: ArtistCandidate[];
  confirmed_artist: (ArtistSummary & { confirmed_by_user_id: number | null; confirmed_at: string | null }) | null;
  /** Null when no artist is confirmed yet, or when the viewer may not read artist contacts. */
  existing_contacts: ExistingArtistContact[] | null;
  proposal: {
    edit_proposal_id: string;
    status: ProposalStatus | null;
    review_note: string | null;
    reviewed_at: string | null;
    target_contact_id: number | null;
    proposed_by_user_id: number | null;
    proposed_at: string | null;
  } | null;
  /** Every value proposed from this document, newest first. */
  contact_proposals: ContactProposalValue[];
  can_propose: boolean;
  can_target_existing: boolean;
}

export interface ArtistContactProposalInput {
  name: string | null;
  role_note: string | null;
  email: string | null;
  phone: string | null;
  address: string | null;
  target_contact_id: number | null;
  note: string | null;
}
