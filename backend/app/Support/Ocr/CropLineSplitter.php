<?php

namespace App\Support\Ocr;

use GdImage;

/**
 * Splits a region crop into one image per line of writing, by the gaps
 * between bands of inked rows. Line recognizers (kraken's `ocr -s`) read one
 * line at a time, and kraken's own page segmenter produced degenerate
 * one-pixel lines on crop-sized images of the benchmark letter — so crops are
 * split here instead. Small bands (dots, a word wrapped under the line, like
 * the benchmark's "yahoo" → "yah" + "oo") are merged into the nearest line.
 */
class CropLineSplitter
{
    private const INK_THRESHOLD = 160;

    /** A band shorter than this share of the tallest band is a fragment, not a line. */
    private const FRAGMENT_SHARE = 0.45;

    private const PAD_SHARE = 0.2;

    /**
     * @return array<int, GdImage> one image per line, top to bottom; the whole crop when it is one line (or unreadable)
     */
    public function split(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $inked = [];
        for ($y = 0; $y < $height; $y++) {
            $count = 0;
            for ($x = 0; $x < $width; $x++) {
                $c = imagecolorat($image, $x, $y);
                if (((($c >> 16) & 0xFF) * 299 + (($c >> 8) & 0xFF) * 587 + ($c & 0xFF) * 114) / 1000 < self::INK_THRESHOLD) {
                    $count++;
                }
            }
            $inked[$y] = $count > max(1, $width * 0.005);
        }

        $bands = [];
        $start = null;
        foreach ($inked as $y => $isInked) {
            if ($isInked && $start === null) {
                $start = $y;
            } elseif (! $isInked && $start !== null) {
                $bands[] = [$start, $y - 1];
                $start = null;
            }
        }
        if ($start !== null) {
            $bands[] = [$start, $height - 1];
        }
        if (count($bands) < 2) {
            return [$image];
        }

        $bands = $this->mergeFragments($bands);
        if (count($bands) < 2) {
            return [$image];
        }

        $lines = [];
        foreach ($bands as [$top, $bottom]) {
            $pad = (int) ceil(($bottom - $top + 1) * self::PAD_SHARE);
            $y0 = max(0, $top - $pad);
            $y1 = min($height - 1, $bottom + $pad);
            $line = imagecrop($image, ['x' => 0, 'y' => $y0, 'width' => $width, 'height' => $y1 - $y0 + 1]);
            if ($line !== false) {
                $lines[] = $line;
            }
        }

        return $lines === [] ? [$image] : $lines;
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $bands
     * @return array<int, array{0: int, 1: int}>
     */
    private function mergeFragments(array $bands): array
    {
        $tallest = max(array_map(fn (array $b) => $b[1] - $b[0] + 1, $bands));

        $changed = true;
        while ($changed && count($bands) > 1) {
            $changed = false;
            foreach ($bands as $i => [$top, $bottom]) {
                if ($bottom - $top + 1 >= $tallest * self::FRAGMENT_SHARE) {
                    continue;
                }
                // Join the nearer neighbour.
                $gapAbove = isset($bands[$i - 1]) ? $top - $bands[$i - 1][1] : PHP_INT_MAX;
                $gapBelow = isset($bands[$i + 1]) ? $bands[$i + 1][0] - $bottom : PHP_INT_MAX;
                $j = $gapAbove <= $gapBelow ? $i - 1 : $i + 1;
                $bands[$j] = [min($bands[$j][0], $top), max($bands[$j][1], $bottom)];
                unset($bands[$i]);
                $bands = array_values($bands);
                $changed = true;
                break;
            }
        }

        return $bands;
    }
}
