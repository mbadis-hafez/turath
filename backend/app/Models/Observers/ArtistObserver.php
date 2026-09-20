<?php

namespace App\Models\Observers;

use App\Models\Artist;
use App\Support\ArchiveItemSearchTextBuilder;
use App\Support\ArtistSearchTextBuilder;
use App\Support\ArtistSlugGenerator;
use App\Support\ArtworkSearchTextBuilder;
use App\Support\Completeness\RecomputesCompleteness;
use App\Support\Proposals\RecordsRevisions;

class ArtistObserver
{
    use RecomputesCompleteness, RecordsRevisions;

    /**
     * Slugs are generated once on creation; they never change on rename and
     * cannot be set by clients (the API strips slug from the payload).
     */
    public function creating(Artist $artist): void
    {
        if (blank($artist->slug)) {
            $artist->slug = ArtistSlugGenerator::generate($artist);
        }

        $this->assignCreator($artist);
    }

    public function saved(Artist $artist): void
    {
        // Before anything else: the search-text rebuild calls syncOriginal(), which wipes the old values.
        $this->recordDirectEditRevision($artist);

        if ($artist->wasRecentlyCreated || $artist->wasChanged(['name_ar', 'name_en', 'legacy_code'])) {
            ArtistSearchTextBuilder::rebuildQuietly($artist);
        }

        if (! $artist->wasRecentlyCreated && $artist->wasChanged(['name_ar', 'name_en'])) {
            $artist->artworks()->each(fn ($artwork) => ArtworkSearchTextBuilder::rebuildQuietly($artwork));

            $artist->archiveItemLinks()->with('archiveItem')->get()
                ->pluck('archiveItem')
                ->filter()
                ->unique('id')
                ->each(fn ($item) => ArchiveItemSearchTextBuilder::rebuildQuietly($item));
        }

        $this->recomputeCompleteness($artist);
    }
}
