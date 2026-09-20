<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Holder\UpdateHolderRequest;
use App\Http\Resources\HolderResource;
use App\Models\Holder;
use Illuminate\Http\JsonResponse;

class HolderUpdateController
{
    public function __invoke(UpdateHolderRequest $request, Holder $holder): JsonResponse
    {
        $holder->fill($request->mappedAttributes());
        $holder->save();

        return response()->json(['data' => new HolderResource($holder)]);
    }
}
