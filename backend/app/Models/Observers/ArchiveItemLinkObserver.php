<?php

namespace App\Models\Observers;

use App\Models\ArchiveItemLink;
use App\Support\ArchiveItemSearchTextBuilder;

class ArchiveItemLinkObserver
{
    public function saved(ArchiveItemLink $link): void
    {
        $this->rebuild($link);
    }

    public function deleted(ArchiveItemLink $link): void
    {
        $this->rebuild($link);
    }

    private function rebuild(ArchiveItemLink $link): void
    {
        $item = $link->archiveItem()->first();

        if ($item !== null) {
            ArchiveItemSearchTextBuilder::rebuildQuietly($item);
        }
    }
}
