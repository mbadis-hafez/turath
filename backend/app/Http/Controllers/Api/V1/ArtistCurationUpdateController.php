<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artist\UpdateArtistCurationRequest;
use App\Models\Artist;
use App\Support\Curation\ArtistSections;
use Illuminate\Http\JsonResponse;

class ArtistCurationUpdateController
{
    public function __invoke(UpdateArtistCurationRequest $request, Artist $artist): JsonResponse
    {
        (new ArtistSections)->applyCuration($artist, $request->validated(), $request->user());

        return (new ArtistCurationShowController)($artist->refresh());
    }
}
