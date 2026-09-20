<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use Illuminate\Http\Response;

class ArchiveItemDestroyController
{
    public function __invoke(ArchiveItem $archiveItem): Response
    {
        $archiveItem->delete();

        return response()->noContent();
    }
}
