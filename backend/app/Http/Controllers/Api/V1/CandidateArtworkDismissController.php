<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CandidateArtwork;
use Illuminate\Http\JsonResponse;

class CandidateArtworkDismissController
{
    public function __invoke(CandidateArtwork $candidate): JsonResponse
    {
        abort_unless($candidate->status === 'pending', 422);
        $candidate->update(['status' => 'dismissed']);

        return response()->json(['data' => ['id' => $candidate->id, 'status' => 'dismissed']]);
    }
}
