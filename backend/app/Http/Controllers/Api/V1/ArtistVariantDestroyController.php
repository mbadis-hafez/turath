<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artist;
use App\Models\ArtistNameVariant;
use Illuminate\Http\Response;

class ArtistVariantDestroyController
{
    public function __invoke(Artist $artist, ArtistNameVariant $variant): Response
    {
        abort_unless($variant->artist_id === $artist->id, 404);

        $variant->delete();

        return response()->noContent();
    }
}
