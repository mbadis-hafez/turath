<?php

namespace App\Support\Ocr;

/**
 * Finds possible correction marks — a word scribbled out, or a line struck
 * through text — inside text regions. It never decides what the corrected
 * value is: a mark only means a person must look and choose.
 */
interface CorrectionMarkDetector
{
    public const KIND_SCRIBBLE = 'scribble';

    public const KIND_LINE_STRIKE = 'line_strike';

    /**
     * @param  array<int, array{x: int, y: int, width: int, height: int}>  $bboxes  regions to inspect on this page
     * @return array<int, array<int, array{kind: string, bbox: array{x: int, y: int, width: int, height: int}}>> marks found, keyed like $bboxes (a region with none is absent)
     */
    public function detect(string $imagePath, array $bboxes): array;
}
