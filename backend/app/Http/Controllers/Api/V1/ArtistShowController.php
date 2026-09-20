<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArtistResource;
use App\Models\Artist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ArtistShowController
{
    public function __invoke(string $slug): JsonResponse|RedirectResponse
    {
        $artist = Artist::with(['variants', 'verifiedBy'])->withTrashed()->where('slug', $slug)->first();

        if ($artist !== null && $artist->merged_into_id !== null) {
            $target = Artist::find($artist->merged_into_id);
            if ($target !== null) {
                return redirect("/api/v1/artists/{$target->slug}", 301);
            }
        }

        if ($artist === null || ! Gate::forUser(auth()->user())->allows('view', $artist)) {
            abort(404);
        }

        return response()->json(['data' => new ArtistResource($artist)]);
    }
}
