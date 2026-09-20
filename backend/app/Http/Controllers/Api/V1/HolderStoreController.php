<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Holder\StoreHolderRequest;
use App\Http\Resources\HolderResource;
use App\Models\Holder;
use Illuminate\Http\JsonResponse;

class HolderStoreController
{
    public function __invoke(StoreHolderRequest $request): JsonResponse
    {
        $holder = Holder::create($request->mappedAttributes());

        return response()->json(['data' => new HolderResource($holder)], 201);
    }
}
