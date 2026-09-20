<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ArchiveItem\ArchiveItemIndexRequest;
use App\Http\Resources\ArchiveItemResource;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ArchiveItemIndexController
{
    public function __invoke(ArchiveItemIndexRequest $request): JsonResponse
    {
        $query = ArchiveItem::query()->with(['links.linkable', 'files']);

        $canManage = $request->user()?->can('archive.manage') ?? false;

        if (! ($request->input('status') === 'all' && $canManage)) {
            $query->where('publication_status', 'published');
        }

        if ($itemType = $request->input('item_type')) {
            $query->where('item_type', $itemType);
        }

        if ($artistId = $request->input('artist_id')) {
            $this->whereLinkedTo($query, Artist::class, $artistId);
        }

        if ($artworkId = $request->input('artwork_id')) {
            $this->whereLinkedTo($query, Artwork::class, $artworkId);
        }

        if ($yearFrom = $request->input('year_from')) {
            $query->where(fn (Builder $q) => $q->whereNull('content_year_to')->orWhere('content_year_to', '>=', $yearFrom));
        }

        if ($yearTo = $request->input('year_to')) {
            $query->where(fn (Builder $q) => $q->whereNull('content_year_from')->orWhere('content_year_from', '<=', $yearTo));
        }

        if ($q = $request->input('q')) {
            $this->applySearch($query, $q);
        }

        $this->applySort($query, $request->input('sort', '-created_at'));

        $perPage = (int) $request->input('per_page', 24);

        return ArchiveItemResource::collection($query->paginate($perPage))->response();
    }

    private function whereLinkedTo(Builder $query, string $linkableType, int $id): void
    {
        $query->whereHas('links', fn (Builder $q) => $q->where('linkable_type', $linkableType)->where('linkable_id', $id));
    }

    private function applySearch(Builder $query, string $q): void
    {
        $normalized = ArabicNormalizer::normalize(mb_substr($q, 0, 100));
        $tokens = array_values(array_filter(preg_split('/\s+/u', $normalized) ?: []));

        if ($tokens === []) {
            return;
        }

        $query->where(function ($and) use ($tokens) {
            foreach ($tokens as $token) {
                $and->where('search_text', 'like', '%'.self::escapeLike($token).'%');
            }
        });
    }

    private function applySort(Builder $query, string $sort): void
    {
        [$column, $direction] = match ($sort) {
            'content_year_from' => ['content_year_from', 'asc'],
            '-content_year_from' => ['content_year_from', 'desc'],
            'created_at' => ['created_at', 'asc'],
            default => ['created_at', 'desc'],
        };

        $query->orderBy($column, $direction)->orderBy('id', $direction);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
