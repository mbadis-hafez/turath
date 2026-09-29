<?php

namespace App\Support\Ocr;

interface NonTextRegionDetector
{
    /**
     * Finds non-text visual content (logos, photographs, scan artifacts) by
     * looking at what's left on the page once every OCR-detected text block's
     * area is excluded. Tesseract only reports blocks it recognized as text,
     * so anything else on the page — a letterhead crest, an artwork photo, a
     * stray scanner mark — never appears in PageLayoutAnalyzer's output at all.
     *
     * @param  array<int, array{x: int, y: int, width: int, height: int}>  $occupiedBboxes  bounding boxes already claimed by text blocks
     * @return array<int, array{bbox: array{x: int, y: int, width: int, height: int}, area_ratio: float, complexity: float}>
     */
    public function detect(string $imagePath, array $occupiedBboxes): array;
}
