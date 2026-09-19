<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artist\ArtistIndexRequest;
use App\Http\Resources\ArtistListResource;
use App\Models\Artist;
use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ArtistIndexController
{
    public function __invoke(ArtistIndexRequest $request): JsonResponse
    {
        $query = Artist::query();

        // Managers may opt into drafts/hidden with status=all; trashed rows
        // are never listed.
        $canManage = $request->user()?->can('artists.manage') ?? false;

        if (! ($request->input('status') === 'all' && $canManage)) {
            $query->where('publication_status', 'published');
        }

        if ($status = $request->input('verified_status')) {
            $query->where('verified_status', $status);
        }

        if ($status = $request->input('living_status')) {
            $query->where('living_status', $status);
        }

        if ($q = $request->input('q')) {
            $this->applySearch($query, $q);
        }

        $this->applySort($query, $request->input('sort', 'name_ar'));

        $perPage = (int) $request->input('per_page', 24);

        return ArtistListResource::collection($query->paginate($perPage))->response();
    }

    private function applySearch(Builder $query, string $q): void
    {
        $normalized = ArabicNormalizer::normalize(mb_substr($q, 0, 100));
        $compact = ArabicNormalizer::compact($normalized);

        $tokens = array_values(array_filter(preg_split('/\s+/u', $normalized) ?: []));

        if ($tokens === []) {
            return;
        }

        $query->where(function ($or) use ($tokens, $compact) {
            $or->where(function ($and) use ($tokens) {
                foreach ($tokens as $token) {
                    $and->where('search_text', 'like', '%'.self::escapeLike($token).'%');
                }
            });

            if ($compact !== '') {
                $or->orWhere('search_compact', 'like', '%'.self::escapeLike($compact).'%');
            }
        });
    }

    private function applySort(Builder $query, string $sort): void
    {
        [$column, $direction] = match ($sort) {
            '-name_ar' => ['COALESCE(name_ar, name_en)', 'desc'],
            'name_en' => ['COALESCE(name_en, name_ar)', 'asc'],
            '-name_en' => ['COALESCE(name_en, name_ar)', 'desc'],
            'created_at' => ['created_at', 'asc'],
            '-created_at' => ['created_at', 'desc'],
            default => ['COALESCE(name_ar, name_en)', 'asc'],
        };

        $query->orderByRaw("{$column} {$direction}")->orderBy('id', $direction);
    }

    /**
     * Escape LIKE wildcards; MySQL's default escape character is backslash.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
