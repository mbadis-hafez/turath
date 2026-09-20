<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArchiveItemResource;
use App\Models\ArchiveItem;
use Illuminate\Http\JsonResponse;

class ArchiveItemRestoreController
{
    public function __invoke(string $id): JsonResponse
    {
        $item = ArchiveItem::withTrashed()->findOrFail($id);

        $item->restore();
        $item->load(['links.linkable', 'files']);

        return response()->json(['data' => new ArchiveItemResource($item)]);
    }
}
