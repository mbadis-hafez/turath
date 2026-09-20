<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artwork\UpdateArtworkRequest;
use App\Http\Resources\ArtworkResource;
use App\Models\Artwork;
use Illuminate\Http\JsonResponse;

class ArtworkUpdateController
{
    public function __invoke(UpdateArtworkRequest $request, Artwork $artwork): JsonResponse
    {
        $artwork->fill($request->mappedAttributes());
        $artwork->save();
        $artwork->load(['artist', 'holder']);

        return response()->json(['data' => new ArtworkResource($artwork)]);
    }
}
