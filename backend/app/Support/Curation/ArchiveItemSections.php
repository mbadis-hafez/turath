<?php

namespace App\Support\Curation;

use App\Models\ArchiveItem;
use App\Support\Proposals\RecordUpdater;

class ArchiveItemSections
{
    /**
     * Flat field update, mirroring ArchiveItemUpdateController.
     *
     * @param  array<string, mixed>  $mappedAttributes  column => new value
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function applyFields(ArchiveItem $archiveItem, array $mappedAttributes): array
    {
        return RecordUpdater::apply($archiveItem, $mappedAttributes);
    }
}
