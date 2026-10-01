<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentType;
use App\Enums\ExtractedFieldStatus;
use App\Enums\ExtractionMethod;
use App\Enums\OcrStage;
use App\Models\ArchiveItem;
use App\Models\FileExtractedField;
use App\Models\FileOcrRegion;
use App\Support\Ocr\ExtractedFieldPayloadMapper;
use App\Support\Ocr\Extraction\DocumentFieldSchema;
use App\Support\Ocr\Extraction\FieldDefinition;
use App\Support\Ocr\OcrReviewPresenter;
use App\Support\Ocr\Pipeline\OcrPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Lines the pipeline set aside for a person (OcrReviewPresenter::setAside):
 * a possible strikethrough, handwriting with no label beside it. Nothing here
 * picks a value — the reviewer reads the crop, chooses which field the line
 * fills and types what it says. The result is a field like any other: edited,
 * awaiting acceptance, with the line's own OCR reading kept beside it.
 */
class ArchiveItemFileOcrRegionReviewController
{
    /** POST .../file/ocr/regions/{region}/transcribe — {field_key, value}. */
    public function transcribe(Request $request, ArchiveItem $archiveItem, FileOcrRegion $region, OcrPipeline $pipeline): JsonResponse
    {
        $this->assertReviewable($archiveItem, $region);
        $data = $request->validate([
            'field_key' => ['required', 'string', 'max:100'],
            'value' => ['required', 'string', 'max:5000'],
        ]);
        $file = $region->file;
        $type = $file->document_type ?? DocumentType::Unknown;
        $value = trim($data['value']);

        $definition = DocumentFieldSchema::field($type, $data['field_key']);
        $isRecordField = ExtractedFieldPayloadMapper::toPayload($data['field_key'], '') !== null;
        if ($definition === null && ! $isRecordField) {
            throw ValidationException::withMessages(['field_key' => ['That is not a field of this document.']]);
        }
        if ($definition !== null && $definition->route === FieldDefinition::ROUTE_ARTIST_CONTACT) {
            throw ValidationException::withMessages(['field_key' => ['Contact details are entered in the artist contact panel, which proposes them for approval.']]);
        }
        if ($definition !== null && $definition->kind === FieldDefinition::KIND_DATE) {
            throw ValidationException::withMessages(['field_key' => ['Dates are reviewed in the dates list.']]);
        }
        if ($value === '') {
            throw ValidationException::withMessages(['value' => ['Type what the line says.']]);
        }

        $documentType = $definition !== null ? $type : null;
        $existing = $file->extractedFields()->where('field_key', $data['field_key'])->where('document_type', $documentType?->value)->get();
        // A single-value field that already has a reading is edited there, not given a second one.
        if (($definition === null || ! $definition->isMultiple()) && $existing->isNotEmpty()) {
            throw ValidationException::withMessages(['field_key' => ['This field already has a value. Edit that value instead.']]);
        }

        $field = FileExtractedField::create([
            'file_id' => $file->id,
            'field_key' => $data['field_key'],
            'document_type' => $documentType?->value,
            'ordinal' => $existing->reduce(fn (int $next, FileExtractedField $f) => max($next, $f->ordinal + 1), 0),
            // Nothing machine-read fills this field: the value is the reviewer's, and the line's own
            // (untrusted) OCR reading is kept beside it.
            'extracted_value' => null,
            'verified_value' => $value,
            'original_ocr_text' => $region->source_text,
            'crop_path' => $region->crop_path,
            // No machine confidence to report; 0 also keeps it out of "accept all high-confidence".
            'confidence' => 0,
            'source_page' => $region->page_number,
            'region_id' => $region->id,
            'extraction_method' => ExtractionMethod::ManuallyTranscribed->value,
            'rule' => OcrReviewPresenter::REVIEWER_RULE,
            'status' => ExtractedFieldStatus::Edited->value,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // A name or title typed here gets candidate records like any other.
        $pipeline->start($file, OcrStage::MatchEntities);

        return response()->json(['data' => ArchiveItemFileOcrFieldController::present($field)], 201);
    }

    /** POST .../file/ocr/regions/{region}/dismiss — looked at it; nothing to extract. */
    public function dismiss(Request $request, ArchiveItem $archiveItem, FileOcrRegion $region): JsonResponse
    {
        $this->assertReviewable($archiveItem, $region);
        $region->update(['review_dismissed_by_user_id' => $request->user()->id, 'review_dismissed_at' => now()]);

        return response()->json(['data' => ['id' => $region->id, 'dismissed' => true]]);
    }

    /** POST .../file/ocr/regions/{region}/restore — undo a dismissal. */
    public function restore(ArchiveItem $archiveItem, FileOcrRegion $region): JsonResponse
    {
        $this->assertReviewable($archiveItem, $region);
        $region->update(['review_dismissed_by_user_id' => null, 'review_dismissed_at' => null]);

        return response()->json(['data' => ['id' => $region->id, 'dismissed' => false]]);
    }

    private function assertReviewable(ArchiveItem $archiveItem, FileOcrRegion $region): void
    {
        abort_unless($region->file->archive_item_id === $archiveItem->id, 404);
        if (! $region->requires_human_review || ! in_array($region->region_type, OcrReviewPresenter::TRANSCRIBABLE, true)) {
            throw ValidationException::withMessages(['region' => ['Only a line set aside for review can be transcribed or dismissed here.']]);
        }
    }
}
