<?php

namespace App\Support;

use App\Models\Artist;
use Illuminate\Support\Facades\DB;

class ArtistSearchTextBuilder
{
    /**
     * Build the normalized search columns for an artist from its names,
     * legacy code and all name variants (typos included).
     *
     * @return array{search_text: string, search_compact: string}
     */
    public static function build(Artist $artist): array
    {
        // Always reload: callers may hold instances whose variants relation
        // was loaded before a change (e.g. the variant observer's cached
        // artist during a delete).
        $artist->load('variants');

        $parts = array_filter([
            $artist->name_ar,
            $artist->name_en,
            $artist->legacy_code,
            ...$artist->variants->pluck('name')->all(),
        ], fn ($part) => is_string($part) && $part !== '');

        $joined = implode(' ', $parts);

        return [
            'search_text' => ArabicNormalizer::normalize($joined),
            'search_compact' => ArabicNormalizer::compact($joined),
        ];
    }

    /**
     * Persist rebuilt search columns without firing model events (avoids
     * recursion through the observers) and keep the in-memory copy in sync so
     * later saves don't see the search columns as dirty.
     */
    public static function rebuildQuietly(Artist $artist): void
    {
        $built = self::build($artist);

        DB::table($artist->getTable())->where('id', $artist->getKey())->update($built);

        $artist->forceFill($built)->syncOriginal();
    }
}
