<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FieldCitation;
use App\Models\RecordCompleteness;
use App\Models\Source;
use App\Models\SourceConflict;
use App\Support\Completeness\CitableTypeResolver;
use App\Support\Completeness\CompletenessCalculator;
use Illuminate\Http\JsonResponse;

class RecordCompletenessController
{
    public function __invoke(string $type, int $id): JsonResponse
    {
        $entry = CitableTypeResolver::forSegment($type);
        abort_if($entry === null, 404);

        $record = $entry['model']::query()->find($id);
        abort_if($record === null, 404);

        $rules = (new CompletenessCalculator)->rulesFor($entry['model']);

        $completeness = RecordCompleteness::query()
            ->where('citable_type', $entry['model'])
            ->where('citable_id', $id)
            ->first();

        $labelFields = fn (array $keys) => collect($keys)
            ->map(fn (string $key) => ['field_key' => $key, 'label' => $rules->fieldLabel($key)])
            ->values()->all();

        $citations = FieldCitation::query()
            ->with('source')
            ->where('citable_type', $entry['model'])
            ->where('citable_id', $id)
            ->get()
            ->map(fn (FieldCitation $c) => [
                'id' => $c->id,
                'field_key' => $c->field_key,
                'claimed_value' => $c->claimed_value,
                'is_primary' => $c->is_primary,
                'source' => $this->sourceShape($c->source),
                'created_at' => $c->created_at->toIso8601String(),
            ]);

        $openConflicts = SourceConflict::query()
            ->where('citable_type', $entry['model'])
            ->where('citable_id', $id)
            ->where('status', 'open')
            ->get()
            ->map(fn (SourceConflict $conflict) => [
                'id' => $conflict->id,
                'field_key' => $conflict->field_key,
                'label' => $rules->fieldLabel($conflict->field_key),
                'citations' => $citations->whereIn('id', $conflict->citation_ids)->values(),
            ]);

        return response()->json(['data' => [
            'citable_type' => $type,
            'citable_id' => $id,
            'completeness_pct' => $completeness->completeness_pct ?? 0,
            'severity' => $completeness->severity ?? 'blocking',
            'blocking_gaps' => $labelFields($completeness->blocking_gap_field_keys ?? []),
            'minor_gaps' => $labelFields($completeness->minor_gap_field_keys ?? []),
            'open_conflicts' => $openConflicts,
            'citations' => $citations->values(),
        ]]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sourceShape(?Source $source): ?array
    {
        if ($source === null) {
            return null;
        }

        return [
            'id' => $source->id,
            'source_type' => $source->source_type,
            'title' => ['ar' => $source->title_ar, 'en' => $source->title_en],
            'publisher_or_outlet' => $source->publisher_or_outlet,
            'reference_note' => $source->reference_note,
            'url' => $source->url,
            'year' => $source->year,
        ];
    }
}
