<?php

namespace App\Models\Observers;

use App\Models\ArchiveItem;
use App\Support\ArchiveItemSearchTextBuilder;

class ArchiveItemObserver
{
    private const SEARCH_AFFECTING_COLUMNS = [
        'title_ar', 'title_en', 'description_ar', 'description_en',
        'creator_name', 'publication_name_ar', 'publication_name_en',
    ];

    public function saved(ArchiveItem $item): void
    {
        if ($item->wasRecentlyCreated || $item->wasChanged(self::SEARCH_AFFECTING_COLUMNS)) {
            ArchiveItemSearchTextBuilder::rebuildQuietly($item);
        }
    }

    /**
     * Soft-deleting a parent must not soft-delete its children (D per F3
     * hierarchy spec: children become orphaned, not removed).
     */
    public function deleting(ArchiveItem $item): void
    {
        if (! $item->isForceDeleting()) {
            $item->children()->update(['parent_id' => null]);
        }
    }
}
