<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artwork;
use App\Support\Curation\PipelineService;
use Illuminate\Http\JsonResponse;

class ArtworkPipelineShowController
{
    public function __invoke(Artwork $artwork): JsonResponse
    {
        return response()->json(['data' => (new PipelineService)->stages($artwork)->map(fn ($s) => [
            'stage_key' => $s->stage_key,
            'status' => $s->status,
            'note' => $s->note,
            'linked_file_id' => $s->linked_file_id,
            'updated_by_user_id' => $s->updated_by_user_id,
            'updated_at' => $s->updated_at?->toIso8601String(),
        ])->values()]);
    }
}
