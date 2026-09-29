<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Models\FileOcrFormField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manual transcription of a form value the pipeline explicitly declined to OCR
 * (handwriting, a signature, anything unclassified). This never runs through
 * ExtractedFieldPayloadMapper or touches the archive item — a reviewer reading
 * a cropped source image and typing what it says is not the same kind of fact
 * as an OCR/AI-derived value, and isn't wired into that apply path in this
 * slice. See DocumentType::fieldSchema() for how a future extractor would
 * eventually turn a resolved form field into a proposed record update.
 */
class ArchiveItemFileOcrFormFieldController
{
    public function transcribe(Request $request, ArchiveItem $archiveItem, FileOcrFormField $formField): JsonResponse
    {
        abort_unless($formField->file->archive_item_id === $archiveItem->id, 404);

        $data = $request->validate(['value' => ['required', 'string', 'max:2000']]);

        $formField->update([
            'manual_value' => $data['value'],
            'transcribed_by_user_id' => $request->user()->id,
            'transcribed_at' => now(),
        ]);

        return response()->json(['data' => [
            'id' => $formField->id,
            'field_label' => $formField->field_label,
            'manual_value' => $formField->manual_value,
            'transcribed_by_user_id' => $formField->transcribed_by_user_id,
            'transcribed_at' => $formField->transcribed_at->toIso8601String(),
        ]]);
    }
}
