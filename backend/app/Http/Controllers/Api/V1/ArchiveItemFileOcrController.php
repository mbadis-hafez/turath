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
            'extraction_method' => $f->extraction_method->value,
        ])->all();

        $regions = $file->ocrRegions()->orderBy('page_number')->get()->map(fn ($r) => [
            'id' => $r->id,
            'page_number' => $r->page_number,
            'region_type' => $r->region_type->value,
            'language' => $r->language,
            'bbox' => $r->bbox,
            'confidence' => $r->confidence,
            'ocr_allowed' => $r->ocr_allowed,
            'ai_correction_allowed' => $r->ai_correction_allowed,
            'requires_human_review' => $r->requires_human_review,
            'review_reason' => $r->review_reason,
            'has_crop' => $r->crop_path !== null,
        ])->all();

        $formFields = $file->ocrFormFields()->get()->map(fn ($f) => [
            'id' => $f->id,
            'field_label' => $f->field_label,
            'value_region_id' => $f->value_region_id,
            'value_type' => $f->value_type,
            'machine_value' => $f->machine_value,
            'manual_value' => $f->manual_value,
            'requires_manual_transcription' => $f->requires_manual_transcription,
            'transcribed_by_user_id' => $f->transcribed_by_user_id,
            'transcribed_at' => $f->transcribed_at?->toIso8601String(),
        ])->all();

        $dates = $file->extractedDates()->get()->map(fn ($d) => [
            'id' => $d->id,
            'value' => $d->value,
            'calendar' => $d->calendar->value,
            'date_type' => $d->date_type->value,
            'source_page' => $d->source_page,
            'source_method' => $d->source_method,
        ])->all();

        return response()->json(['data' => [
            'status' => $file->ocr_status?->value,
            'progress_pct' => $file->ocr_progress_pct,
            'language_confidence' => $file->ocr_language_confidence,
            'failure_reason' => $file->ocr_failure_reason,
            'document_type' => $file->document_type?->value,
            'texts' => $texts,
            'fields' => $fields,
            'regions' => $regions,
            'form_fields' => $formFields,
            'dates' => $dates,
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
