<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artwork\ArtworkIndexRequest;
use App\Http\Resources\ArtworkListResource;
use App\Models\Artist;
use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ArtistArtworksController
{
    public function __invoke(ArtworkIndexRequest $request, Artist $artist): JsonResponse
    {
        $query = $artist->artworks()->with(['artist', 'holder']);

        $canManage = $request->user()?->can('artworks.manage') ?? false;

        if (! ($request->input('status') === 'all' && $canManage)) {
            $query->where('publication_status', 'published');
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
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
            $normalized = ArabicNormalizer::normalize(mb_substr($q, 0, 100));
            $tokens = array_values(array_filter(preg_split('/\s+/u', $normalized) ?: []));

            foreach ($tokens as $token) {
                $query->where('search_text', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $token).'%');
            }
        }

        $query->orderByDesc('created_at')->orderByDesc('id');

        $perPage = (int) $request->input('per_page', 24);

        return ArtworkListResource::collection($query->paginate($perPage))->response();
    }
}
