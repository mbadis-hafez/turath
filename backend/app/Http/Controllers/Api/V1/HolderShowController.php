<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\HolderResource;
use App\Models\Holder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class HolderShowController
{
    public function __invoke(int $holder): JsonResponse
    {
        $model = Holder::withTrashed()->find($holder);

        if ($model === null || ! Gate::forUser(auth()->user())->allows('view', $model)) {
            abort(404);
        }

        return response()->json(['data' => new HolderResource($model)]);
    }
}
