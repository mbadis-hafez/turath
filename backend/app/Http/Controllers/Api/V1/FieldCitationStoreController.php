<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Completeness\StoreFieldCitationRequest;
use App\Models\FieldCitation;
use App\Models\Source;
use App\Support\Completeness\CitableTypeResolver;
use App\Support\Completeness\CompletenessCalculator;
use App\Support\Completeness\ConflictDetector;
use App\Support\Completeness\DashboardStatsCalculator;
use Illuminate\Http\JsonResponse;

class FieldCitationStoreController
{
    public function __invoke(StoreFieldCitationRequest $request, string $type, int $id): JsonResponse
    {
        $entry = CitableTypeResolver::forSegment($type);
        abort_if($entry === null, 404);
        abort_unless($request->user()?->can($entry['manage_permission']) ?? false, 403);

        $record = $entry['model']::query()->find($id);
        abort_if($record === null, 404);

        $sourceId = $request->input('source_id');

        if ($sourceId === null) {
            $newSource = $request->input('new_source');
            $source = Source::create([
                'source_type' => $newSource['source_type'],
                'title_ar' => $newSource['title_ar'] ?? null,
                'title_en' => $newSource['title_en'] ?? null,
                'publisher_or_outlet' => $newSource['publisher_or_outlet'] ?? null,
                'reference_note' => $newSource['reference_note'] ?? null,
                'url' => $newSource['url'] ?? null,
                'year' => $newSource['year'] ?? null,
                'added_by_user_id' => $request->user()->id,
            ]);
            $sourceId = $source->id;
        }

        $citation = FieldCitation::create([
            'citable_type' => $entry['model'],
            'citable_id' => $id,
            'field_key' => $request->input('field_key'),
            'source_id' => $sourceId,
            'claimed_value' => $request->input('claimed_value'),
            'created_by_user_id' => $request->user()->id,
        ]);

        (new ConflictDetector)->check($entry['model'], $id, $citation->field_key);
        (new CompletenessCalculator)->recompute($record);
        if ($record->getAttribute('created_by_user_id') !== null) {
            (new DashboardStatsCalculator)->recompute($record->getAttribute('created_by_user_id'));
        }

        return response()->json(['data' => [
            'id' => $citation->id,
            'field_key' => $citation->field_key,
            'source_id' => $citation->source_id,
            'claimed_value' => $citation->claimed_value,
            'is_primary' => $citation->is_primary,
        ]], 201);
    }
}
