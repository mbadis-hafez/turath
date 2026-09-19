<?php

namespace App\Models\Observers;

use App\Models\ArtistNameVariant;
use App\Support\ArtistSearchTextBuilder;

class ArtistNameVariantObserver
{
    public function saved(ArtistNameVariant $variant): void
    {
        $this->rebuildParent($variant);
    }

    public function deleted(ArtistNameVariant $variant): void
    {
        $this->rebuildParent($variant);
    }

    private function rebuildParent(ArtistNameVariant $variant): void
    {
        $artist = $variant->artist()->first();

        if ($artist !== null) {
            ArtistSearchTextBuilder::rebuildQuietly($artist);
        }
    }
}
