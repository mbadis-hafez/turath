<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ArchiveItem\StoreArchiveItemLinkRequest;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ArchiveItemLinkStoreController
{
    public function __invoke(StoreArchiveItemLinkRequest $request, ArchiveItem $archiveItem): JsonResponse
    {
        $linkableClass = $request->input('linkable_type') === 'artist' ? Artist::class : Artwork::class;
        $linkableId = $request->input('linkable_id');

        if (! $linkableClass::query()->whereKey($linkableId)->exists()) {
            throw ValidationException::withMessages(['linkable_id' => ['The selected linkable_id is invalid.']]);
        }

        $alreadyLinked = $archiveItem->links()
            ->where('linkable_type', $linkableClass)
            ->where('linkable_id', $linkableId)
            ->where('role', $request->input('role'))
            ->exists();

        if ($alreadyLinked) {
            throw ValidationException::withMessages(['role' => ['This entity is already linked with this role.']]);
        }

        $link = $archiveItem->links()->create([
            'linkable_type' => $linkableClass,
            'linkable_id' => $linkableId,
            'role' => $request->input('role'),
        ]);

        return response()->json(['data' => [
            'id' => $link->id,
            'role' => $link->role,
            'linkable_type' => $request->input('linkable_type'),
            'linkable_id' => $link->linkable_id,
        ]], 201);
    }
}
