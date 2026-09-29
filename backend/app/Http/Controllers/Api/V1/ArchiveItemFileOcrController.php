<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FileOcrStatus;
use App\Jobs\ProcessFileOcrJob;
use App\Models\ArchiveItem;
use Illuminate\Http\JsonResponse;

/** OCR status, extracted text, and candidate fields for an archive item's one file. */
class ArchiveItemFileOcrController
{
    public function show(ArchiveItem $archiveItem): JsonResponse
    {
        $file = $archiveItem->files()->where('role', 'original')->latest('id')->first();
        if ($file === null) {
            return response()->json(['data' => null]);
        }

        $texts = ['ar' => [], 'en' => []];
        foreach ($file->extractedTexts()->orderBy('page_number')->get() as $text) {
            $texts[$text->language][] = [
                'page' => $text->page_number,
                'text' => $text->text,
                'confidence' => $text->confidence,
                'segments' => $text->segments ?? [],
            ];
        }

        $fields = $file->extractedFields()->orderBy('field_key')->get()->map(fn ($f) => [
            'id' => $f->id,
            'field_key' => $f->field_key,
            'extracted_value' => $f->extracted_value,
            'confidence' => $f->confidence,
            'source_page' => $f->source_page,
            'status' => $f->status->value,
        ])->all();

        return response()->json(['data' => [
            'status' => $file->ocr_status?->value,
            'progress_pct' => $file->ocr_progress_pct,
            'language_confidence' => $file->ocr_language_confidence,
            'failure_reason' => $file->ocr_failure_reason,
            'texts' => $texts,
            'fields' => $fields,
        ]]);
    }

    /** POST .../file/ocr/run — (re)dispatches OCR for the item's file, for files never processed (e.g. uploaded before this feature) or whose run failed. */
    public function run(ArchiveItem $archiveItem): JsonResponse
    {
        $file = $archiveItem->files()->where('role', 'original')->latest('id')->first();
        if ($file === null || ! $file->isOcrCandidate()) {
            return response()->json(['message' => 'This file cannot be processed with OCR.'], 422);
        }
        if ($file->ocr_status === FileOcrStatus::Pending || $file->ocr_status === FileOcrStatus::Processing) {
            return response()->json(['message' => 'OCR is already running for this file.'], 409);
        }

        $file->update(['ocr_status' => FileOcrStatus::Pending, 'ocr_progress_pct' => 0, 'ocr_failure_reason' => null]);
        ProcessFileOcrJob::dispatch($file->id);

        return response()->json(['data' => ['status' => FileOcrStatus::Pending->value]]);
    }
}
