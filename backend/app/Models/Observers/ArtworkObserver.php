<?php

namespace App\Models\Observers;

use App\Models\Artwork;
use App\Support\ArtworkSearchTextBuilder;

class ArtworkObserver
{
    private const SEARCH_AFFECTING_COLUMNS = [
        'title_ar', 'title_en', 'medium_ar', 'medium_en', 'legacy_ref', 'artist_id',
    ];

    public function saved(Artwork $artwork): void
    {
        if ($artwork->wasRecentlyCreated || $artwork->wasChanged(self::SEARCH_AFFECTING_COLUMNS)) {
            ArtworkSearchTextBuilder::rebuildQuietly($artwork);
        }
    }
}
