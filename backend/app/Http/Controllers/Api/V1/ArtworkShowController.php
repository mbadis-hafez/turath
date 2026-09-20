<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArtworkResource;
use App\Models\Artwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ArtworkShowController
{
    public function __invoke(int $artwork): JsonResponse
    {
        $model = Artwork::with(['artist', 'holder'])->withTrashed()->find($artwork);

        if ($model === null || ! Gate::forUser(auth()->user())->allows('view', $model)) {
            abort(404);
        }

        return response()->json(['data' => new ArtworkResource($model)]);
    }
}
