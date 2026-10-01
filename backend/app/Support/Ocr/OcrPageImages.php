<?php

namespace App\Support\Ocr;

use App\Models\File;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A page exactly as OCR saw it, so the review screen can draw a region's
 * bounding box over it: region boxes are in the pixels of the rendered page
 * (a PDF rendered by the same PdfPageRasterizer, or the image file itself).
 *
 * The OCR run throws its renders away, so a PDF's pages are rendered again on
 * first request and kept beside the region crops. Rendering is deterministic,
 * so a kept page always matches the boxes drawn from that file.
 */
class OcrPageImages
{
    public function __construct(private readonly PdfPageRasterizer $rasterizer) {}

    /** Absolute path to page N's image, or null when the file has no such page. */
    public function path(File $file, int $page): ?string
    {
        if ($page < 1 || ! $file->isOcrCandidate()) {
            return null;
        }

        $disk = Storage::disk($file->disk);
        if ($file->mime_type !== 'application/pdf') {
            return $page === 1 && $disk->exists($file->path) ? $disk->path($file->path) : null;
        }

        $kept = self::keptPath($file, $page);
        if (! $disk->exists($kept)) {
            $this->renderAll($file);
        }

        return $disk->exists($kept) ? $disk->path($kept) : null;
    }

    public static function keptPath(File $file, int $page): string
    {
        return "archive/ocr-pages/file-{$file->id}/page-{$page}.png";
    }

    private function renderAll(File $file): void
    {
        $disk = Storage::disk($file->disk);
        // Its own directory, so two first requests at once never read each other's half-written pages.
        $tempDir = storage_path('app/ocr-tmp/pages-'.$file->id.'-'.Str::random(8));

        try {
            foreach ($this->rasterizer->rasterize($disk->path($file->path), $tempDir) as $index => $rendered) {
                $png = file_get_contents($rendered);
                if ($png !== false) {
                    $disk->put(self::keptPath($file, $index + 1), $png);
                }
            }
        } finally {
            if (Filesystem::isDirectory($tempDir)) {
                Filesystem::deleteDirectory($tempDir);
            }
        }
    }
}
