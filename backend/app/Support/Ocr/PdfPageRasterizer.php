<?php

namespace App\Support\Ocr;

interface PdfPageRasterizer
{
    /**
     * Render every page of a PDF to an image file, in page order.
     *
     * @return array<int, string> absolute paths to the rendered page images, 1-indexed by array position (page 1 first)
     */
    public function rasterize(string $pdfPath, string $outputDir): array;
}
