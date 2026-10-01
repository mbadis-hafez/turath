<?php

namespace App\Support\Ocr;

use App\Enums\OcrRegionType;

/**
 * What a possible correction mark does to a classified region. It never
 * chooses between the crossed-out value and its replacement:
 *
 * - the region goes to human review, with a reason that says why;
 * - it is never sent to AI correction — OCR of a struck-out line reads the
 *   old and new values run together, and a model would "fix" that into one
 *   fluent, confidently wrong value;
 * - its OCR text, where OCR was allowed at all, is kept as the raw reading
 *   for the reviewer, never as a trusted value (FormFieldDetector forces
 *   manual transcription for a marked value).
 */
class CorrectionMarkRouting
{
    public const REVIEW_REASON = 'Possible correction mark (crossed-out or struck-through text) — a reviewer must decide which value is intended.';

    /** Signatures are scribble-like by nature, and logos, photographs and noise aren't text to correct. */
    public const INSPECTED_TYPES = [
        OcrRegionType::PrintedText, OcrRegionType::Handwriting, OcrRegionType::FormLabel,
        OcrRegionType::FormValue, OcrRegionType::Footer, OcrRegionType::Unknown,
    ];

    /**
     * @param  array{region_type: OcrRegionType, language: ?string, bbox: array{x: int, y: int, width: int, height: int}, confidence: ?int, source_text: ?string, ocr_allowed: bool, ai_correction_allowed: bool, requires_human_review: bool, review_reason: ?string}  $region  as RegionClassifier produces it
     * @param  array<int, array{kind: string, bbox: array{x: int, y: int, width: int, height: int}}>  $marks
     * @return array{region_type: OcrRegionType, language: ?string, bbox: array{x: int, y: int, width: int, height: int}, confidence: ?int, source_text: ?string, ocr_allowed: bool, ai_correction_allowed: bool, requires_human_review: bool, review_reason: ?string, has_correction_mark: bool, correction_marks: array<int, array{kind: string, bbox: array{x: int, y: int, width: int, height: int}}>|null}
     */
    public static function apply(array $region, array $marks): array
    {
        if ($marks === [] || ! in_array($region['region_type'], self::INSPECTED_TYPES, true)) {
            return [...$region, 'has_correction_mark' => false, 'correction_marks' => null];
        }

        return [
            ...$region,
            'has_correction_mark' => true,
            'correction_marks' => array_values($marks),
            'ai_correction_allowed' => false,
            'requires_human_review' => true,
            'review_reason' => $region['review_reason'] === null ? self::REVIEW_REASON : $region['review_reason'].' '.self::REVIEW_REASON,
        ];
    }
}
