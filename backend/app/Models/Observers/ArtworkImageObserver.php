<?php

namespace App\Models\Observers;

use App\Models\ArtworkImage;

/**
 * Enforces "an artwork with at least one image has exactly one primary
 * image" (never zero, never more than one) on every write path — the API,
 * ArtworkMerger, importers, seeders — rather than leaving it to whichever
 * controller action happens to remember. Setting a primary explicitly
 * (PATCH is_final=true) already clears every other image's flag inside its
 * own transaction in ArtworkImageController::update(); this observer only
 * covers the two moments that invariant would otherwise go unmaintained:
 * the first image landing on an artwork, and the primary being removed.
 *
 * ArtworkImage has no SoftDeletes, so `deleted` here is a hard delete.
 */
class ArtworkImageObserver
{
    public function created(ArtworkImage $image): void
    {
        $hasPrimary = ArtworkImage::where('artwork_id', $image->artwork_id)->where('is_final', true)->exists();

        if (! $hasPrimary) {
            $image->update(['is_final' => true]);
        }
    }

    public function deleted(ArtworkImage $image): void
    {
        if ($image->is_final) {
            static::promoteOldest($image->artwork_id);
        }
    }

    /**
     * Re-asserts the invariant for an artwork whose images changed via a
     * bulk query that bypassed model events (e.g. ArtworkMerger's single
     * UPDATE statement moving images between artworks). A no-op if the
     * artwork already has a primary or has no images at all.
     */
    public static function promoteOldest(int $artworkId): void
    {
        $hasPrimary = ArtworkImage::where('artwork_id', $artworkId)->where('is_final', true)->exists();

        if ($hasPrimary) {
            return;
        }

        ArtworkImage::where('artwork_id', $artworkId)->oldest('id')->first()?->update(['is_final' => true]);
    }
}
