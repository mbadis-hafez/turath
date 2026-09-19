<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArtistResource;
use App\Models\Artist;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ArtistShowController
{
    public function __invoke(string $slug): JsonResponse
    {
        $artist = Artist::with(['variants', 'verifiedBy'])->withTrashed()->where('slug', $slug)->first();

        if ($artist === null || ! Gate::forUser(auth()->user())->allows('view', $artist)) {
            abort(404);
        }

        return response()->json(['data' => new ArtistResource($artist)]);
    }
}
