<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VerifiedStatus;
use App\Http\Resources\ArtistResource;
use App\Models\Artist;
use Illuminate\Http\JsonResponse;

class ArtistUnverifyController
{
    public function __invoke(Artist $artist): JsonResponse
    {
        $artist->verified_status = VerifiedStatus::Unverified->value;
        $artist->verified_by_user_id = null;
        $artist->verified_at = null;
        $artist->save();

        $artist->load(['variants', 'verifiedBy']);

        return response()->json(['data' => new ArtistResource($artist)]);
    }
}
