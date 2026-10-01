<?php

namespace App\Support\Ocr\Correction;

use App\Enums\FileOcrStatus;
use App\Enums\OcrRegionType;
use App\Models\File;
use App\Models\FileOcrRegion;
use App\Support\Ocr\ExternalProcessingPolicy;

/**
 * What may be sent to an AI correction provider at all. Two gates:
 *
 * - per file: correction is enabled, a provider is configured, OCR finished,
 *   and — for a provider outside our infrastructure — external providers are
 *   explicitly allowed and the material's access level is at or below the
 *   configured ceiling (embargoed material never leaves);
 * - per region: printed text only. The region's own ai_correction_allowed
 *   flag is required, and the type is checked again here as a second,
 *   independent line: handwriting, signatures, logos, photographs, noise,
 *   unclassified regions and form values are never sent, whatever a row's
 *   flags say — and neither is any region carrying a possible correction mark.
 */
class CorrectionPolicy
{
    public const ELIGIBLE_TYPES = [OcrRegionType::PrintedText, OcrRegionType::FormLabel, OcrRegionType::Footer];

    /** Null when the file's regions may be sent to this provider, otherwise why not. */
    public function refusalFor(File $file, OcrCorrectionProvider $provider): ?string
    {
        if (! config('ocr.correction.enabled')) {
            return 'disabled';
        }
        if ($provider->name() === NullCorrectionProvider::NAME) {
            return 'no_provider_configured';
        }
        if ($file->ocr_status !== FileOcrStatus::Completed) {
            return 'ocr_not_completed';
        }

        return $provider->isExternal() ? ExternalProcessingPolicy::refusalFor($file, 'ocr.correction') : null;
    }

    /** Null when this region may be sent, otherwise why not. */
    public function regionRefusal(FileOcrRegion $region): ?string
    {
        if (! in_array($region->region_type, self::ELIGIBLE_TYPES, true)) {
            return 'region_type_not_eligible';
        }
        if (! $region->ai_correction_allowed || ! $region->ocr_allowed) {
            return 'region_not_eligible';
        }
        // Crossed-out text reads as old and new values run together; a model would merge them.
        if ($region->has_correction_mark) {
            return 'correction_mark';
        }
        if ($region->source_text === null || trim($region->source_text) === '') {
            return 'no_text';
        }
        if (mb_strlen($region->source_text) > (int) config('ocr.correction.max_region_chars')) {
            return 'too_long';
        }

        return null;
    }
}
