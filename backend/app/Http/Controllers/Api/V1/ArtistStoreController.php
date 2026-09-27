<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artist\StoreArtistRequest;
use App\Http\Resources\ArtistResource;
use App\Models\Artist;
use App\Support\Proposals\CreationReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ArtistStoreController
{
    public function __invoke(StoreArtistRequest $request): JsonResponse
    {
        $artist = DB::transaction(function () use ($request) {
            // A brand-new record can never be created already-published — its
            // creation hasn't been reviewed yet, and creation_approved_at is
            // never set at this point by definition (005). Publishing is a
            // separate, later action, gated by CreationReviewGate.
            $attributes = [...$request->mappedAttributes(), 'publication_status' => 'draft'];
            $artist = Artist::create($attributes);
            (new CreationReviewService)->startFor($artist, $request->user());

            return $artist;
        });
        $artist->refresh();
        $artist->load('variants');

        return response()->json(['data' => new ArtistResource($artist)], 201);
    }
}
