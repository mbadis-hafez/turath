<?php

namespace App\Support\Ocr;

use GdImage;

/**
 * Pixel heuristics for the two kinds of correction mark seen in the archive,
 * measured at each region's own text scale (line height, from the region's
 * ink profile) so small bold print and large handwriting are judged alike:
 *
 * - scribble: a word crossed out with overlapping strokes (the benchmark
 *   letter's first attempt at an email address). Detected as a cluster of
 *   letter-sized windows whose ink density is both high outright and several
 *   times the region's own typical density — bold print is dense everywhere,
 *   a scribble is dense in one place.
 *
 * - line strike: a stroke drawn through text. Detected as a long horizontal
 *   ink run that crosses whitespace (a gap between letters or words — an
 *   Arabic baseline stays inside a word) with letter ink both just above and
 *   just below it. That last condition is what keeps form underlines and
 *   signing lines, which only have writing above them, from counting; dotted
 *   fill lines never form a long enough run in the first place.
 *
 * Thresholds were calibrated on the two pages of the benchmark letter and on
 * synthetic cases, so they are a first pass: a false positive costs a
 * reviewer a look, a false negative costs a wrong value, and the thresholds
 * lean accordingly. Not a trained model; swappable behind the interface.
 */
class GdCorrectionMarkDetector implements CorrectionMarkDetector
{
    private const INK_THRESHOLD = 160;

    /** Regions above this many pixels are sampled every other pixel. */
    private const DOWNSAMPLE_ABOVE_PIXELS = 600_000;

    private const SCRIBBLE_WINDOW_OF_LINE = 0.6;

    /** Benchmark: the scribble reached 3.9 strokes per column; cursive, print, digits, a signature and the logo stayed at or below 3.2. */
    private const SCRIBBLE_MIN_CROSSINGS = 3.5;

    private const SCRIBBLE_MIN_RATIO = 1.6;

    private const SCRIBBLE_MIN_WINDOWS = 2;

    private const SCRIBBLE_GROW_SHARE = 0.8;

    private const STRIKE_MIN_LENGTH_OF_LINE = 2.5;

    private const STRIKE_MIN_GAP_OF_LINE = 0.4;

    private const STRIKE_MIN_THROUGH_SHARE = 0.25;

    private const STRIKE_BRIDGE_PX = 2;

    public function detect(string $imagePath, array $bboxes): array
    {
        $data = @file_get_contents($imagePath);
        $image = $data === false ? false : @imagecreatefromstring($data);
        if ($image === false) {
            return [];
        }

        $found = [];
        foreach ($bboxes as $key => $bbox) {
            $marks = $this->inspect($image, $bbox);
            if ($marks !== []) {
                $found[$key] = $marks;
            }
        }

        return $found;
    }

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $bbox
     * @return array<int, array{kind: string, bbox: array{x: int, y: int, width: int, height: int}}>
     */
    private function inspect(GdImage $image, array $bbox): array
    {
        $x0 = max(0, $bbox['x']);
        $y0 = max(0, $bbox['y']);
        $x1 = min(imagesx($image), $bbox['x'] + $bbox['width']);
        $y1 = min(imagesy($image), $bbox['y'] + $bbox['height']);
        if ($x1 - $x0 < 8 || $y1 - $y0 < 8) {
            return [];
        }

        $step = ($x1 - $x0) * ($y1 - $y0) > self::DOWNSAMPLE_ABOVE_PIXELS ? 2 : 1;
        $rows = $this->binarize($image, $x0, $y0, $x1, $y1, $step);
        $lineHeight = $this->lineHeight($rows);
        if ($lineHeight === null) {
            return [];
        }

        $toPage = fn (array $b) => [
            'x' => $x0 + $b['x'] * $step, 'y' => $y0 + $b['y'] * $step,
            'width' => $b['width'] * $step, 'height' => $b['height'] * $step,
        ];

        return [
            ...array_map(fn ($b) => ['kind' => self::KIND_SCRIBBLE, 'bbox' => $toPage($b)], $this->scribbles($rows, $lineHeight)),
            ...array_map(fn ($b) => ['kind' => self::KIND_LINE_STRIKE, 'bbox' => $toPage($b)], $this->strikes($rows, $lineHeight)),
        ];
    }

    /**
     * One string per row, "1" for ink and "0" for paper — strings so that run
     * finding is a regex in C rather than a PHP loop.
     *
     * @return array<int, string>
     */
    private function binarize(GdImage $image, int $x0, int $y0, int $x1, int $y1, int $step): array
    {
        $rows = [];
        for ($y = $y0; $y < $y1; $y += $step) {
            $row = '';
            for ($x = $x0; $x < $x1; $x += $step) {
                $c = imagecolorat($image, $x, $y);
                $brightness = ((($c >> 16) & 0xFF) * 299 + (($c >> 8) & 0xFF) * 587 + ($c & 0xFF) * 114) / 1000;
                $row .= $brightness < self::INK_THRESHOLD ? '1' : '0';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Median height of the bands of rows that carry ink — the region's text
     * line height, whatever the font size or handwriting. Null for a region
     * with no ink worth measuring.
     *
     * @param  array<int, string>  $rows
     */
    private function lineHeight(array $rows): ?int
    {
        $width = strlen($rows[0] ?? '');
        $bands = [];
        $run = 0;
        foreach ($rows as $row) {
            if (substr_count($row, '1') > $width * 0.01) {
                $run++;
            } elseif ($run > 0) {
                $bands[] = $run;
                $run = 0;
            }
        }
        if ($run > 0) {
            $bands[] = $run;
        }
        $bands = array_filter($bands, fn (int $h) => $h >= 4);
        if ($bands === []) {
            return null;
        }
        sort($bands);

        return max(8, $bands[intdiv(count($bands), 2)]);
    }

    /**
     * Windows one text line tall and 0.6 of a line wide, scored by the mean
     * number of separate strokes a vertical scan crosses per column. Cursive
     * and print cross one to three strokes; a word scribbled out with a
     * zigzag crosses many; a solid logo bar or bold letter is dense but
     * crosses few, which is why this isn't an ink-density measure — density
     * could not tell the benchmark's scribble from its logo or its bold
     * footer.
     *
     * @param  array<int, string>  $rows
     * @return array<int, array{x: int, y: int, width: int, height: int}>
     */
    private function scribbles(array $rows, int $lineHeight): array
    {
        $height = count($rows);
        $width = strlen($rows[0]);
        $winWidth = max(6, (int) round($lineHeight * self::SCRIBBLE_WINDOW_OF_LINE));
        $winHeight = min($height, $lineHeight);
        $strideX = max(1, intdiv($winWidth, 2));
        $strideY = max(1, intdiv($winHeight, 4));
        if ($width < $winWidth) {
            return [];
        }

        // Integral image of stroke entries: a pixel that is ink with paper (or the
        // window's top edge) above it is where a vertical scan enters a stroke.
        $entries = array_fill(0, $height + 1, array_fill(0, $width + 1, 0));
        $inkPrefix = [];
        for ($y = 0; $y < $height; $y++) {
            $row = $rows[$y];
            $rowSum = 0;
            $inkSum = 0;
            $inkPrefix[$y] = [0];
            for ($x = 0; $x < $width; $x++) {
                $isInk = $row[$x] === '1';
                $rowSum += ($isInk && ($y === 0 || $rows[$y - 1][$x] === '0')) ? 1 : 0;
                $entries[$y + 1][$x + 1] = $entries[$y][$x + 1] + $rowSum;
                $inkSum += $isInk ? 1 : 0;
                $inkPrefix[$y][] = $inkSum;
            }
        }

        $scores = [];
        $inked = [];
        for ($gy = 0; $gy * $strideY + $winHeight <= $height; $gy++) {
            for ($gx = 0; $gx * $strideX + $winWidth <= $width; $gx++) {
                $y1 = $gy * $strideY;
                $y2 = $y1 + $winHeight;
                $x1 = $gx * $strideX;
                $x2 = $x1 + $winWidth;
                $strokes = $entries[$y2][$x2] - $entries[$y1 + 1][$x2] - $entries[$y2][$x1] + $entries[$y1 + 1][$x1]
                    + ($inkPrefix[$y1][$x2] - $inkPrefix[$y1][$x1]); // strokes already under way at the window's top edge
                $score = $strokes / $winWidth;
                $scores[$gy][$gx] = $score;
                if ($score > 0.2) {
                    $inked[] = $score;
                }
            }
        }
        if (count($inked) < self::SCRIBBLE_MIN_WINDOWS * 2) {
            return [];
        }
        sort($inked);
        $threshold = max(self::SCRIBBLE_MIN_CROSSINGS, $inked[intdiv(count($inked), 2)] * self::SCRIBBLE_MIN_RATIO);

        // Hysteresis: a mark needs one window over the threshold, and grows into
        // neighbours nearly as busy — a scribble's edges are thinner than its middle.
        $busy = [];
        $strong = [];
        foreach ($scores as $gy => $cols) {
            foreach ($cols as $gx => $score) {
                if ($score >= $threshold * self::SCRIBBLE_GROW_SHARE) {
                    $busy["{$gy}:{$gx}"] = [$gy, $gx];
                }
                if ($score >= $threshold) {
                    $strong["{$gy}:{$gx}"] = true;
                }
            }
        }

        $marks = [];
        foreach ($this->clusters($busy) as $cluster) {
            $hasSeed = array_filter($cluster, fn (array $cell) => isset($strong["{$cell[0]}:{$cell[1]}"])) !== [];
            if (! $hasSeed || count($cluster) < self::SCRIBBLE_MIN_WINDOWS) {
                continue;
            }
            $ys = array_column($cluster, 0);
            $xs = array_column($cluster, 1);
            $marks[] = [
                'x' => min($xs) * $strideX, 'y' => min($ys) * $strideY,
                'width' => (max($xs) - min($xs)) * $strideX + $winWidth, 'height' => (max($ys) - min($ys)) * $strideY + $winHeight,
            ];
        }

        return $marks;
    }

    /**
     * 8-connected clusters of grid cells.
     *
     * @param  array<string, array{0: int, 1: int}>  $cells
     * @return array<int, array<int, array{0: int, 1: int}>>
     */
    private function clusters(array $cells): array
    {
        $clusters = [];
        $seen = [];
        foreach ($cells as $key => $cell) {
            if (isset($seen[$key])) {
                continue;
            }
            $stack = [$cell];
            $seen[$key] = true;
            $cluster = [];
            while ($stack !== []) {
                [$y, $x] = array_pop($stack);
                $cluster[] = [$y, $x];
                for ($dy = -1; $dy <= 1; $dy++) {
                    for ($dx = -1; $dx <= 1; $dx++) {
                        $n = ($y + $dy).':'.($x + $dx);
                        if (isset($cells[$n]) && ! isset($seen[$n])) {
                            $seen[$n] = true;
                            $stack[] = $cells[$n];
                        }
                    }
                }
            }
            $clusters[] = $cluster;
        }

        return $clusters;
    }

    /**
     * @param  array<int, string>  $rows
     * @return array<int, array{x: int, y: int, width: int, height: int}>
     */
    private function strikes(array $rows, int $lineHeight): array
    {
        $height = count($rows);
        $minLength = (int) ceil($lineHeight * self::STRIKE_MIN_LENGTH_OF_LINE);
        $minGap = max(3, (int) ceil($lineHeight * self::STRIKE_MIN_GAP_OF_LINE));
        $reach = max(3, (int) round($lineHeight * 0.5));
        $bridge = self::STRIKE_BRIDGE_PX;

        $hits = [];
        foreach ($rows as $y => $row) {
            preg_match_all('/1+(?:0{1,'.$bridge.'}1+)*/', $row, $runs, PREG_OFFSET_CAPTURE);
            foreach ($runs[0] as [$run, $start]) {
                $length = strlen($run);
                if ($length < $minLength) {
                    continue;
                }
                if ($this->crossesText($rows, $y, $start, $length, $reach, $minGap, $height)) {
                    $hits[] = ['y' => $y, 'start' => $start, 'end' => $start + $length];
                }
            }
        }

        // A stroke is several pixels thick: merge hits on adjacent rows that overlap horizontally.
        $marks = [];
        foreach ($hits as $hit) {
            foreach ($marks as &$mark) {
                if ($hit['y'] <= $mark['y2'] + 2 && $hit['start'] < $mark['x2'] && $hit['end'] > $mark['x1']) {
                    $mark = ['x1' => min($mark['x1'], $hit['start']), 'x2' => max($mark['x2'], $hit['end']), 'y1' => $mark['y1'], 'y2' => $hit['y']];

                    continue 2;
                }
            }
            unset($mark);
            $marks[] = ['x1' => $hit['start'], 'x2' => $hit['end'], 'y1' => $hit['y'], 'y2' => $hit['y']];
        }

        return array_map(fn ($m) => ['x' => $m['x1'], 'y' => $m['y1'], 'width' => $m['x2'] - $m['x1'], 'height' => $m['y2'] - $m['y1'] + 1], $marks);
    }

    /**
     * Whether a long run at row $y is drawn *through* text: somewhere along it
     * there's a stretch of columns with no ink above or below except the run
     * itself (it crossed a gap), and along enough of it there is letter ink
     * both above and below the stroke.
     *
     * @param  array<int, string>  $rows
     */
    private function crossesText(array $rows, int $y, int $start, int $length, int $reach, int $minGap, int $height): bool
    {
        // The stroke's own thickness: rows just around it that are inked in the same columns.
        $band = 3;
        $above = [];
        $below = [];
        for ($x = $start; $x < $start + $length; $x++) {
            $inkAbove = false;
            for ($yy = max(0, $y - $reach); $yy < $y - $band; $yy++) {
                if ($rows[$yy][$x] === '1') {
                    $inkAbove = true;
                    break;
                }
            }
            $inkBelow = false;
            for ($yy = $y + $band + 1; $yy <= min($height - 1, $y + $reach); $yy++) {
                if ($rows[$yy][$x] === '1') {
                    $inkBelow = true;
                    break;
                }
            }
            $above[] = $inkAbove;
            $below[] = $inkBelow;
        }

        $through = 0;
        $gap = 0;
        $longestGap = 0;
        foreach ($above as $i => $inkAbove) {
            if ($inkAbove && $below[$i]) {
                $through++;
            }
            if (! $inkAbove && ! $below[$i]) {
                $gap++;
                $longestGap = max($longestGap, $gap);
            } else {
                $gap = 0;
            }
        }

        return $longestGap >= $minGap && $through >= $length * self::STRIKE_MIN_THROUGH_SHARE;
    }
}
