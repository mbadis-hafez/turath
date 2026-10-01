<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Support\Ocr\OcrPageImages;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** One page of the item's file as OCR saw it, for drawing region boxes over (see OcrPageImages). */
class ArchiveItemFileOcrPageController
{
    public function image(ArchiveItem $archiveItem, int $page, OcrPageImages $pages): BinaryFileResponse
    {
        $file = $archiveItem->files()->where('role', 'original')->latest('id')->first();
        abort_if($file === null, 404);

        $path = $pages->path($file, $page);
        abort_if($path === null, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=3600']);
    }
}
