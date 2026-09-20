<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArtworkResource;
use App\Models\Artwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ArtworkShowController
{
    public function __invoke(int $artwork): JsonResponse|RedirectResponse
    {
        $model = Artwork::with(['artist', 'holder'])->withTrashed()->find($artwork);

        if ($model !== null && $model->merged_into_id !== null) {
            return redirect("/api/v1/artworks/{$model->merged_into_id}", 301);
        }

        if ($model === null || ! Gate::forUser(auth()->user())->allows('view', $model)) {
            abort(404);
        }

        return response()->json(['data' => new ArtworkResource($model)]);
    }
}
