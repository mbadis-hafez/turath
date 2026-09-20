<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArchiveItemResource;
use App\Models\ArchiveItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ArchiveItemShowController
{
    public function __invoke(int $archiveItem): JsonResponse
    {
        $item = ArchiveItem::with(['links.linkable', 'files'])->withTrashed()->find($archiveItem);

        if ($item === null || ! Gate::forUser(auth()->user())->allows('view', $item)) {
            abort(404);
        }

        return response()->json(['data' => new ArchiveItemResource($item)]);
    }
}
