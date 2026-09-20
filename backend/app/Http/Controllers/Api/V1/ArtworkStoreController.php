<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artwork\StoreArtworkRequest;
use App\Http\Resources\ArtworkResource;
use App\Models\Artwork;
use Illuminate\Http\JsonResponse;

class ArtworkStoreController
{
    public function __invoke(StoreArtworkRequest $request): JsonResponse
    {
        $artwork = Artwork::create($request->mappedAttributes());
        $artwork->refresh();
        $artwork->load(['artist', 'holder']);

        return response()->json(['data' => new ArtworkResource($artwork)], 201);
    }
}
