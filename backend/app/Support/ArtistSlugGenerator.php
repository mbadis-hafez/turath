<?php

namespace App\Support;

use App\Models\Artist;
use Illuminate\Support\Str;

class ArtistSlugGenerator
{
    public static function generate(Artist $artist): string
    {
        $base = Str::slug((string) $artist->name_en);

        if ($base === '') {
            $base = 'artist-'.Str::lower(Str::random(8));
        }

        $slug = $base;
        $suffix = 2;

        while (self::exists($slug)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function exists(string $slug): bool
    {
        return Artist::withTrashed()->where('slug', $slug)->exists();
    }
}
