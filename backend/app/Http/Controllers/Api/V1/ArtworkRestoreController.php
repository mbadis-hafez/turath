<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArtworkResource;
use App\Models\Artwork;
use Illuminate\Http\JsonResponse;

class ArtworkRestoreController
{
    public function __invoke(string $id): JsonResponse
    {
        $artwork = Artwork::withTrashed()->findOrFail($id);

        $artwork->restore();
        $artwork->load(['artist', 'holder']);

        return response()->json(['data' => new ArtworkResource($artwork)]);
    }
}
