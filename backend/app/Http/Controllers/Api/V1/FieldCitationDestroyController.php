<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FieldCitation;
use App\Support\Completeness\CitableTypeResolver;
use App\Support\Completeness\CompletenessCalculator;
use App\Support\Completeness\ConflictDetector;
use App\Support\Completeness\DashboardStatsCalculator;
use Illuminate\Http\Response;

class FieldCitationDestroyController
{
    public function __invoke(string $citation): Response
    {
        $model = FieldCitation::query()->findOrFail($citation);

        $segment = CitableTypeResolver::segmentFor($model->citable_type);
        $entry = $segment !== null ? CitableTypeResolver::forSegment($segment) : null;
        abort_unless($entry !== null && (auth()->user()?->can($entry['manage_permission']) ?? false), 403);

        $citableType = $model->citable_type;
        $citableId = $model->citable_id;
        $fieldKey = $model->field_key;

        $model->delete();

        (new ConflictDetector)->check($citableType, $citableId, $fieldKey);

        $record = $entry['model']::query()->find($citableId);
        if ($record !== null) {
            (new CompletenessCalculator)->recompute($record);
            if ($record->getAttribute('created_by_user_id') !== null) {
                (new DashboardStatsCalculator)->recompute($record->getAttribute('created_by_user_id'));
            }
        }

        return response()->noContent();
    }
}
