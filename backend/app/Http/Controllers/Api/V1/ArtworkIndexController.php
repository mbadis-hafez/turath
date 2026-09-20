<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artwork\ArtworkIndexRequest;
use App\Http\Resources\ArtworkListResource;
use App\Models\Artwork;
use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ArtworkIndexController
{
    public function __invoke(ArtworkIndexRequest $request): JsonResponse
    {
        $query = Artwork::query()->with(['artist', 'holder']);

        $canManage = $request->user()?->can('artworks.manage') ?? false;

        if (! ($request->input('status') === 'all' && $canManage)) {
            $query->where('publication_status', 'published');
        }

        if ($artistId = $request->input('artist_id')) {
            $query->where('artist_id', $artistId);
        }

        if ($slug = $request->input('artist_slug')) {
            $query->whereHas('artist', fn (Builder $q) => $q->where('slug', $slug));
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($holderId = $request->input('holder_id')) {
            $query->where('holder_id', $holderId);
        }

        if ($yearFrom = $request->input('year_from')) {
            $query->where(fn (Builder $q) => $q->whereNull('creation_year_to')->orWhere('creation_year_to', '>=', $yearFrom));
        }

        if ($yearTo = $request->input('year_to')) {
            $query->where(fn (Builder $q) => $q->whereNull('creation_year_from')->orWhere('creation_year_from', '<=', $yearTo));
        }

        if ($certainty = $request->input('attribution_certainty')) {
            $query->where('attribution_certainty', $certainty);
        }

        if ($q = $request->input('q')) {
            $this->applySearch($query, $q);
        }

        $this->applySort($query, $request->input('sort', '-created_at'));

        $perPage = (int) $request->input('per_page', 24);

        return ArtworkListResource::collection($query->paginate($perPage))->response();
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
            'title_ar' => ['COALESCE(title_ar, title_en)', 'asc'],
            'title_en' => ['COALESCE(title_en, title_ar)', 'asc'],
            'creation_year_from' => ['creation_year_from', 'asc'],
            '-creation_year_from' => ['creation_year_from', 'desc'],
            'created_at' => ['created_at', 'asc'],
            default => ['created_at', 'desc'],
        };

        $query->orderByRaw("{$column} {$direction}")->orderBy('id', $direction);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
