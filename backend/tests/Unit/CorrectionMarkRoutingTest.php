<?php

use App\Enums\OcrRegionType;
use App\Jobs\ProcessFileOcrJob;
use App\Models\File;
use App\Support\Ocr\CorrectionMarkDetector;
use App\Support\Ocr\CorrectionMarkRouting;
use App\Support\Ocr\FormFieldDetector;
use App\Support\Ocr\NonTextRegionDetector;
use App\Support\Ocr\OcrEngine;
use App\Support\Ocr\PageLayoutAnalyzer;
use App\Support\Ocr\PdfPageRasterizer;

function classifiedRegion(OcrRegionType $type, ?string $text = 'old@example.com new@example.com'): array
{
    return [
        'region_type' => $type, 'language' => 'en', 'bbox' => ['x' => 100, 'y' => 200, 'width' => 400, 'height' => 40],
        'confidence' => 85, 'source_text' => $type->ocrAllowed() ? $text : null,
        'ocr_allowed' => $type->ocrAllowed(), 'ai_correction_allowed' => $type->aiCorrectionAllowed(),
        'requires_human_review' => $type->requiresHumanReview(), 'review_reason' => $type->defaultReviewReason(),
    ];
}

const A_SCRIBBLE = ['kind' => 'scribble', 'bbox' => ['x' => 110, 'y' => 205, 'width' => 120, 'height' => 30]];

it('routes a marked printed region to review and never to AI correction, keeping its raw reading', function () {
    $routed = CorrectionMarkRouting::apply(classifiedRegion(OcrRegionType::PrintedText), [A_SCRIBBLE]);

    expect($routed['has_correction_mark'])->toBeTrue()
        ->and($routed['correction_marks'])->toBe([A_SCRIBBLE])
        ->and($routed['requires_human_review'])->toBeTrue()
        ->and($routed['ai_correction_allowed'])->toBeFalse()
        ->and($routed['review_reason'])->toBe(CorrectionMarkRouting::REVIEW_REASON)
        // Nothing is chosen: the OCR reading (both values run together) stays as it was read.
        ->and($routed['source_text'])->toBe('old@example.com new@example.com');
});

it('adds the correction-mark reason to a handwriting region\'s own reason', function () {
    $routed = CorrectionMarkRouting::apply(classifiedRegion(OcrRegionType::Handwriting), [A_SCRIBBLE]);

    expect($routed['review_reason'])->toStartWith('Handwritten content requires manual transcription.')
        ->and($routed['review_reason'])->toEndWith(CorrectionMarkRouting::REVIEW_REASON);
});

it('leaves unmarked regions, and regions that are not text, as they were', function () {
    $plain = CorrectionMarkRouting::apply(classifiedRegion(OcrRegionType::PrintedText), []);
    $signature = CorrectionMarkRouting::apply(classifiedRegion(OcrRegionType::Signature), [A_SCRIBBLE]);

    expect($plain)->toMatchArray(['has_correction_mark' => false, 'correction_marks' => null, 'ai_correction_allowed' => true, 'requires_human_review' => false])
        ->and($signature['has_correction_mark'])->toBeFalse();
});

it('forces manual transcription for a marked form value, even a printed one, and says why', function () {
    $fields = (new FormFieldDetector)->pair([
        ['id' => 1, 'region_type' => OcrRegionType::FormLabel, 'bbox' => ['x' => 600, 'y' => 200, 'width' => 150, 'height' => 40], 'source_text' => 'البريد الإلكتروني:'],
        ['id' => 2, 'region_type' => OcrRegionType::PrintedText, 'bbox' => ['x' => 100, 'y' => 200, 'width' => 400, 'height' => 40], 'source_text' => 'old@example.com new@example.com', 'has_correction_mark' => true],
    ]);

    expect($fields[0])->toMatchArray([
        'value_region_id' => 2,
        'machine_value' => 'old@example.com new@example.com',
        'requires_manual_transcription' => true,
        'has_correction_mark' => true,
    ]);
});

it('leaves an unmarked printed form value trusted as before', function () {
    $fields = (new FormFieldDetector)->pair([
        ['id' => 1, 'region_type' => OcrRegionType::FormLabel, 'bbox' => ['x' => 600, 'y' => 200, 'width' => 150, 'height' => 40], 'source_text' => 'الموضوع:'],
        ['id' => 2, 'region_type' => OcrRegionType::PrintedText, 'bbox' => ['x' => 100, 'y' => 200, 'width' => 400, 'height' => 40], 'source_text' => 'بدايات الحركة الفنية'],
    ]);

    expect($fields[0])->toMatchArray(['requires_manual_transcription' => false, 'has_correction_mark' => false]);
});

/*
 * End to end through the job, with fakes for tesseract and the pixel
 * detector: an email line whose value was crossed out and rewritten.
 */

class MarkTestOcrEngine implements OcrEngine
{
    public function recognize(string $imagePath, string $language): array
    {
        return ['text' => 'البريد الإلكتروني: old@example.com new@example.com', 'confidence' => 85, 'segments' => []];
    }
}

class MarkTestRasterizer implements PdfPageRasterizer
{
    public function rasterize(string $pdfPath, string $outputDir): array
    {
        return [];
    }
}

class MarkTestLayout implements PageLayoutAnalyzer
{
    public function analyze(string $imagePath): array
    {
        $word = fn (string $text, int $x, int $conf) => ['text' => $text, 'confidence' => $conf, 'bbox' => ['x' => $x, 'y' => 200, 'width' => 140, 'height' => 40]];
        $words = [$word('البريد', 900, 92), $word('الإلكتروني:', 740, 91), $word('old@example.com', 420, 88), $word('new@example.com', 250, 90)];

        return [[
            'block_id' => '1', 'text' => implode(' ', array_column($words, 'text')), 'confidence' => 90,
            'bbox' => ['x' => 250, 'y' => 200, 'width' => 790, 'height' => 40], 'word_count' => 4, 'avg_word_length' => 12.0, 'words' => $words,
        ]];
    }
}

class MarkTestNoBlobs implements NonTextRegionDetector
{
    public function detect(string $imagePath, array $occupiedBboxes): array
    {
        return [];
    }
}

/** Flags whichever inspected region lies over the crossed-out address; records what it was asked. */
class MarkTestDetector implements CorrectionMarkDetector
{
    /** @var array<int, array<int, array{x: int, y: int, width: int, height: int}>> */
    public array $asked = [];

    public function detect(string $imagePath, array $bboxes): array
    {
        $this->asked[] = $bboxes;
        $found = [];
        foreach ($bboxes as $key => $bbox) {
            if ($bbox['x'] <= 430 && $bbox['x'] + $bbox['width'] >= 560) {
                $found[$key] = [['kind' => 'scribble', 'bbox' => ['x' => 420, 'y' => 200, 'width' => 140, 'height' => 40]]];
            }
        }

        return $found;
    }
}

it('persists the mark on the region and its form field, sends it to review, and keeps it away from AI correction', function () {
    $file = File::factory()->create(['mime_type' => 'image/png', 'path' => 'archive/form.png']);
    $detector = new MarkTestDetector;

    (new ProcessFileOcrJob($file->id))->handle(new MarkTestOcrEngine, new MarkTestRasterizer, new MarkTestLayout, new MarkTestNoBlobs, markDetector: $detector);

    $value = $file->ocrRegions()->where('region_type', '!=', 'form_label')->sole();
    expect($value->has_correction_mark)->toBeTrue()
        ->and($value->correction_marks[0]['kind'])->toBe('scribble')
        ->and($value->requires_human_review)->toBeTrue()
        ->and($value->ai_correction_allowed)->toBeFalse()
        ->and($value->review_reason)->toContain('Possible correction mark');

    $field = $file->ocrFormFields()->sole();
    expect($field->has_correction_mark)->toBeTrue()
        ->and($field->requires_manual_transcription)->toBeTrue()
        ->and($field->manual_value)->toBeNull();

    // The label was inspected too; the detector only ever sees text-bearing regions.
    expect($detector->asked)->toHaveCount(1)->and($detector->asked[0])->toHaveCount(2);
});
