<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Source;
use App\Models\Theme;
use App\Support\ArchiveAccessResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HomeController
{
    public function __invoke(Request $request): JsonResponse
    {
        $published = fn (Builder $query): Builder => $query->where('publication_status', 'published');

        $archiveItems = $published(ArchiveItem::query());
        $artists = $published(Artist::query());
        $artworks = $published(Artwork::query());

        $recent = (clone $archiveItems)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'stats' => [
                    'materials' => $archiveItems->count(),
                    'artists' => $artists->count(),
                    'artworks' => $artworks->count(),
                    'sources' => Source::query()->count(),
                ],
                'updated_at' => $this->latestUpdatedAt($archiveItems, $artists, $artworks),
                'popular_searches' => $this->popularSearches($artists),
                'themes' => $this->themes(),
                'archive_feature' => $recent->first() ? $this->archiveSummary($recent->first(), $request) : null,
                'recent_archive_items' => $recent->skip(1)->values()->map(fn (ArchiveItem $item) => $this->archiveSummary($item, $request)),
                'artists' => $this->artists($artists),
                'places' => $this->places(),
            ],
        ]);
    }

    /**
     * @return array<int, array{term: array{ar: string|null, en: string|null}}>
     */
    private function popularSearches(Builder $artists): array
    {
        return (clone $artists)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['name_ar', 'name_en'])
            ->map(fn (Artist $artist) => ['term' => $this->localized($artist->name_ar, $artist->name_en)])
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: array{ar: string|null, en: string|null}, count: int}>
     */
    private function themes(): array
    {
        return Theme::query()
            ->leftJoin('theme_taggables', 'themes.id', '=', 'theme_taggables.theme_id')
            ->select('themes.id', 'themes.label_ar', 'themes.label_en')
            ->selectRaw('COUNT(theme_taggables.theme_id) as material_count')
            ->groupBy('themes.id', 'themes.label_ar', 'themes.label_en')
            ->orderByDesc('material_count')
            ->orderBy('themes.id')
            ->limit(4)
            ->get()
            ->map(fn (Theme $theme) => [
                'id' => $theme->id,
                'label' => $this->localized($theme->label_ar, $theme->label_en),
                'count' => (int) $theme->getAttribute('material_count'),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, slug: string, name: array{ar: string|null, en: string|null}, materials_count: int}>
     */
    private function artists(Builder $artists): array
    {
        return (clone $artists)
            ->withCount([
                'archiveItemLinks as materials_count' => fn (Builder $query) => $query->whereHas(
                    'archiveItem',
                    fn (Builder $itemQuery) => $itemQuery->where('publication_status', 'published'),
                ),
            ])
            ->orderByDesc('materials_count')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get(['id', 'slug', 'name_ar', 'name_en'])
            ->map(fn (Artist $artist) => [
                'id' => $artist->id,
                'slug' => $artist->slug,
                'name' => $this->localized($artist->name_ar, $artist->name_en),
                'materials_count' => (int) $artist->getAttribute('materials_count'),
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: array{ar: string|null, en: string|null}, materials_count: int}>
     */
    private function places(): array
    {
        return ArchiveItem::query()
            ->where('publication_status', 'published')
            ->where(fn (Builder $query) => $query->whereNotNull('place_ar')->orWhereNotNull('place_en'))
            ->select('place_ar', 'place_en')
            ->selectRaw('COUNT(*) as material_count')
            ->groupBy('place_ar', 'place_en')
            ->orderByDesc('material_count')
            ->limit(6)
            ->get()
            ->map(fn (ArchiveItem $item) => [
                'name' => $this->localized($item->getAttribute('place_ar'), $item->getAttribute('place_en')),
                'materials_count' => (int) $item->getAttribute('material_count'),
            ])
            ->all();
    }

    /**
     * @return array{id: int, item_type: string, title: array{ar: string|null, en: string|null}, content: array{display: string|null, year_from: int|null, year_to: int|null}|null, creator_name: string|null, description: array{ar: string|null, en: string|null}|null, restricted: bool}
     */
    private function archiveSummary(ArchiveItem $item, Request $request): array
    {
        $full = ArchiveAccessResolver::canViewFull($request->user(), $item);

        return [
            'id' => $item->id,
            'item_type' => $item->item_type,
            'title' => $this->localized($item->title_ar, $item->title_en),
            'content' => $item->content === null ? null : [
                'display' => $item->content->display,
                'year_from' => $item->content->yearFrom,
                'year_to' => $item->content->yearTo,
            ],
            'creator_name' => $full ? $item->creator_name : null,
            'description' => $full ? $this->localized($item->description_ar, $item->description_en) : null,
            'restricted' => ! $full,
        ];
    }

    private function latestUpdatedAt(Builder $archiveItems, Builder $artists, Builder $artworks): ?string
    {
        $timestamps = [
            $archiveItems->max('updated_at'),
            $artists->max('updated_at'),
            $artworks->max('updated_at'),
        ];

        $latest = collect($timestamps)->filter()->max();

        return $latest === null ? null : Carbon::parse($latest)->toIso8601String();
    }

    /**
     * @return array{ar: string|null, en: string|null}
     */
    private function localized(?string $ar, ?string $en): array
    {
        return ['ar' => $ar, 'en' => $en];
    }
}
