<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artist\VerifyArtistRequest;
use App\Http\Resources\ArtistResource;
use App\Models\Artist;
use App\Support\Curation\ArtistCurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ArtistVerifyController
{
    public function __invoke(VerifyArtistRequest $request, Artist $artist): JsonResponse
    {
        if ($request->input('status') === 'verified') {
            $errors = (new ArtistCurationService)->verifyErrors($artist);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        $artist->verified_status = $request->input('status');
        $artist->verified_by_user_id = $request->user()->id;
        $artist->verified_at = now();
        $artist->save();

        $artist->load(['variants', 'verifiedBy']);

        return response()->json(['data' => new ArtistResource($artist)]);
    }
}
