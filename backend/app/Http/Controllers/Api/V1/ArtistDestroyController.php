<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artist;
use Illuminate\Http\Response;

class ArtistDestroyController
{
    public function __invoke(Artist $artist): Response
    {
        $artist->delete();

        return response()->noContent();
    }
}
