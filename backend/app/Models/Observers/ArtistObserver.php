<?php

namespace App\Models\Observers;

use App\Models\Artist;
use App\Support\ArtistSearchTextBuilder;
use App\Support\ArtistSlugGenerator;
use App\Support\ArtworkSearchTextBuilder;

class ArtistObserver
{
    /**
     * Slugs are generated once on creation; they never change on rename and
     * cannot be set by clients (the API strips slug from the payload).
     */
    public function creating(Artist $artist): void
    {
        if (blank($artist->slug)) {
            $artist->slug = ArtistSlugGenerator::generate($artist);
        }
    }

    public function saved(Artist $artist): void
    {
        if ($artist->wasRecentlyCreated || $artist->wasChanged(['name_ar', 'name_en', 'legacy_code'])) {
            ArtistSearchTextBuilder::rebuildQuietly($artist);
        }

        if (! $artist->wasRecentlyCreated && $artist->wasChanged(['name_ar', 'name_en'])) {
            $artist->artworks()->each(fn ($artwork) => ArtworkSearchTextBuilder::rebuildQuietly($artwork));
        }
    }
}
