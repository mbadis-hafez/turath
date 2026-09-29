<?php

namespace App\Enums;

/**
 * How an extracted field's value came to exist — never interchangeable, per
 * docs/privacy-rules.md:10. A reviewer accepting a value doesn't change how it
 * was produced; it changes its ExtractedFieldStatus instead.
 */
enum ExtractionMethod: string
{
    /** Read directly off an OCR engine's output, unmodified. */
    case OcrDerived = 'ocr_derived';

    /** OCR output that an AI correction pass adjusted (spelling/OCR-error correction only, no invented content). */
    case AiCorrected = 'ai_corrected';

    /** An AI produced this value without a direct OCR reading to correct — e.g. a structured guess from context. Highest scrutiny. */
    case AiInferred = 'ai_inferred';

    /** A human typed this value after reading a source crop the OCR pipeline explicitly declined to read (e.g. handwriting). */
    case ManuallyTranscribed = 'manually_transcribed';

    /** A human reviewed and confirmed a machine-produced value as correct. */
    case HumanVerified = 'human_verified';
}
