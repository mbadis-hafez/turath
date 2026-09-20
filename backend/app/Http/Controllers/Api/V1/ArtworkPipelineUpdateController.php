<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artwork;
use App\Models\ArtworkPipelineStage;
use App\Models\PipelineNoteSuggestion;
use App\Support\Curation\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArtworkPipelineUpdateController
{
    public function __invoke(Request $request, Artwork $artwork, string $stageKey): JsonResponse
    {
        abort_unless(in_array($stageKey, ArtworkPipelineStage::KEYS, true), 404);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(ArtworkPipelineStage::STATUSES)],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'linked_file_id' => ['sometimes', 'nullable', 'integer', 'exists:files,id'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);

        // D90: only editors advance stages; a contributor can only suggest a
        // status_research note (stand-in for F7's proposal flow).
        if (! $request->user()->can('artworks.manage')) {
            abort_unless($stageKey === 'status_research' && array_keys(array_diff_key($data, ['edit_summary' => 1])) === ['note'] && ! blank($data['note']), 403);

            $suggestion = PipelineNoteSuggestion::create([
                'artwork_id' => $artwork->id,
                'stage_key' => $stageKey,
                'note' => $data['note'],
                'submitted_by_user_id' => $request->user()->id,
            ]);

            return response()->json(['data' => ['suggestion_id' => $suggestion->id, 'status' => 'pending']], 202);
        }

        $stage = (new PipelineService)->update($artwork, $stageKey, $data);

        return response()->json(['data' => ['stage_key' => $stage->stage_key, 'status' => $stage->status, 'note' => $stage->note]]);
    }
}
