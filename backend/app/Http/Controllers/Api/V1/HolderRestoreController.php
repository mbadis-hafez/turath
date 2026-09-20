<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\HolderResource;
use App\Models\Holder;
use Illuminate\Http\JsonResponse;

class HolderRestoreController
{
    public function __invoke(string $id): JsonResponse
    {
        $holder = Holder::withTrashed()->findOrFail($id);

        $holder->restore();

        return response()->json(['data' => new HolderResource($holder)]);
    }
}
