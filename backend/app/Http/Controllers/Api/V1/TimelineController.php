<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * D120: a year-bucketed read path over public events, artist lifespans and
 * artworks. Each entry is tagged by kind so a birth year is never presented as
 * the same kind of fact as an exhibition.
 */
class TimelineController
{
    private const LIMIT = 1000;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'integer'],
            'to' => ['nullable', 'integer'],
            'type' => ['nullable', 'array'],
            'type.*' => [Rule::in(['event', 'artist_lifespan', 'artwork'])],
            'theme_id' => ['nullable', 'array'],
            'theme_id.*' => ['integer'],
        ]);
        $types = $data['type'] ?? ['event'];
        $themes = $data['theme_id'] ?? [];
        $entries = collect();

        if (in_array('event', $types, true)) {
            $events = $this->themed(Event::query()->where('publication_status', 'published'), $themes);
            foreach ($this->overlap($events, $data, 'start_year_from', 'end_year_to')->limit(self::LIMIT)->get() as $e) {
                $entries->push([
                    'kind' => 'event', 'id' => $e->id, 'year_from' => $e->getAttribute('start_year_from'),
                    'year_to' => $e->getAttribute('end_year_to') ?? $e->getAttribute('start_year_to'), 'display' => $e->getAttribute('start_date_display'),
                    'event_type' => $e->event_type, 'title' => ['ar' => $e->title_ar, 'en' => $e->title_en],
                ]);
            }
        }

        if (in_array('artist_lifespan', $types, true)) {
            $artists = $this->themed(Artist::query()->where('publication_status', 'published')->whereNull('merged_into_id'), $themes);
            foreach ($this->overlap($artists, $data, 'birth_year_from', 'death_year_to')->limit(self::LIMIT)->get() as $a) {
                $entries->push([
                    'kind' => 'artist_lifespan', 'id' => $a->id, 'slug' => $a->slug, 'year_from' => $a->getAttribute('birth_year_from'),
                    'year_to' => $a->getAttribute('death_year_to') ?? $a->getAttribute('death_year_from'), 'display' => $a->getAttribute('birth_date_display'),
                    'name' => ['ar' => $a->name_ar, 'en' => $a->name_en],
                ]);
            }
        }

        if (in_array('artwork', $types, true)) {
            $works = $this->themed(Artwork::query()->with('artist')->where('publication_status', 'published'), $themes);
            foreach ($this->overlap($works, $data, 'creation_year_from', 'creation_year_to')->limit(self::LIMIT)->get() as $w) {
                $entries->push([
                    'kind' => 'artwork', 'id' => $w->id, 'year_from' => $w->getAttribute('creation_year_from'), 'year_to' => $w->getAttribute('creation_year_to'),
                    'display' => $w->getAttribute('creation_date_display'), 'title' => ['ar' => $w->title_ar, 'en' => $w->title_en],
                    'artist' => $w->artist ? ['ar' => $w->artist->name_ar, 'en' => $w->artist->name_en] : null,
                ]);
            }
        }

        $buckets = $entries->sortBy(fn ($e) => [$e['year_from'], $e['kind']])->groupBy('year_from')
            ->map(fn ($group, $year) => ['year' => (int) $year, 'entries' => $group->values()->all()])->sortKeys()->values();

        return response()->json(['data' => $buckets, 'meta' => ['total' => $entries->count()]]);
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @param  array<int, int>  $themes
     * @return Builder<T>
     */
    private function themed(Builder $query, array $themes): Builder
    {
        return $themes === [] ? $query : $query->whereHas('themes', fn (Builder $t) => $t->whereIn('themes.id', $themes));
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<T>
     */
    private function overlap(Builder $query, array $data, string $fromCol, string $toCol): Builder
    {
        $query->whereNotNull($fromCol);
        if (isset($data['from'])) {
            $query->whereRaw("COALESCE({$toCol}, {$fromCol}) >= ?", [$data['from']]);
        }
        if (isset($data['to'])) {
            $query->where($fromCol, '<=', $data['to']);
        }

        return $query;
    }
}
