<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ArchiveItem\StoreArchiveItemRequest;
use App\Http\Resources\ArchiveItemResource;
use App\Models\ArchiveItem;
use Illuminate\Http\JsonResponse;

class ArchiveItemStoreController
{
    public function __invoke(StoreArchiveItemRequest $request): JsonResponse
    {
        $item = ArchiveItem::create($request->mappedAttributes());
        $item->refresh();
        $item->load(['links.linkable', 'files']);

        return response()->json(['data' => new ArchiveItemResource($item)], 201);
    }
}
