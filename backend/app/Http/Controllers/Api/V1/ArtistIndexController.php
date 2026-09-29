<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artist\ArtistIndexRequest;
use App\Http\Resources\ArtistListResource;
use App\Models\Artist;
use App\Support\ArabicNormalizer;
use App\Support\ArtistLetter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ArtistIndexController
{
    public function __invoke(ArtistIndexRequest $request): JsonResponse
    {
        $query = $this->filtered($request)->withCount([
            'archiveItemLinks as materials_count' => fn (Builder $q) => $q->whereHas(
                'archiveItem',
                fn (Builder $i) => $i->where('publication_status', 'published'),
            ),
        ]);

        $this->applySort($query, $request->input('sort', 'name_ar'));

        $perPage = (int) $request->input('per_page', 24);
        $paginated = $query->paginate($perPage);
        $response = ArtistListResource::collection($paginated)->response();

        if (! $request->boolean('include_facets')) {
            return $response;
        }

        $payload = $response->getData(true);
        $payload['meta'] = [...$payload['meta'], ...$this->meta($request)];

        return response()->json($payload);
    }

    /**
     * Base filtered query, optionally excluding one facet's own filter.
     *
     * @return Builder<Artist>
     */
    private function filtered(ArtistIndexRequest $request, ?string $except = null): Builder
    {
        $query = Artist::query();
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

        if ($except !== 'city' && ($city = $request->input('city'))) {
            $query->where(fn (Builder $q) => $q->where('birth_place_ar', $city)->orWhere('birth_place_en', $city));
        }

        if ($except !== 'theme_id' && ($themeId = $request->input('theme_id'))) {
            $query->whereHas('themes', fn (Builder $q) => $q->where('themes.id', $themeId));
        }

        if ($except !== 'item_type' && ($itemType = $request->input('item_type'))) {
            $query->whereHas(
                'archiveItemLinks.archiveItem',
                fn (Builder $q) => $q->where('publication_status', 'published')->where('item_type', $itemType),
            );
        }

        if ($q = $request->input('q')) {
            $this->applySearch($query, $q);
        }

        return $query;
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
            '-materials_count' => ['materials_count', 'desc'],
            default => ['COALESCE(name_ar, name_en)', 'asc'],
        };

        $query->orderByRaw("{$column} {$direction}")->orderBy('id', $direction);
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(ArtistIndexRequest $request): array
    {
        return [
            'facets' => $this->facets($request),
            'letters' => $this->letters($request),
            'materials_total' => $this->materialsTotal($request),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function facets(ArtistIndexRequest $request): array
    {
        $ids = fn (?string $except) => $this->filtered($request, $except)->select('artists.id');

        $cityExpression = 'COALESCE(birth_place_ar, birth_place_en)';

        $themes = DB::table('theme_taggables')
            ->join('themes', 'themes.id', '=', 'theme_taggables.theme_id')
            ->where('taggable_type', Artist::class)
            ->whereIn('taggable_id', $ids('theme_id'))
            ->groupBy('themes.id', 'themes.label_ar', 'themes.label_en')
            ->selectRaw('themes.id as id, themes.label_ar, themes.label_en, COUNT(*) as count')
            ->orderByDesc('count')
            ->get();

        $itemTypes = DB::table('archive_item_links')
            ->join('archive_items', 'archive_items.id', '=', 'archive_item_links.archive_item_id')
            ->where('archive_item_links.linkable_type', Artist::class)
            ->whereIn('archive_item_links.linkable_id', $ids('item_type'))
            ->where('archive_items.publication_status', 'published')
            ->whereNotNull('archive_items.item_type')
            ->selectRaw('archive_items.item_type as value, COUNT(DISTINCT archive_item_links.linkable_id) as count')
            ->groupBy('archive_items.item_type')
            ->orderByDesc('count')
            ->get();

        return [
            'city' => $this->filtered($request, 'city')
                ->reorder()
                ->toBase()
                ->selectRaw("{$cityExpression} as value, COUNT(*) as count")
                ->whereRaw("{$cityExpression} IS NOT NULL")
                ->groupBy('value')
                ->orderByDesc('count')
                ->limit(12)
                ->get()
                ->map(fn (object $r) => ['value' => $r->value, 'count' => (int) $r->count])
                ->all(),
            'theme_id' => $themes->map(fn ($r) => [
                'value' => (int) $r->id,
                'label' => ['ar' => $r->label_ar, 'en' => $r->label_en],
                'count' => (int) $r->count,
            ])->all(),
            'item_type' => $itemTypes->map(fn ($r) => [
                'value' => $r->value,
                'count' => (int) $r->count,
            ])->all(),
        ];
    }

    /**
     * @return array{ar: array<int, string>, en: array<int, string>}
     */
    private function letters(ArtistIndexRequest $request): array
    {
        $rows = $this->filtered($request)->reorder()->select('name_ar', 'name_en')->get();

        $ar = [];
        $en = [];

        foreach ($rows as $row) {
            if ($letter = ArtistLetter::of($row->name_ar, 'ar')) {
                $ar[$letter] = true;
            }
            if ($letter = ArtistLetter::of($row->name_en, 'en')) {
                $en[$letter] = true;
            }
        }

        $arLetters = array_keys($ar);
        $enLetters = array_keys($en);
        sort($enLetters);

        return [
            'ar' => $arLetters,
            'en' => $enLetters,
        ];
    }

    private function materialsTotal(ArtistIndexRequest $request): int
    {
        // At the current scale of a few hundred artists, summing in-memory
        // over the filtered result set is acceptable and avoids fragile
        // subquery aggregation over a virtual withCount column.
        return (int) $this->filtered($request)
            ->clone()
            ->withCount([
                'archiveItemLinks as materials_count' => fn (Builder $q) => $q->whereHas(
                    'archiveItem',
                    fn (Builder $i) => $i->where('publication_status', 'published'),
                ),
            ])
            ->get()
            ->sum('materials_count');
    }

    /**
     * Escape LIKE wildcards; MySQL's default escape character is backslash.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
