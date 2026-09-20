<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CandidateArtwork;
use Illuminate\Http\JsonResponse;

class CandidateArtworkIndexController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => CandidateArtwork::where('status', 'pending')->latest('created_at')->get()->map(fn ($c) => [
            'id' => $c->id,
            'source_type' => $c->source_type,
            'source_archive_item_id' => $c->source_archive_item_id,
            'source_import_batch_row_id' => $c->source_import_batch_row_id,
            'suggested_title' => ['ar' => $c->suggested_title_ar, 'en' => $c->suggested_title_en],
            'suggested_artist_id' => $c->suggested_artist_id,
            'status' => $c->status,
        ])->values()]);
    }
}
