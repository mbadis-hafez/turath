<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Holder;
use Illuminate\Http\Response;

class HolderDestroyController
{
    public function __invoke(Holder $holder): Response
    {
        $holder->delete();

        return response()->noContent();
    }
}
