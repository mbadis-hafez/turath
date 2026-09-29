<?php

namespace App\Jobs;

use App\Enums\ExtractedFieldStatus;
use App\Enums\FileOcrStatus;
use App\Models\File;
use App\Models\FileExtractedField;
use App\Models\FileExtractedText;
use App\Support\Ocr\FieldExtractionHeuristic;
use App\Support\Ocr\OcrEngine;
use App\Support\Ocr\PdfPageRasterizer;
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
 * OCR's an archive item's file (image or PDF) in Arabic and English, then runs
 * a first-pass field-extraction heuristic over the result. Per
 * docs/privacy-rules.md:10, none of this ever touches the live record — it
 * only ever produces reviewable suggestions (file_extracted_fields), which a
 * human accepts or rejects through the OCR review endpoints.
 */
class ProcessFileOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A permanently-broken file shouldn't be retried against a real tesseract binary indefinitely. */
    public int $tries = 1;

    public function __construct(private readonly int $fileId) {}

    public function handle(OcrEngine $engine, PdfPageRasterizer $rasterizer): void
    {
        $file = File::find($this->fileId);
        if ($file === null || ! $file->isOcrCandidate()) {
            return;
        }

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

            $this->saveCandidateFields($file, (new FieldExtractionHeuristic)->extract($pagesByLanguage));

            $file->update([
                'ocr_status' => FileOcrStatus::Completed,
                'ocr_progress_pct' => 100,
                'ocr_completed_at' => now(),
                'ocr_language_confidence' => [
                    'ar' => $this->averageConfidence($pagesByLanguage['ar']),
                    'en' => $this->averageConfidence($pagesByLanguage['en']),
                ],
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
            ]);
        }
    }
}
