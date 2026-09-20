<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ArchiveItem\ArchiveItemIndexRequest;
use App\Http\Resources\ArchiveItemResource;
use App\Models\ArchiveItem;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ArtistArchiveItemsController
{
    public function __invoke(ArchiveItemIndexRequest $request, Artist $artist): JsonResponse
    {
        $canManage = $request->user()?->can('archive.manage') ?? false;

        $query = ArchiveItem::query()
            ->with(['links.linkable', 'files'])
            ->whereHas('links', fn (Builder $q) => $q->where('linkable_type', Artist::class)->where('linkable_id', $artist->id));

        if (! ($request->input('status') === 'all' && $canManage)) {
            $query->where('publication_status', 'published');
        }

        $query->orderByDesc('created_at')->orderByDesc('id');

        $perPage = (int) $request->input('per_page', 24);

        return ArchiveItemResource::collection($query->paginate($perPage))->response();
    }
}
