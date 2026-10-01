<?php

namespace App\Jobs;

use App\Enums\OcrRegionType;
use App\Enums\OcrStage;
use App\Jobs\Concerns\RunsAsOcrStage;
use App\Models\File;
use App\Models\FileExtractedText;
use App\Models\FileOcrFormField;
use App\Models\FileOcrRegion;
use App\Support\Ocr\CorrectionMarkDetector;
use App\Support\Ocr\CorrectionMarkRouting;
use App\Support\Ocr\FormFieldDetector;
use App\Support\Ocr\NonTextRegionDetector;
use App\Support\Ocr\OcrEngine;
use App\Support\Ocr\PageLayoutAnalyzer;
use App\Support\Ocr\PdfPageRasterizer;
use App\Support\Ocr\Pipeline\StageOutcome;
use App\Support\Ocr\RegionClassifier;
use App\Support\Ocr\RegionCropper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * The pipeline's recognize stage (see OcrPipeline). OCR's an archive item's
 * file (image or PDF) in Arabic and English, classifies every detected region
 * on every page (printed text / handwriting / logo / signature / etc — see
 * RegionClassifier) before trusting any of its text, flags possible
 * correction marks (crossed-out or struck-through text) for review, and pairs
 * form labels with their values. Dates, candidate fields and the document
 * type come after, in ExtractOcrFieldsJob, from the text stored here. Per
 * docs/privacy-rules.md:10, none of this ever touches the live record — it
 * only ever produces reviewable suggestions, which a human accepts, rejects,
 * or (for handwriting) manually transcribes through the OCR review endpoints.
 */
class ProcessFileOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsAsOcrStage, SerializesModels;

    /** Bump when a change to OCR, region classification, mark detection or form pairing should reprocess files already done. */
    public const VERSION = 'recognize-v1';

    /** A permanently-broken file shouldn't be retried against a real tesseract binary indefinitely. */
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(private readonly int $fileId, ?string $runId = null)
    {
        $this->runId = $runId;
        $this->timeout = (int) config('ocr.pipeline.timeouts.recognize', $this->timeout);
        $this->onQueue(config('ocr.pipeline.queue'));
    }

    public static function stage(): OcrStage
    {
        return OcrStage::Recognize;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [];
    }

    public function handle(
        OcrEngine $engine,
        PdfPageRasterizer $rasterizer,
        ?PageLayoutAnalyzer $layoutAnalyzer = null,
        ?NonTextRegionDetector $nonTextDetector = null,
        ?RegionClassifier $regionClassifier = null,
        ?FormFieldDetector $formFieldDetector = null,
        ?RegionCropper $cropper = null,
        ?CorrectionMarkDetector $markDetector = null,
    ): void {
        $file = File::find($this->fileId);
        if ($file === null || ! $file->isOcrCandidate()) {
            return;
        }

        $layoutAnalyzer ??= app(PageLayoutAnalyzer::class);
        $nonTextDetector ??= app(NonTextRegionDetector::class);
        $regionClassifier ??= new RegionClassifier;
        $formFieldDetector ??= new FormFieldDetector;
        $cropper ??= new RegionCropper;
        $markDetector ??= app(CorrectionMarkDetector::class);

        $this->runStage($file, fn (File $file) => $this->recognize(
            $file, $engine, $rasterizer, $layoutAnalyzer, $nonTextDetector, $regionClassifier, $formFieldDetector, $cropper, $markDetector,
        ));
    }

    private function recognize(
        File $file,
        OcrEngine $engine,
        PdfPageRasterizer $rasterizer,
        PageLayoutAnalyzer $layoutAnalyzer,
        NonTextRegionDetector $nonTextDetector,
        RegionClassifier $regionClassifier,
        FormFieldDetector $formFieldDetector,
        RegionCropper $cropper,
        CorrectionMarkDetector $markDetector,
    ): StageOutcome {
        $file->update(['ocr_progress_pct' => 0]);

        $tempDir = storage_path("app/ocr-tmp/file-{$file->id}");

        try {
            $pagePaths = $this->pagePaths($file, $rasterizer, $tempDir);
            $pageCount = count($pagePaths);
            $pagesByLanguage = ['ar' => [], 'en' => []];
            $step = 0;
            $totalSteps = max(1, $pageCount * 2);

            foreach ($pagePaths as $index => $path) {
                $pageNumber = $index + 1;
                foreach (['ar', 'en'] as $lang) {
                    $result = $engine->recognize($path, $lang);
                    FileExtractedText::updateOrCreate(
                        ['file_id' => $file->id, 'page_number' => $pageNumber, 'language' => $lang],
                        ['text' => $result['text'], 'confidence' => $result['confidence'], 'segments' => $result['segments']],
                    );
                    $pagesByLanguage[$lang][$pageNumber] = ['text' => $result['text'], 'confidence' => $result['confidence']];
                    $step++;
                    $file->update(['ocr_progress_pct' => (int) round($step / $totalSteps * 100)]);
                }
            }
            // Pages beyond this run's page count are left over from an earlier rendering; extraction must not see them.
            $file->extractedTexts()->where('page_number', '>', $pageCount)->delete();

            $this->classifyRegions($file, $pagePaths, $layoutAnalyzer, $nonTextDetector, $regionClassifier, $formFieldDetector, $cropper, $markDetector);

            $file->update([
                'ocr_progress_pct' => 100,
                'ocr_language_confidence' => [
                    'ar' => $this->averageConfidence($pagesByLanguage['ar']),
                    'en' => $this->averageConfidence($pagesByLanguage['en']),
                ],
            ]);
        } finally {
            if (Filesystem::isDirectory($tempDir)) {
                Filesystem::deleteDirectory($tempDir);
            }
        }

        return StageOutcome::succeeded([
            'pages' => $pageCount,
            'regions' => $file->ocrRegions()->count(),
            'crops' => $file->ocrRegions()->whereNotNull('crop_path')->count(),
            'form_fields' => $file->ocrFormFields()->count(),
        ]);
    }

    /**
     * @return array<int, string> absolute paths, one per page, in page order
     */
    private function pagePaths(File $file, PdfPageRasterizer $rasterizer, string $tempDir): array
    {
        $absolutePath = Storage::disk($file->disk)->path($file->path);

        if ($file->mime_type === 'application/pdf') {
            return $rasterizer->rasterize($absolutePath, $tempDir);
        }

        return [$absolutePath];
    }

    /**
     * Classifies every region on every page, crops the ones a reviewer must look at directly, and
     * pairs form labels with their nearest value region. Reprocessing must not clobber a form
     * field a reviewer already manually transcribed — those rows are left alone.
     *
     * @param  array<int, string>  $pagePaths
     */
    private function classifyRegions(
        File $file,
        array $pagePaths,
        PageLayoutAnalyzer $layoutAnalyzer,
        NonTextRegionDetector $nonTextDetector,
        RegionClassifier $regionClassifier,
        FormFieldDetector $formFieldDetector,
        RegionCropper $cropper,
        CorrectionMarkDetector $markDetector,
    ): void {
        $resolvedLabels = $file->ocrFormFields()->whereNotNull('transcribed_at')->pluck('field_label')->all();
        $file->ocrRegions()->delete();
        $file->ocrFormFields()->whereNull('transcribed_at')->delete();

        foreach ($pagePaths as $index => $path) {
            $pageNumber = $index + 1;
            $pageSize = $this->imageSize($path);

            $textBlocks = $layoutAnalyzer->analyze($path);
            $nonTextRegions = $nonTextDetector->detect($path, array_column($textBlocks, 'bbox'));
            $classified = $this->withCorrectionMarks($regionClassifier->classify($textBlocks, $nonTextRegions, $pageSize), $path, $markDetector);

            $persisted = [];
            foreach ($classified as $region) {
                /** @var FileOcrRegion $row */
                $row = FileOcrRegion::create([
                    'file_id' => $file->id,
                    'page_number' => $pageNumber,
                    'region_type' => $region['region_type']->value,
                    'language' => $region['language'],
                    'bbox' => $region['bbox'],
                    'confidence' => $region['confidence'],
                    'source_text' => $region['source_text'],
                    'ocr_allowed' => $region['ocr_allowed'],
                    'ai_correction_allowed' => $region['ai_correction_allowed'],
                    'requires_human_review' => $region['requires_human_review'],
                    'review_reason' => $region['review_reason'],
                    'has_correction_mark' => $region['has_correction_mark'],
                    'correction_marks' => $region['correction_marks'],
                ]);

                if ($row->requires_human_review) {
                    $png = $cropper->crop($path, $region['bbox']);
                    if ($png !== null) {
                        $cropPath = "archive/ocr-crops/file-{$file->id}/page-{$pageNumber}/region-{$row->id}.png";
                        Storage::disk($file->disk)->put($cropPath, $png);
                        // The hash keys handwriting suggestions to crop content, so a re-run never re-sends the same crop.
                        $row->update(['crop_path' => $cropPath, 'crop_sha256' => hash('sha256', $png)]);
                    }
                }

                $persisted[] = [
                    'id' => $row->id, 'region_type' => $region['region_type'], 'bbox' => $region['bbox'],
                    'source_text' => $region['source_text'], 'has_correction_mark' => $region['has_correction_mark'],
                ];
            }

            foreach ($formFieldDetector->pair($persisted) as $pair) {
                if (in_array($pair['field_label'], $resolvedLabels, true)) {
                    continue;
                }
                FileOcrFormField::create([
                    'file_id' => $file->id,
                    'field_label' => $pair['field_label'],
                    'label_region_id' => $pair['label_region_id'],
                    'value_region_id' => $pair['value_region_id'],
                    'value_type' => $pair['value_type'],
                    'machine_value' => $pair['machine_value'],
                    'requires_manual_transcription' => $pair['requires_manual_transcription'],
                    'has_correction_mark' => $pair['has_correction_mark'],
                ]);
            }
        }
    }

    /**
     * Runs correction-mark detection over the page's text-bearing regions and
     * routes any marked region to review (see CorrectionMarkRouting). Must run
     * before persisting, so a marked region is cropped for the reviewer.
     *
     * @param  array<int, array{region_type: OcrRegionType, language: ?string, bbox: array{x: int, y: int, width: int, height: int}, confidence: ?int, source_text: ?string, ocr_allowed: bool, ai_correction_allowed: bool, requires_human_review: bool, review_reason: ?string}>  $regions
     * @return array<int, array{region_type: OcrRegionType, language: ?string, bbox: array{x: int, y: int, width: int, height: int}, confidence: ?int, source_text: ?string, ocr_allowed: bool, ai_correction_allowed: bool, requires_human_review: bool, review_reason: ?string, has_correction_mark: bool, correction_marks: array<int, array{kind: string, bbox: array{x: int, y: int, width: int, height: int}}>|null}>
     */
    private function withCorrectionMarks(array $regions, string $pagePath, CorrectionMarkDetector $markDetector): array
    {
        $inspect = [];
        foreach ($regions as $i => $region) {
            if (in_array($region['region_type'], CorrectionMarkRouting::INSPECTED_TYPES, true)) {
                $inspect[$i] = $region['bbox'];
            }
        }
        $marks = $inspect === [] ? [] : $markDetector->detect($pagePath, $inspect);

        return array_map(
            fn (int $i) => CorrectionMarkRouting::apply($regions[$i], $marks[$i] ?? []),
            array_keys($regions),
        );
    }

    /**
     * @return array{width: int, height: int}
     */
    private function imageSize(string $path): array
    {
        $size = @getimagesize($path);

        return $size === false ? ['width' => 0, 'height' => 0] : ['width' => $size[0], 'height' => $size[1]];
    }

    /**
     * @param  array<int, array{text: string, confidence: int}>  $extracted
     */
    private function averageConfidence(array $extracted): ?int
    {
        if ($extracted === []) {
            return null;
        }
        $confidences = array_column($extracted, 'confidence');

        return (int) round(array_sum($confidences) / count($confidences));
    }
}
