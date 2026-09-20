<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ArchiveItem\UpdateArchiveItemRequest;
use App\Http\Resources\ArchiveItemResource;
use App\Models\ArchiveItem;
use Illuminate\Http\JsonResponse;

class ArchiveItemUpdateController
{
    public function __invoke(UpdateArchiveItemRequest $request, ArchiveItem $archiveItem): JsonResponse
    {
        $archiveItem->fill($request->mappedAttributes());
        $archiveItem->save();
        $archiveItem->load(['links.linkable', 'files']);

        return response()->json(['data' => new ArchiveItemResource($archiveItem)]);
    }
}
