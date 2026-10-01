<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExtractedFieldStatus;
use App\Models\ArchiveItem;
use App\Models\FileExtractedDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A reviewer confirming or rejecting a date the extractor found — its value,
 * calendar and role together. Confirming records that a person checked it
 * against the source; it writes no record, and never converts the date to
 * another calendar.
 */
class ArchiveItemFileOcrDateController
{
    public function accept(Request $request, ArchiveItem $archiveItem, FileExtractedDate $date): JsonResponse
    {
        return $this->decide($request, $archiveItem, $date, ExtractedFieldStatus::Accepted);
    }

    public function reject(Request $request, ArchiveItem $archiveItem, FileExtractedDate $date): JsonResponse
    {
        return $this->decide($request, $archiveItem, $date, ExtractedFieldStatus::Rejected);
    }

    private function decide(Request $request, ArchiveItem $archiveItem, FileExtractedDate $date, ExtractedFieldStatus $status): JsonResponse
    {
        abort_unless($date->file->archive_item_id === $archiveItem->id, 404);

        $date->update(['status' => $status, 'reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => now()]);

        return response()->json(['data' => self::present($date)]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(FileExtractedDate $date): array
    {
        return [
            'id' => $date->id,
            'value' => $date->value,
            'normalized' => $date->normalized,
            'calendar' => $date->calendar->value,
            'date_type' => $date->date_type->value,
            'field_key' => $date->field_key,
            'source_page' => $date->source_page,
            'source_method' => $date->source_method,
            'region_id' => $date->region_id,
            'context' => $date->context,
            'confidence' => $date->confidence,
            'status' => $date->status->value,
            'reviewed_at' => $date->reviewed_at?->toIso8601String(),
        ];
    }
}
