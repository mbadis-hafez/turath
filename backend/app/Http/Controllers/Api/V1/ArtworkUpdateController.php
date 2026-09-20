<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artwork\UpdateArtworkRequest;
use App\Http\Resources\ArtworkResource;
use App\Models\Artwork;
use App\Support\Completeness\PublishGate;
use Illuminate\Http\JsonResponse;

class ArtworkUpdateController
{
    public function __invoke(UpdateArtworkRequest $request, Artwork $artwork): JsonResponse
    {
        $artwork->fill($request->mappedAttributes());

        if ($artwork->isDirty('publication_status') && $artwork->publication_status === 'published') {
            PublishGate::assertPublishable($artwork);
        }

        $artwork->save();
        $artwork->load(['artist', 'holder']);

        return response()->json(['data' => new ArtworkResource($artwork)]);
    }
}
