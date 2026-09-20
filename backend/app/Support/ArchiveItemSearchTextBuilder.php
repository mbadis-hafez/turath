<?php

namespace App\Support;

use App\Models\ArchiveItem;
use App\Models\Artist;
use Illuminate\Support\Facades\DB;

class ArchiveItemSearchTextBuilder
{
    /**
     * Build the normalized search column from title, description, creator,
     * publication name, legacy ref and the denormalized names of every
     * linked artist (kept in sync on link add/remove and on the linked
     * artist's own rename, same pattern as F2's artworks).
     *
     * @return array{search_text: string}
     */
    public static function build(ArchiveItem $item): array
    {
        $item->loadMissing(['links.linkable']);

        $artistNames = $item->links
            ->map(fn ($link) => $link->linkable)
            ->filter(fn ($linkable) => $linkable instanceof Artist)
            ->flatMap(fn ($artist) => [$artist->name_ar, $artist->name_en])
            ->all();

        $parts = array_filter([
            $item->title_ar,
            $item->title_en,
            $item->description_ar,
            $item->description_en,
            $item->creator_name,
            $item->publication_name_ar,
            $item->publication_name_en,
            $item->legacy_ref,
            ...$artistNames,
        ], fn ($part) => is_string($part) && $part !== '');

        return [
            'search_text' => ArabicNormalizer::normalize(implode(' ', $parts)),
        ];
    }

    public static function rebuildQuietly(ArchiveItem $item): void
    {
        $built = self::build($item);

        DB::table($item->getTable())->where('id', $item->getKey())->update($built);

        $item->forceFill($built)->syncOriginal();
    }
}
