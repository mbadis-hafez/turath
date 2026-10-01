<?php

use App\Enums\OcrRegionType;
use App\Support\Ocr\RegionClassifier;

function classifyPage(array $textBlocks, array $nonTextRegions = [], array $pageSize = ['width' => 1000, 'height' => 1400]): array
{
    return (new RegionClassifier)->classify($textBlocks, $nonTextRegions, $pageSize);
}

function block(string $text, int $confidence, array $bbox, ?int $wordCount = null): array
{
    return [
        'block_id' => '1',
        'text' => $text,
        'confidence' => $confidence,
        'bbox' => $bbox,
        'word_count' => $wordCount ?? count(preg_split('/\s+/u', trim($text)) ?: []),
        'avg_word_length' => mb_strlen(str_replace(' ', '', $text)) / max(1, $wordCount ?? count(preg_split('/\s+/u', trim($text)) ?: [])),
    ];
}

it('classifies confident, multi-word text as printed and OCR-allowed', function () {
    $regions = classifyPage([block('نطلب منك التكرم بتفويضنا لاستخدام أعمالك الفنية', 88, ['x' => 100, 'y' => 200, 'width' => 600, 'height' => 40])]);

    expect($regions)->toHaveCount(1)
        ->and($regions[0]['region_type'])->toBe(OcrRegionType::PrintedText)
        ->and($regions[0]['ocr_allowed'])->toBeTrue()
        ->and($regions[0]['ai_correction_allowed'])->toBeTrue()
        ->and($regions[0]['requires_human_review'])->toBeFalse()
        ->and($regions[0]['language'])->toBe('ar');
});

it('classifies a short colon-terminated line as a form label, regardless of its wording', function () {
    $regions = classifyPage([block('اسم الفنان/ة:', 90, ['x' => 100, 'y' => 300, 'width' => 150, 'height' => 30])]);

    expect($regions[0]['region_type'])->toBe(OcrRegionType::FormLabel)
        ->and($regions[0]['requires_human_review'])->toBeFalse();
});

it('classifies low-confidence, garbled text as handwriting requiring review, never OCR/AI-correctable', function () {
    $regions = classifyPage([block('عبد ثهر السل', 22, ['x' => 200, 'y' => 500, 'width' => 250, 'height' => 35])]);

    expect($regions[0]['region_type'])->toBe(OcrRegionType::Handwriting)
        ->and($regions[0]['ocr_allowed'])->toBeFalse()
        ->and($regions[0]['ai_correction_allowed'])->toBeFalse()
        ->and($regions[0]['requires_human_review'])->toBeTrue()
        ->and($regions[0]['source_text'])->toBeNull()
        ->and($regions[0]['review_reason'])->toContain('manual transcription');
});

it('classifies a tiny, low-confidence, few-word mark near the bottom of the page as a signature', function () {
    $regions = classifyPage([block('امض', 15, ['x' => 150, 'y' => 1300, 'width' => 80, 'height' => 20], wordCount: 1)]);

    expect($regions[0]['region_type'])->toBe(OcrRegionType::Signature)
        ->and($regions[0]['requires_human_review'])->toBeTrue();
});

it('classifies text in the bottom margin as a footer, distinct from body text', function () {
    $regions = classifyPage([block('mali@hafezgallery.com +966554711681', 75, ['x' => 100, 'y' => 1330, 'width' => 500, 'height' => 20])]);

    expect($regions[0]['region_type'])->toBe(OcrRegionType::Footer)
        ->and($regions[0]['ocr_allowed'])->toBeTrue();
});

it('never sends a logo, photograph, or noise blob through OCR or AI correction', function () {
    $regions = classifyPage([], [
        ['bbox' => ['x' => 800, 'y' => 20, 'width' => 120, 'height' => 100], 'area_ratio' => 0.008, 'complexity' => 30.0], // small, top corner => logo
        ['bbox' => ['x' => 100, 'y' => 500, 'width' => 500, 'height' => 400], 'area_ratio' => 0.14, 'complexity' => 60.0], // large => photograph
        ['bbox' => ['x' => 50, 'y' => 50, 'width' => 10, 'height' => 10], 'area_ratio' => 0.0001, 'complexity' => 5.0], // speck => noise
    ]);

    $byType = collect($regions)->groupBy(fn ($r) => $r['region_type']->value);
    expect($byType->has('logo'))->toBeTrue()
        ->and($byType->has('photograph'))->toBeTrue()
        ->and($byType->has('noise'))->toBeTrue();

    foreach ($regions as $region) {
        expect($region['ocr_allowed'])->toBeFalse()
            ->and($region['ai_correction_allowed'])->toBeFalse();
    }
    expect($byType['noise'][0]['requires_human_review'])->toBeFalse();
});

it('marks a non-text blob it cannot confidently place as unknown, requiring review rather than guessing', function () {
    $regions = classifyPage([], [
        ['bbox' => ['x' => 400, 'y' => 700, 'width' => 150, 'height' => 100], 'area_ratio' => 0.01, 'complexity' => 40.0],
    ]);

    expect($regions[0]['region_type'])->toBe(OcrRegionType::Unknown)
        ->and($regions[0]['requires_human_review'])->toBeTrue();
});

/** A block with word-level geometry, needed to exercise the inline "label: value" split — words are laid left to right for simplicity; only order matters, not direction. Default y keeps it out of the page's lower half so short splits aren't mistaken for a signature. */
function wordBlock(array $words, int $y = 200, int $height = 40): array
{
    $x = 100;
    $wordEntries = [];
    foreach ($words as [$text, $confidence]) {
        $width = max(20, mb_strlen($text) * 12);
        $wordEntries[] = ['text' => $text, 'confidence' => $confidence, 'bbox' => ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]];
        $x += $width + 10;
    }
    $texts = array_column($words, 0);
    $allX = array_map(fn ($w) => $w['bbox']['x'], $wordEntries);
    $allX2 = array_map(fn ($w) => $w['bbox']['x'] + $w['bbox']['width'], $wordEntries);
    $confidences = array_column($words, 1);

    return [
        'block_id' => '1',
        'text' => implode(' ', $texts),
        'confidence' => (int) round(array_sum($confidences) / count($confidences)),
        'bbox' => ['x' => min($allX), 'y' => $y, 'width' => max($allX2) - min($allX), 'height' => $height],
        'word_count' => count($words),
        'avg_word_length' => array_sum(array_map('mb_strlen', $texts)) / count($texts),
        'words' => $wordEntries,
    ];
}

it('splits a same-line "label: value" block, discovered by running against a real scanned form where tesseract read them as one block', function () {
    $regions = classifyPage([wordBlock([['الموضوع:', 92], ['بدايات', 90], ['الحركة', 91], ['الفنية', 93]])]);

    $byType = collect($regions)->keyBy(fn ($r) => $r['region_type']->value);
    expect($byType->has('form_label'))->toBeTrue()
        ->and($byType['form_label']['source_text'])->toBe('الموضوع:')
        ->and($byType->has('printed_text'))->toBeTrue()
        ->and($byType['printed_text']['source_text'])->toBe('بدايات الحركة الفنية')
        ->and($byType['printed_text']['ocr_allowed'])->toBeTrue();
});

it('does not trust a moderate-confidence value split off a label line as printed — the exact miss found against a real handwritten phone number', function () {
    // Real case: "رقم الجوال: 05XXXXXXXX" (a handwritten phone number) OCR'd by tesseract at confidence 62 —
    // above the normal 60 "printed" threshold, but "031/4410" is not a plausible reading of a real phone number.
    // A value this short-and-answer-shaped needs a stricter bar than a full sentence of body text.
    $regions = classifyPage([wordBlock([['رقم', 90], ['الجوال:', 88], ['031/4410', 62]])]);

    $byType = collect($regions)->keyBy(fn ($r) => $r['region_type']->value);
    expect($byType->has('form_label'))->toBeTrue()
        ->and($byType->has('handwriting'))->toBeTrue()
        ->and($byType['handwriting']['ocr_allowed'])->toBeFalse()
        ->and($byType['handwriting']['source_text'])->toBeNull()
        ->and($byType['handwriting']['requires_human_review'])->toBeTrue();
});

it('still splits when the value is a full sentence, only the value side gets the stricter bar applied and it comfortably clears it', function () {
    $regions = classifyPage([wordBlock([['أغراض', 93], ['البحث:', 92], ['سيتم', 91], ['استخدام', 92], ['أعمالك', 90]])]);

    $byType = collect($regions)->keyBy(fn ($r) => $r['region_type']->value);
    expect($byType['printed_text']['ocr_allowed'])->toBeTrue()
        ->and($byType['printed_text']['source_text'])->toContain('سيتم استخدام أعمالك');
});

it('leaves a block with no word geometry classified as a whole, unaffected by the split logic', function () {
    $regions = classifyPage([block('رقم الجوال: 0555555555', 62, ['x' => 100, 'y' => 900, 'width' => 300, 'height' => 40])]);

    expect($regions)->toHaveCount(1);
});
