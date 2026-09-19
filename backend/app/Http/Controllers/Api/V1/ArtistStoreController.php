<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artist\StoreArtistRequest;
use App\Http\Resources\ArtistResource;
use App\Models\Artist;
use Illuminate\Http\JsonResponse;

class ArtistStoreController
{
    public function __invoke(StoreArtistRequest $request): JsonResponse
    {
        $artist = Artist::create($request->mappedAttributes());
        $artist->load('variants');

        return response()->json(['data' => new ArtistResource($artist)], 201);
    }
}
