<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentType;
use App\Enums\OcrStage;
use App\Models\ArchiveItem;
use App\Models\File;
use App\Models\FileEntityMatch;
use App\Models\FileExtractedField;
use App\Support\Ocr\Extraction\DocumentFieldSchema;
use App\Support\Ocr\Extraction\FieldDefinition;
use App\Support\Ocr\HandwritingSuggestionService;
use App\Support\Ocr\Matching\EntityMatchPresenter;
use App\Support\Ocr\OcrReviewPresenter;
use App\Support\Ocr\Pipeline\OcrPipeline;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** OCR status, extracted text, and candidate fields for an archive item's one file. */
class ArchiveItemFileOcrController
{
    public function show(Request $request, ArchiveItem $archiveItem, HandwritingSuggestionService $handwriting, OcrPipeline $pipeline, EntityMatchPresenter $matches, OcrReviewPresenter $review): JsonResponse
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

        // The archive item's own fields first, then the document type's, in schema order.
        $schemaKeys = array_map(fn (FieldDefinition $f) => $f->key, DocumentFieldSchema::for($file->document_type ?? DocumentType::Unknown));
        $extracted = $file->extractedFields()->orderBy('field_key')->orderBy('ordinal')->get();
        $regionRows = $file->ocrRegions()->orderBy('page_number')->orderBy('id')->get();
        // Candidate records for each extracted name — suggestions until a reviewer confirms one.
        $matched = $matches->present(FileEntityMatch::query()->where('file_id', $file->id)->get(), $request->user());
        // Each value's layers: source region and its OCR, the AI correction, what the record holds now.
        $evidence = $review->evidence($file, $archiveItem, $extracted, $regionRows);
        $fields = $extracted
            ->sortBy(fn (FileExtractedField $f) => [$f->document_type === null ? 0 : 1, array_search($f->field_key, $schemaKeys, true) ?: 0, $f->field_key, $f->ordinal])
            ->map(fn (FileExtractedField $f) => [...ArchiveItemFileOcrFieldController::present($f), ...$evidence[$f->id], 'match' => $matched[$f->id] ?? null])
            ->values()->all();

        $regions = $regionRows->map(fn ($r) => [
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
            'has_correction_mark' => $r->has_correction_mark,
            'correction_marks' => $r->correction_marks ?? [],
            'has_crop' => $r->crop_path !== null,
        ])->all();

        $formFields = $file->ocrFormFields()->with('valueRegion')->get()->map(function ($f) use ($handwriting) {
            $suggestion = $f->valueRegion !== null ? $handwriting->latestFor($f->valueRegion) : null;

            return [
                'id' => $f->id,
                'field_label' => $f->field_label,
                'value_region_id' => $f->value_region_id,
                'value_type' => $f->value_type,
                'machine_value' => $f->machine_value,
                'manual_value' => $f->manual_value,
                'requires_manual_transcription' => $f->requires_manual_transcription,
                'has_correction_mark' => $f->has_correction_mark,
                // A machine reading of the handwriting, if one was requested — never the field's value by itself.
                'suggestion' => $suggestion !== null ? ArchiveItemFileOcrHandwritingController::present($suggestion) : null,
                'can_request_suggestion' => $f->valueRegion !== null && $handwriting->regionRefusal($f->valueRegion) === null,
                'transcribed_by_user_id' => $f->transcribed_by_user_id,
                'transcribed_at' => $f->transcribed_at?->toIso8601String(),
            ];
        })->all();

        $dates = $file->extractedDates()->orderBy('source_page')->orderBy('id')->get()->map(fn ($d) => ArchiveItemFileOcrDateController::present($d))->all();

        return response()->json(['data' => [
            'status' => $file->ocr_status?->value,
            'progress_pct' => $file->ocr_progress_pct,
            'language_confidence' => $file->ocr_language_confidence,
            'failure_reason' => $file->ocr_failure_reason,
            'document_type' => $file->document_type?->value,
            'document_type_source' => $file->document_type === null ? null : ($file->document_type_set_by_user_id !== null ? 'reviewer' : 'classifier'),
            'schema' => $this->schema($file, $extracted),
            'stages' => $pipeline->describe($file),
            'handwriting' => $handwriting->describe($file),
            'texts' => $texts,
            'fields' => $fields,
            'regions' => $regions,
            'form_fields' => $formFields,
            'dates' => $dates,
            'set_aside_regions' => $review->setAside($file, $regionRows, $extracted),
        ]]);
    }

    /**
     * PUT .../file/ocr/document-type — a reviewer's correction of the
     * classifier: the chosen type decides which fields are extracted, and
     * extraction re-runs with it. Null hands the choice back to the classifier.
     */
    public function setDocumentType(Request $request, ArchiveItem $archiveItem, OcrPipeline $pipeline): JsonResponse
    {
        $data = $request->validate(['document_type' => ['present', 'nullable', Rule::enum(DocumentType::class)]]);
        $file = $archiveItem->files()->where('role', 'original')->latest('id')->first();
        abort_if($file === null, 404);

        $file->update($data['document_type'] === null
            ? ['document_type_set_by_user_id' => null, 'document_type_set_at' => null]
            : ['document_type' => $data['document_type'], 'document_type_set_by_user_id' => $request->user()->id, 'document_type_set_at' => now()]);

        // Not forced: re-extraction only happens if the choice actually changed what it reads.
        $result = $pipeline->start($file, OcrStage::Extract);

        return response()->json(['data' => [
            'document_type' => $file->document_type?->value,
            'document_type_source' => $file->document_type_set_by_user_id !== null ? 'reviewer' : 'classifier',
            'result' => $result,
        ]]);
    }

    /**
     * The file's document type's fields, and whether anything was found for each.
     *
     * @param  Collection<int, FileExtractedField>  $extracted
     * @return list<array{key: string, label: array{ar: string, en: string}, kind: string, route: string, target: ?string, found: bool}>
     */
    private function schema(File $file, Collection $extracted): array
    {
        $type = $file->document_type ?? DocumentType::Unknown;
        $dateKeys = $file->extractedDates()->whereNotNull('field_key')->pluck('field_key')->all();
        $fieldKeys = $extracted->filter(fn (FileExtractedField $f) => $f->document_type === $type && $f->currentValue() !== null)->pluck('field_key')->all();

        return array_map(fn (FieldDefinition $f) => [
            'key' => $f->key,
            'label' => $f->label,
            'kind' => $f->kind,
            'route' => $f->route,
            'target' => $f->target,
            'found' => in_array($f->key, $f->kind === FieldDefinition::KIND_DATE ? $dateKeys : $fieldKeys, true),
        ], DocumentFieldSchema::for($type));
    }

    /**
     * POST .../file/ocr/run — OCRs the item's file again from the start, for
     * files never processed (e.g. uploaded before this feature), whose run
     * failed, or that a reviewer wants re-read. Later stages re-run only if
     * the new OCR changed their input.
     */
    public function run(ArchiveItem $archiveItem, OcrPipeline $pipeline): JsonResponse
    {
        return $this->startPipeline($archiveItem, $pipeline, OcrStage::Recognize);
    }

    /**
     * POST .../file/ocr/stages/{stage}/run — re-runs one stage (and whichever
     * later stages its new output affects), leaving earlier stages alone.
     */
    public function runStage(ArchiveItem $archiveItem, string $stage, OcrPipeline $pipeline): JsonResponse
    {
        return $this->startPipeline($archiveItem, $pipeline, OcrStage::from($stage));
    }

    private function startPipeline(ArchiveItem $archiveItem, OcrPipeline $pipeline, OcrStage $from): JsonResponse
    {
        $file = $archiveItem->files()->where('role', 'original')->latest('id')->first();
        if ($file === null || ! $file->isOcrCandidate()) {
            return response()->json(['message' => 'This file cannot be processed with OCR.'], 422);
        }

        $result = $pipeline->start($file, $from, force: true);
        if ($result === OcrPipeline::BUSY) {
            return response()->json(['message' => 'OCR is already running for this file.'], 409);
        }
        if ($result === OcrPipeline::PREREQUISITES_MISSING) {
            return response()->json(['message' => 'The earlier OCR stages have to finish before this one can run.'], 422);
        }

        return response()->json(['data' => [
            'status' => $file->refresh()->ocr_status?->value,
            'result' => $result,
            'stages' => $pipeline->describe($file),
        ]]);
    }
}
