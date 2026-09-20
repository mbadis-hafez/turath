<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artist\UpdateArtistRequest;
use App\Http\Resources\ArtistResource;
use App\Models\Artist;
use App\Support\Completeness\PublishGate;
use Illuminate\Http\JsonResponse;

class ArtistUpdateController
{
    public function __invoke(UpdateArtistRequest $request, Artist $artist): JsonResponse
    {
        $artist->fill($request->mappedAttributes());

        if ($artist->isDirty('publication_status') && $artist->publication_status === 'published') {
            PublishGate::assertPublishable($artist);
        }

        $artist->save();
        $artist->load('variants');

        return response()->json(['data' => new ArtistResource($artist)]);
    }
}
