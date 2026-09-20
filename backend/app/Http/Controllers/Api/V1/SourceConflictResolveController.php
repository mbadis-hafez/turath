<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConflictStatus;
use App\Http\Requests\Completeness\ResolveSourceConflictRequest;
use App\Models\FieldCitation;
use App\Models\SourceConflict;
use App\Support\Completeness\CompletenessCalculator;
use App\Support\Completeness\DashboardStatsCalculator;
use Illuminate\Http\JsonResponse;

class SourceConflictResolveController
{
    public function __invoke(ResolveSourceConflictRequest $request, SourceConflict $sourceConflict): JsonResponse
    {
        $resolvedSourceId = $request->input('resolved_source_id');

        $sourceConflict->status = ConflictStatus::Resolved->value;
        $sourceConflict->resolved_source_id = $resolvedSourceId;
        $sourceConflict->resolution_note = $request->input('resolution_note');
        $sourceConflict->resolved_by_user_id = $request->user()->id;
        $sourceConflict->resolved_at = now();
        $sourceConflict->save();

        FieldCitation::query()
            ->where('citable_type', $sourceConflict->citable_type)
            ->where('citable_id', $sourceConflict->citable_id)
            ->where('field_key', $sourceConflict->field_key)
            ->update(['is_primary' => false]);

        FieldCitation::query()
            ->where('citable_type', $sourceConflict->citable_type)
            ->where('citable_id', $sourceConflict->citable_id)
            ->where('field_key', $sourceConflict->field_key)
            ->where('source_id', $resolvedSourceId)
            ->update(['is_primary' => true]);

        $record = $sourceConflict->citable_type::query()->find($sourceConflict->citable_id);
        if ($record !== null) {
            (new CompletenessCalculator)->recompute($record);
            if ($record->created_by_user_id !== null) {
                (new DashboardStatsCalculator)->recompute($record->created_by_user_id);
            }
        }

        return response()->json(['data' => [
            'id' => $sourceConflict->id,
            'status' => $sourceConflict->status,
            'resolved_source_id' => $sourceConflict->resolved_source_id,
            'resolution_note' => $sourceConflict->resolution_note,
            'resolved_at' => $sourceConflict->resolved_at?->toIso8601String(),
        ]]);
    }
}
