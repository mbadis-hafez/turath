<?php

namespace App\Support\Ocr;

use App\Enums\OcrRegionType;

/**
 * Turns raw page geometry (text blocks from PageLayoutAnalyzer, non-text
 * blobs from NonTextRegionDetector) into classified regions, each carrying
 * whether it may be OCR'd, whether an AI correction pass may touch it, and
 * whether a human must review it — before any of that region's text is
 * trusted anywhere downstream.
 *
 * Deliberately heuristic, not a trained classifier: label detection is a
 * generic "short line ending in a colon" rule (not a fixed dictionary, so it
 * isn't tied to one document's exact labels), and handwriting-vs-printed is a
 * confidence/token-length proxy — printed text OCR'd by a model trained on
 * print produces high, consistent confidence; handwriting run through the
 * same model tends to produce low, erratic confidence and short garbled
 * tokens. This will misclassify some regions; anything it isn't confident
 * about is marked `requires_human_review` rather than guessed at. Swapping
 * this for a trained region/handwriting detector later should not require
 * changing the file_ocr_regions schema or its consumers.
 *
 * One case worth calling out, found by running this against a real scanned
 * form: a compact row ("رقم الجوال: 05XXXXXXXX") is commonly read by
 * tesseract as a single block/line, not two — label and handwritten value on
 * the same physical line. splitInlineLabelValue() detects a colon in the
 * first few words of a block and splits it into a label sub-block and a
 * value sub-block, each classified independently — this is how a printed
 * label ends up paired with a genuinely handwritten value that needs manual
 * transcription, without inventing geometry that wasn't actually detected.
 */
class RegionClassifier
{
    private const LABEL_MAX_LENGTH = 45;

    private const LABEL_MAX_WORDS = 6;

    private const HANDWRITING_LOW_CONFIDENCE = 40;

    private const HANDWRITING_MID_CONFIDENCE = 60;

    /** Applied instead of HANDWRITING_MID_CONFIDENCE to a value split off an inline "label: value" line — see classifyTextBlock(). */
    private const HANDWRITING_MID_CONFIDENCE_STRICT = 80;

    private const HANDWRITING_SHORT_TOKEN = 3.0;

    private const FOOTER_Y_RATIO = 0.88;

    private const LOGO_MAX_AREA_RATIO = 0.04;

    private const LOGO_MAX_Y_RATIO = 0.20;

    private const NOISE_MAX_AREA_RATIO = 0.005;

    /**
     * @param  array<int, array{block_id: string, text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}, word_count: int, avg_word_length: float, words?: array<int, array{text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}}>}>  $textBlocks
     * @param  array<int, array{bbox: array{x: int, y: int, width: int, height: int}, area_ratio: float, complexity: float}>  $nonTextRegions
     * @param  array{width: int, height: int}  $pageSize
     * @return array<int, array{region_type: OcrRegionType, language: ?string, bbox: array, confidence: ?int, source_text: ?string, ocr_allowed: bool, ai_correction_allowed: bool, requires_human_review: bool, review_reason: ?string}>
     */
    public function classify(array $textBlocks, array $nonTextRegions, array $pageSize): array
    {
        $regions = [];

        foreach ($textBlocks as $block) {
            foreach ($this->splitInlineLabelValue($block) as $subBlock) {
                $type = $this->classifyTextBlock($subBlock, $pageSize);
                $regions[] = $this->toRegion($type, $subBlock['bbox'], $subBlock['confidence'], $subBlock['text']);
            }
        }

        foreach ($nonTextRegions as $blob) {
            $type = $this->classifyNonTextBlob($blob, $pageSize);
            $regions[] = $this->toRegion($type, $blob['bbox'], null, null);
        }

        return $regions;
    }

    /**
     * Splits a block into [label, value] when one of its first few words (in
     * tesseract's own reading order) ends in a colon — the same-line "label:
     * value" case. Returns the block unchanged (as a single-element array)
     * when there's no word geometry to split on, no colon found in that
     * prefix window, or nothing follows the colon.
     *
     * @param  array{block_id: string, text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}, word_count: int, avg_word_length: float, words?: array<int, array{text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}}>}  $block
     * @return array<int, array{block_id: string, text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}, word_count: int, avg_word_length: float}>
     */
    private function splitInlineLabelValue(array $block): array
    {
        $words = $block['words'] ?? [];
        if (count($words) < 2) {
            return [$block];
        }

        $splitIndex = null;
        foreach (array_slice($words, 0, self::LABEL_MAX_WORDS, preserve_keys: true) as $i => $word) {
            if (preg_match('/[:：]\s*$/u', trim($word['text'])) === 1) {
                $splitIndex = $i;
                break;
            }
        }
        if ($splitIndex === null) {
            return [$block];
        }

        $valueWords = array_slice($words, $splitIndex + 1);
        if ($valueWords === []) {
            return [$block];
        }

        return [
            $this->blockFromWords(array_slice($words, 0, $splitIndex + 1)),
            $this->blockFromWords($valueWords, isSplitValue: true),
        ];
    }

    /**
     * @param  array<int, array{text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}}>  $words
     * @return array{block_id: string, text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}, word_count: int, avg_word_length: float, is_split_value: bool}
     */
    private function blockFromWords(array $words, bool $isSplitValue = false): array
    {
        $texts = array_column($words, 'text');
        $confidences = array_column($words, 'confidence');
        $x1 = min(array_map(fn ($w) => $w['bbox']['x'], $words));
        $y1 = min(array_map(fn ($w) => $w['bbox']['y'], $words));
        $x2 = max(array_map(fn ($w) => $w['bbox']['x'] + $w['bbox']['width'], $words));
        $y2 = max(array_map(fn ($w) => $w['bbox']['y'] + $w['bbox']['height'], $words));

        return [
            'block_id' => 'split',
            'text' => implode(' ', $texts),
            'confidence' => (int) round(array_sum($confidences) / count($confidences)),
            'bbox' => ['x' => $x1, 'y' => $y1, 'width' => $x2 - $x1, 'height' => $y2 - $y1],
            'word_count' => count($words),
            'avg_word_length' => array_sum(array_map('mb_strlen', $texts)) / count($texts),
            'is_split_value' => $isSplitValue,
        ];
    }

    /**
     * @param  array{block_id: string, text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}, word_count: int, avg_word_length: float, is_split_value?: bool}  $block
     * @param  array{width: int, height: int}  $pageSize
     */
    private function classifyTextBlock(array $block, array $pageSize): OcrRegionType
    {
        $text = trim($block['text']);
        $isLabel = mb_strlen($text) <= self::LABEL_MAX_LENGTH
            && $block['word_count'] <= self::LABEL_MAX_WORDS
            && preg_match('/[:：]\s*$/u', $text) === 1;
        if ($isLabel) {
            return OcrRegionType::FormLabel;
        }

        // A value split off an inline "label: value" line is inherently answer-shaped (a name,
        // number, or address — typically one or two long tokens), so the normal "short token"
        // signal below doesn't fire for it even when it's garbled handwriting: a real miss found
        // by running this against a scanned form was "رقم الجوال: 05XXXXXXXX" (a handwritten
        // phone number) OCR'd as "031/4410 ط ©" at confidence 62 — comfortably above the normal
        // 60 threshold, and its average token length (~3.3) is too long to trip the short-token
        // check either, even though it's clearly not a real printed phone number. Split values
        // are held to a flat, stricter confidence bar instead.
        $isHandwritingLike = ($block['is_split_value'] ?? false)
            ? $block['confidence'] < self::HANDWRITING_MID_CONFIDENCE_STRICT
            : ($block['confidence'] < self::HANDWRITING_LOW_CONFIDENCE
                || ($block['confidence'] < self::HANDWRITING_MID_CONFIDENCE && $block['avg_word_length'] < self::HANDWRITING_SHORT_TOKEN));

        if ($isHandwritingLike) {
            $areaRatio = $this->areaRatio($block['bbox'], $pageSize);
            $inLowerHalf = $pageSize['height'] > 0 && $block['bbox']['y'] > $pageSize['height'] * 0.5;
            if ($block['word_count'] <= 2 && $areaRatio < 0.02 && $inLowerHalf) {
                return OcrRegionType::Signature;
            }

            return OcrRegionType::Handwriting;
        }

        if ($pageSize['height'] > 0 && $block['bbox']['y'] > $pageSize['height'] * self::FOOTER_Y_RATIO) {
            return OcrRegionType::Footer;
        }

        return OcrRegionType::PrintedText;
    }

    /**
     * @param  array{bbox: array{x: int, y: int, width: int, height: int}, area_ratio: float, complexity: float}  $blob
     * @param  array{width: int, height: int}  $pageSize
     */
    private function classifyNonTextBlob(array $blob, array $pageSize): OcrRegionType
    {
        if ($blob['area_ratio'] < self::NOISE_MAX_AREA_RATIO) {
            return OcrRegionType::Noise;
        }

        $nearTopCorner = $pageSize['height'] > 0 && $blob['bbox']['y'] < $pageSize['height'] * self::LOGO_MAX_Y_RATIO;
        if ($blob['area_ratio'] < self::LOGO_MAX_AREA_RATIO && $nearTopCorner) {
            return OcrRegionType::Logo;
        }

        if ($blob['area_ratio'] >= self::LOGO_MAX_AREA_RATIO) {
            return OcrRegionType::Photograph;
        }

        // Small, not near a header corner: could be either — don't guess.
        return OcrRegionType::Unknown;
    }

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $bbox
     */
    private function areaRatio(array $bbox, array $pageSize): float
    {
        $pageArea = $pageSize['width'] * $pageSize['height'];

        return $pageArea > 0 ? ($bbox['width'] * $bbox['height']) / $pageArea : 0.0;
    }

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $bbox
     * @return array{region_type: OcrRegionType, language: ?string, bbox: array, confidence: ?int, source_text: ?string, ocr_allowed: bool, ai_correction_allowed: bool, requires_human_review: bool, review_reason: ?string}
     */
    private function toRegion(OcrRegionType $type, array $bbox, ?int $confidence, ?string $text): array
    {
        return [
            'region_type' => $type,
            'language' => $type->ocrAllowed() ? $this->detectLanguage($text) : null,
            'bbox' => $bbox,
            'confidence' => $confidence,
            'source_text' => $type->ocrAllowed() ? $text : null,
            'ocr_allowed' => $type->ocrAllowed(),
            'ai_correction_allowed' => $type->aiCorrectionAllowed(),
            'requires_human_review' => $type->requiresHumanReview(),
            'review_reason' => $type->defaultReviewReason(),
        ];
    }

    private function detectLanguage(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $arabicCount = preg_match_all('/\p{Arabic}/u', $text);
        $latinCount = preg_match_all('/[A-Za-z]/', $text);

        if ($arabicCount === 0 && $latinCount === 0) {
            return null;
        }

        return $arabicCount >= $latinCount ? 'ar' : 'en';
    }
}
