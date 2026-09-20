<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artwork;
use App\Models\PipelineNoteSuggestion;
use App\Support\Curation\PipelineService;
use Illuminate\Http\JsonResponse;

class PipelineNoteSuggestionAcceptController
{
    public function __invoke(PipelineNoteSuggestion $suggestion): JsonResponse
    {
        abort_unless($suggestion->status === 'pending', 422);

        (new PipelineService)->update(Artwork::findOrFail($suggestion->artwork_id), $suggestion->stage_key, ['note' => $suggestion->note]);
        $suggestion->update(['status' => 'accepted']);

        return response()->json(['data' => ['id' => $suggestion->id, 'status' => 'accepted']]);
    }
}
