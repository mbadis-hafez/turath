<?php

namespace App\Support\Ocr;

interface PageLayoutAnalyzer
{
    /**
     * Detects text-bearing layout blocks on a rendered page image, with their
     * geometry — independent of, and prior to, deciding whether any given
     * block's text should be trusted. Blocks with zero recognized words (e.g.
     * a logo or photograph) are not returned here; see ImageRegionDetector for
     * those. `words` preserves tesseract's own reading order (line by line,
     * word by word) with per-word geometry — RegionClassifier uses it to split
     * a block where a printed "label:" and its value share one physical line,
     * which a compact form row commonly does.
     *
     * @return array<int, array{block_id: string, text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}, word_count: int, avg_word_length: float, words: array<int, array{text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}}>}>
     */
    public function analyze(string $imagePath): array;
}
