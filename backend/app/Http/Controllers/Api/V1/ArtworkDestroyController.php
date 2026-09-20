<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artwork;
use Illuminate\Http\Response;

class ArtworkDestroyController
{
    public function __invoke(Artwork $artwork): Response
    {
        $artwork->delete();

        return response()->noContent();
    }
}
