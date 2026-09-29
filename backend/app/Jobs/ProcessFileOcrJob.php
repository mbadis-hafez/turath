<?php

namespace App\Jobs;

use App\Enums\DateCalendar;
use App\Enums\ExtractedDateType;
use App\Enums\ExtractedFieldStatus;
use App\Enums\ExtractionMethod;
use App\Enums\FileOcrStatus;
use App\Models\File;
use App\Models\FileExtractedDate;
use App\Models\FileExtractedField;
use App\Models\FileExtractedText;
use App\Models\FileOcrFormField;
use App\Models\FileOcrRegion;
use App\Support\Ocr\DateExtractor;
use App\Support\Ocr\DocumentTypeClassifier;
use App\Support\Ocr\FieldExtractionHeuristic;
use App\Support\Ocr\FormFieldDetector;
use App\Support\Ocr\NonTextRegionDetector;
use App\Support\Ocr\OcrEngine;
use App\Support\Ocr\PageLayoutAnalyzer;
use App\Support\Ocr\PdfPageRasterizer;
use App\Support\Ocr\RegionClassifier;
use App\Support\Ocr\RegionCropper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * OCR's an archive item's file (image or PDF) in Arabic and English, classifies
 * every detected region on every page (printed text / handwriting / logo /
 * signature / etc — see RegionClassifier) before trusting any of its text,
 * pairs form labels with their values, extracts structured dates, guesses a
 * document type, then runs a first-pass field-extraction heuristic. Per
 * docs/privacy-rules.md:10, none of this ever touches the live record — it
 * only ever produces reviewable suggestions, which a human accepts, rejects,
 * or (for handwriting) manually transcribes through the OCR review endpoints.
 */
class ProcessFileOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A permanently-broken file shouldn't be retried against a real tesseract binary indefinitely. */
    public int $tries = 1;

    public function __construct(private readonly int $fileId) {}

    public function handle(
        OcrEngine $engine,
        PdfPageRasterizer $rasterizer,
        ?PageLayoutAnalyzer $layoutAnalyzer = null,
        ?NonTextRegionDetector $nonTextDetector = null,
        ?RegionClassifier $regionClassifier = null,
        ?FormFieldDetector $formFieldDetector = null,
        ?RegionCropper $cropper = null,
        ?DateExtractor $dateExtractor = null,
        ?DocumentTypeClassifier $documentTypeClassifier = null,
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
        $dateExtractor ??= new DateExtractor;
        $documentTypeClassifier ??= new DocumentTypeClassifier;

        $file->update(['ocr_status' => FileOcrStatus::Processing, 'ocr_progress_pct' => 0, 'ocr_failure_reason' => null]);

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

            $this->classifyRegions($file, $pagePaths, $layoutAnalyzer, $nonTextDetector, $regionClassifier, $formFieldDetector, $cropper);
            $this->saveDates($file, $dateExtractor->extract($pagesByLanguage));
            $this->saveCandidateFields($file, (new FieldExtractionHeuristic)->extract($pagesByLanguage));

            $file->update([
                'ocr_status' => FileOcrStatus::Completed,
                'ocr_progress_pct' => 100,
                'ocr_completed_at' => now(),
                'ocr_language_confidence' => [
                    'ar' => $this->averageConfidence($pagesByLanguage['ar']),
                    'en' => $this->averageConfidence($pagesByLanguage['en']),
                ],
                'document_type' => $documentTypeClassifier->classify($pagesByLanguage)->value,
            ]);
        } catch (Throwable $e) {
            Log::error('Archive file OCR failed', ['file_id' => $file->id, 'error' => $e->getMessage()]);
            $file->update(['ocr_status' => FileOcrStatus::Failed, 'ocr_failure_reason' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        } finally {
            if (Filesystem::isDirectory($tempDir)) {
                Filesystem::deleteDirectory($tempDir);
            }
        }
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
    ): void {
        $resolvedLabels = $file->ocrFormFields()->whereNotNull('transcribed_at')->pluck('field_label')->all();
        $file->ocrRegions()->delete();
        $file->ocrFormFields()->whereNull('transcribed_at')->delete();

        foreach ($pagePaths as $index => $path) {
            $pageNumber = $index + 1;
            $pageSize = $this->imageSize($path);

            $textBlocks = $layoutAnalyzer->analyze($path);
            $nonTextRegions = $nonTextDetector->detect($path, array_column($textBlocks, 'bbox'));
            $classified = $regionClassifier->classify($textBlocks, $nonTextRegions, $pageSize);

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
                ]);

                if ($row->requires_human_review) {
                    $png = $cropper->crop($path, $region['bbox']);
                    if ($png !== null) {
                        $cropPath = "archive/ocr-crops/file-{$file->id}/page-{$pageNumber}/region-{$row->id}.png";
                        Storage::disk($file->disk)->put($cropPath, $png);
                        $row->update(['crop_path' => $cropPath]);
                    }
                }

                $persisted[] = ['id' => $row->id, 'region_type' => $region['region_type'], 'bbox' => $region['bbox'], 'source_text' => $region['source_text']];
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
                ]);
            }
        }
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
     * @param  array<int, array{value: string, calendar: DateCalendar, date_type: ExtractedDateType, source_page: ?int}>  $dates
     */
    private function saveDates(File $file, array $dates): void
    {
        $file->extractedDates()->delete();
        foreach ($dates as $date) {
            FileExtractedDate::create([
                'file_id' => $file->id,
                'value' => $date['value'],
                'calendar' => $date['calendar']->value,
                'date_type' => $date['date_type']->value,
                'source_page' => $date['source_page'],
                'source_method' => 'ocr',
            ]);
        }
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

    /**
     * Reprocessing must not clobber fields a reviewer already accepted, rejected, or edited.
     *
     * @param  array<int, array{field_key: string, extracted_value: string, confidence: int, source_page: int|null}>  $candidates
     */
    private function saveCandidateFields(File $file, array $candidates): void
    {
        $reviewed = $file->extractedFields()->where('status', '!=', ExtractedFieldStatus::Pending->value)->pluck('field_key')->all();

        $file->extractedFields()->where('status', ExtractedFieldStatus::Pending->value)->delete();

        foreach ($candidates as $candidate) {
            if (in_array($candidate['field_key'], $reviewed, true)) {
                continue;
            }
            FileExtractedField::create([
                'file_id' => $file->id,
                'field_key' => $candidate['field_key'],
                'extracted_value' => $candidate['extracted_value'],
                'confidence' => $candidate['confidence'],
                'source_page' => $candidate['source_page'],
                'status' => ExtractedFieldStatus::Pending->value,
                'extraction_method' => ExtractionMethod::OcrDerived->value,
            ]);
        }
    }
}
