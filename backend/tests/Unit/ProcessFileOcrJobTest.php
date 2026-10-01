<?php

use App\Enums\ExtractedFieldStatus;
use App\Enums\FileOcrStatus;
use App\Jobs\ExtractOcrFieldsJob;
use App\Jobs\ProcessFileOcrJob;
use App\Models\File;
use App\Models\FileExtractedField;
use App\Support\Ocr\NonTextRegionDetector;
use App\Support\Ocr\OcrEngine;
use App\Support\Ocr\OcrEngineException;
use App\Support\Ocr\PageLayoutAnalyzer;
use App\Support\Ocr\PdfPageRasterizer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/** Deterministic stand-in for the real Tesseract engine, keyed by (imagePath, language). */
class FakeOcrEngine implements OcrEngine
{
    /** @param array<string, array<string, array{text: string, confidence: int, segments?: array<int, array{text: string, confidence: int}>}>> $byPathAndLang */
    public function __construct(private array $byPathAndLang) {}

    public function recognize(string $imagePath, string $language): array
    {
        $result = $this->byPathAndLang[$imagePath][$language] ?? ['text' => '', 'confidence' => 0];

        return ['segments' => [], ...$result];
    }
}

class FakePdfPageRasterizer implements PdfPageRasterizer
{
    public function __construct(private array $pages) {}

    public function rasterize(string $pdfPath, string $outputDir): array
    {
        return $this->pages;
    }
}

/** Region classification has its own dedicated tests (RegionClassifierTest, FormFieldDetectorTest); here it's a no-op by default so these OCR-text-focused tests aren't coupled to page geometry. */
class FakePageLayoutAnalyzer implements PageLayoutAnalyzer
{
    public function analyze(string $imagePath): array
    {
        return [];
    }
}

class FakeNonTextRegionDetector implements NonTextRegionDetector
{
    public function detect(string $imagePath, array $occupiedBboxes): array
    {
        return [];
    }
}

/** Recognition with the fakes above, then the stages it queues (extraction), as a worker would run them. */
function runOcrJob(File $file, OcrEngine $engine, PdfPageRasterizer $rasterizer = new FakePdfPageRasterizer([])): void
{
    (new ProcessFileOcrJob($file->id))->handle($engine, $rasterizer, new FakePageLayoutAnalyzer, new FakeNonTextRegionDetector);
    runQueuedOcrStages();
}

it('OCRs an image in both languages, saves text per language, and completes', function () {
    $file = File::factory()->create(['mime_type' => 'image/jpeg', 'path' => 'archive/photo.jpg']);
    $path = Storage::disk('local')->path('archive/photo.jpg');

    $engine = new FakeOcrEngine([
        $path => [
            'ar' => ['text' => 'افتتاح المعرض', 'confidence' => 91],
            'en' => ['text' => 'Exhibition Opening 1979', 'confidence' => 88],
        ],
    ]);

    runOcrJob($file, $engine);
    $file->refresh();

    expect($file->ocr_status)->toBe(FileOcrStatus::Completed)
        ->and($file->ocr_progress_pct)->toBe(100)
        ->and($file->ocr_language_confidence)->toBe(['ar' => 91, 'en' => 88])
        ->and($file->ocr_completed_at)->not->toBeNull();

    $texts = $file->extractedTexts()->orderBy('language')->get();
    expect($texts)->toHaveCount(2);
    expect($texts->firstWhere('language', 'ar')->text)->toBe('افتتاح المعرض');
    expect($texts->firstWhere('language', 'en')->confidence)->toBe(88);
});

it('produces candidate fields from the OCR text without touching the live record', function () {
    $file = File::factory()->create(['mime_type' => 'image/jpeg', 'path' => 'archive/photo2.jpg']);
    $path = Storage::disk('local')->path('archive/photo2.jpg');

    $engine = new FakeOcrEngine([
        $path => [
            'ar' => ['text' => 'افتتاح المعرض', 'confidence' => 91],
            'en' => ['text' => 'Exhibition Opening 1979', 'confidence' => 88],
        ],
    ]);

    runOcrJob($file, $engine);

    $fields = $file->extractedFields()->get()->keyBy('field_key');
    expect($fields->has('title_ar'))->toBeTrue()
        ->and($fields['title_ar']->extracted_value)->toBe('افتتاح المعرض')
        ->and($fields['title_ar']->status)->toBe(ExtractedFieldStatus::Pending)
        ->and($fields->has('title_en'))->toBeTrue()
        ->and($fields['title_en']->extracted_value)->toBe('Exhibition Opening 1979')
        ->and($fields->has('date_display'))->toBeTrue()
        ->and($fields['date_display']->extracted_value)->toBe('1979');

    // Nothing here writes to the archive item itself — that only happens once a
    // reviewer explicitly accepts a field through the OCR review endpoints.
    expect($file->archiveItem->fresh()->title_en)->not->toBe('Exhibition Opening 1979');
});

it('rasterizes each PDF page and tracks progress across pages and languages', function () {
    $file = File::factory()->create(['mime_type' => 'application/pdf', 'path' => 'archive/scan.pdf']);

    $engine = new FakeOcrEngine([
        '/tmp/page-1.png' => ['ar' => ['text' => 'صفحة واحد', 'confidence' => 80], 'en' => ['text' => 'Page one', 'confidence' => 70]],
        '/tmp/page-2.png' => ['ar' => ['text' => 'صفحة اثنين', 'confidence' => 82], 'en' => ['text' => 'Page two', 'confidence' => 72]],
    ]);
    $rasterizer = new FakePdfPageRasterizer(['/tmp/page-1.png', '/tmp/page-2.png']);

    runOcrJob($file, $engine, $rasterizer);
    $file->refresh();

    expect($file->ocr_status)->toBe(FileOcrStatus::Completed)
        ->and($file->extractedTexts()->count())->toBe(4)
        ->and($file->extractedTexts()->where('page_number', 2)->where('language', 'en')->first()->text)->toBe('Page two');
});

it('marks the file failed and does not crash the queue when the OCR engine throws', function () {
    $file = File::factory()->create(['mime_type' => 'image/jpeg', 'path' => 'archive/broken.jpg']);

    $engine = new class implements OcrEngine
    {
        public function recognize(string $imagePath, string $language): array
        {
            throw new OcrEngineException('tesseract binary not found');
        }
    };

    // The job fails itself (landing in failed_jobs) rather than throwing, and isn't retried: tries = 1.
    $job = (new ProcessFileOcrJob($file->id))->withFakeQueueInteractions();
    $job->handle($engine, new FakePdfPageRasterizer([]), new FakePageLayoutAnalyzer, new FakeNonTextRegionDetector);

    $job->assertFailedWith(OcrEngineException::class);
    $file->refresh();
    expect($file->ocr_status)->toBe(FileOcrStatus::Failed)
        ->and($file->ocr_failure_reason)->toContain('tesseract binary not found')
        ->and($file->ocrStageRuns()->sole()->status)->toBe('failed');
    Queue::assertNotPushed(ExtractOcrFieldsJob::class);
});

it('skips files that are not OCR candidates, like video', function () {
    $file = File::factory()->create(['mime_type' => 'video/mp4']);

    runOcrJob($file, new FakeOcrEngine([]));

    expect($file->refresh()->ocr_status)->toBeNull();
});

it('does not overwrite fields a reviewer already accepted or rejected on reprocess', function () {
    $file = File::factory()->create(['mime_type' => 'image/jpeg', 'path' => 'archive/photo3.jpg']);
    $path = Storage::disk('local')->path('archive/photo3.jpg');
    FileExtractedField::create([
        'file_id' => $file->id, 'field_key' => 'title_en', 'extracted_value' => 'Kept as reviewer edited it',
        'confidence' => 40, 'status' => ExtractedFieldStatus::Accepted->value,
    ]);

    $engine = new FakeOcrEngine([
        $path => ['en' => ['text' => 'A totally different first line', 'confidence' => 95]],
    ]);
    runOcrJob($file, $engine);

    $field = $file->extractedFields()->where('field_key', 'title_en')->sole();
    expect($field->extracted_value)->toBe('Kept as reviewer edited it')
        ->and($field->status)->toBe(ExtractedFieldStatus::Accepted);
});
