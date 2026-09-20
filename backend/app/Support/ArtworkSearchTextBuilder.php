<?php

namespace App\Support;

use App\Models\Artwork;
use Illuminate\Support\Facades\DB;

class ArtworkSearchTextBuilder
{
    /**
     * Build the normalized search column from title, medium, legacy ref and
     * the linked artist's names (denormalized in for query simplicity).
     *
     * @return array{search_text: string}
     */
    public static function build(Artwork $artwork): array
    {
        $artwork->loadMissing('artist');

        $parts = array_filter([
            $artwork->title_ar,
            $artwork->title_en,
            $artwork->medium_ar,
            $artwork->medium_en,
            $artwork->legacy_ref,
            $artwork->artist?->name_ar,
            $artwork->artist?->name_en,
        ], fn ($part) => is_string($part) && $part !== '');

        return [
            'search_text' => ArabicNormalizer::normalize(implode(' ', $parts)),
        ];
    }

    /**
     * Persist the rebuilt search column without firing model events (avoids
     * observer recursion) and keep the in-memory copy in sync.
     */
    public static function rebuildQuietly(Artwork $artwork): void
    {
        $built = self::build($artwork);

        DB::table($artwork->getTable())->where('id', $artwork->getKey())->update($built);

        $artwork->forceFill($built)->syncOriginal();
    }
}
