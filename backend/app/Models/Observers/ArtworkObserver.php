<?php

namespace App\Models\Observers;

use App\Models\Artwork;
use App\Support\ArtworkSearchTextBuilder;
use App\Support\Completeness\RecomputesCompleteness;
use App\Support\Curation\PipelineService;

class ArtworkObserver
{
    use RecomputesCompleteness;

    private const SEARCH_AFFECTING_COLUMNS = [
        'title_ar', 'title_en', 'medium_ar', 'medium_en', 'legacy_ref', 'artist_id',
    ];

    public function creating(Artwork $artwork): void
    {
        $this->assignCreator($artwork);
    }

    public function saved(Artwork $artwork): void
    {
        if ($artwork->wasRecentlyCreated || $artwork->wasChanged(self::SEARCH_AFFECTING_COLUMNS)) {
            ArtworkSearchTextBuilder::rebuildQuietly($artwork);
        }

        if ($artwork->wasRecentlyCreated) {
            (new PipelineService)->ensureStages($artwork);
        }

        $this->recomputeCompleteness($artwork);
    }
}
