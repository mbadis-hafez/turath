<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArtistResource;
use App\Models\Artist;
use Illuminate\Http\JsonResponse;

class ArtistRestoreController
{
    public function __invoke(string $id): JsonResponse
    {
        $artist = Artist::withTrashed()->findOrFail($id);

        $artist->restore();
        $artist->load('variants');

        return response()->json(['data' => new ArtistResource($artist)]);
    }
}
