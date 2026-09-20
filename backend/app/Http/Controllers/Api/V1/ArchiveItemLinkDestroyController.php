<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use Illuminate\Http\Response;

class ArchiveItemLinkDestroyController
{
    public function __invoke(ArchiveItem $archiveItem, ArchiveItemLink $link): Response
    {
        abort_if($link->archive_item_id !== $archiveItem->id, 404);

        $link->delete();

        return response()->noContent();
    }
}
