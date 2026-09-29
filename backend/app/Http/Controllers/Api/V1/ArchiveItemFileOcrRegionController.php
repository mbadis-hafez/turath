<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Models\FileOcrRegion;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/** Serves the cropped source image for one OCR region, for a reviewer to compare against a machine suggestion (or, for handwriting, to read and transcribe themselves). */
class ArchiveItemFileOcrRegionController
{
    public function crop(ArchiveItem $archiveItem, FileOcrRegion $region): Response
    {
        abort_unless($region->file->archive_item_id === $archiveItem->id, 404);
        abort_if($region->crop_path === null, 404);

        $disk = Storage::disk($region->file->disk);
        abort_unless($disk->exists($region->crop_path), 404);

        return response($disk->get($region->crop_path), 200, ['Content-Type' => 'image/png']);
    }
}
