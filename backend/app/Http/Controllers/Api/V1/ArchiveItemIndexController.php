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
use Illuminate\Support\Facades\DB;

class ArchiveItemIndexController
{
    public function __invoke(ArchiveItemIndexRequest $request): JsonResponse
    {
        $query = $this->filtered($request)->with(['links.linkable', 'files']);
        $this->applySort($query, $request->input('sort', '-created_at'));

        $paginated = $query->paginate((int) $request->input('per_page', 24));
        $response = ArchiveItemResource::collection($paginated)->response();

        if (! $request->boolean('include_facets')) {
            return $response;
        }

        $payload = $response->getData(true);
        $payload['meta']['facets'] = $this->facets($request);

        return response()->json($payload);
    }

    /**
     * Visibility plus every active filter, optionally leaving one facet group out
     * so its own counts describe the options that are still open to it.
     *
     * @return Builder<ArchiveItem>
     */
    private function filtered(ArchiveItemIndexRequest $request, ?string $except = null): Builder
    {
        $query = ArchiveItem::query();
        $canManage = $request->user()?->can('archive.manage') ?? false;

        if (! ($request->input('status') === 'all' && $canManage)) {
            $query->where('publication_status', 'published');
        }

        if ($except !== 'item_type' && ($types = $request->input('item_type', [])) !== []) {
            $query->whereIn('item_type', $types);
        }
        if ($except !== 'place' && ($places = $request->input('place', [])) !== []) {
            $query->where(fn (Builder $q) => $q->whereIn('place_ar', $places)->orWhereIn('place_en', $places));
        }
        if ($except !== 'theme_id' && ($themes = $request->input('theme_id', [])) !== []) {
            $query->whereHas('themes', fn (Builder $q) => $q->whereIn('themes.id', $themes));
        }
        // Full = openly public; preview = published but restricted to something narrower.
        if ($except !== 'access' && ($access = $request->input('access', [])) !== [] && count($access) === 1) {
            $access[0] === 'full' ? $query->where('access_level', 'public') : $query->where('access_level', '!=', 'public');
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

        return $query;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function facets(ArchiveItemIndexRequest $request): array
    {
        $ids = fn (?string $except) => $this->filtered($request, $except)->select('archive_items.id');
        $counts = fn (string $except, string $expression) => $this->filtered($request, $except)->reorder()
            ->selectRaw("{$expression} as value, COUNT(*) as count")->groupBy('value')->orderByDesc('count')->toBase();

        $place = 'COALESCE(place_ar, place_en)';
        $themes = DB::table('theme_taggables')->join('themes', 'themes.id', '=', 'theme_taggables.theme_id')
            ->where('taggable_type', ArchiveItem::class)->whereIn('taggable_id', $ids('theme_id'))
            ->groupBy('themes.id', 'themes.label_ar', 'themes.label_en')
            ->selectRaw('themes.id as id, themes.label_ar, themes.label_en, COUNT(*) as count')->orderByDesc('count')->get();

        return [
            'item_type' => $counts('item_type', 'item_type')->get()->map(fn ($r) => ['value' => $r->value, 'count' => (int) $r->count])->all(),
            'place' => $counts('place', $place)->whereRaw("{$place} IS NOT NULL")->limit(12)->get()->map(fn ($r) => ['value' => $r->value, 'count' => (int) $r->count])->all(),
            'theme_id' => $themes->map(fn ($r) => ['value' => (int) $r->id, 'label' => ['ar' => $r->label_ar, 'en' => $r->label_en], 'count' => (int) $r->count])->all(),
            'access' => $counts('access', "CASE WHEN access_level = 'public' THEN 'full' ELSE 'preview' END")->get()->map(fn ($r) => ['value' => $r->value, 'count' => (int) $r->count])->all(),
        ];
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
