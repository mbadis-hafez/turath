<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OcrStage;
use App\Models\ArchiveItem;
use App\Models\FileOcrFormField;
use App\Support\Ocr\HandwritingSuggestionService;
use App\Support\Ocr\Pipeline\OcrPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manual transcription of a form value the pipeline explicitly declined to OCR
 * (handwriting, a signature, anything unclassified). This never runs through
 * ExtractedFieldPayloadMapper or touches the archive item — a reviewer reading
 * a cropped source image and typing what it says is not the same kind of fact
 * as an OCR/AI-derived value. It does re-run extraction, so the transcription
 * becomes the value of the document field it belongs to (marked manually
 * transcribed), still to be reviewed along that field's own route.
 */
class ArchiveItemFileOcrFormFieldController
{
    public function transcribe(Request $request, ArchiveItem $archiveItem, FileOcrFormField $formField, HandwritingSuggestionService $suggestions, OcrPipeline $pipeline): JsonResponse
    {
        abort_unless($formField->file->archive_item_id === $archiveItem->id, 404);

        $data = $request->validate(['value' => ['required', 'string', 'max:2000']]);

        $formField->update([
            'manual_value' => $data['value'],
            'transcribed_by_user_id' => $request->user()->id,
            'transcribed_at' => now(),
        ]);
        // A pending machine suggestion for this crop is settled by what the reviewer actually typed.
        $suggestions->recordTranscription($formField, $data['value'], $request->user());
        $pipeline->start($formField->file, OcrStage::Extract);

        return response()->json(['data' => [
            'id' => $formField->id,
            'field_label' => $formField->field_label,
            'manual_value' => $formField->manual_value,
            'transcribed_by_user_id' => $formField->transcribed_by_user_id,
            'transcribed_at' => $formField->transcribed_at->toIso8601String(),
        ]]);
    }
}
