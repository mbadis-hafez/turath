<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artist\VerifyArtistRequest;
use App\Http\Resources\ArtistResource;
use App\Models\Artist;
use Illuminate\Http\JsonResponse;

class ArtistVerifyController
{
    public function __invoke(VerifyArtistRequest $request, Artist $artist): JsonResponse
    {
        $artist->verified_status = $request->input('status');
        $artist->verified_by_user_id = $request->user()->id;
        $artist->verified_at = now();
        $artist->save();

        $artist->load(['variants', 'verifiedBy']);

        return response()->json(['data' => new ArtistResource($artist)]);
    }
}
